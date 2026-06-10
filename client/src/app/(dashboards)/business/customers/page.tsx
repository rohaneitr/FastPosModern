"use client";

import React, { useState, useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import useSWR from "swr";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { Search, Plus, Loader2, X, Users, Edit, Trash2, Download } from "lucide-react";
import toast from "react-hot-toast";
import clsx from "clsx";
import api from "@/lib/api";

const fetcher = (url: string) => api.get(url).then((res) => res.data);

// Zod Validation Schema matching backend FormRequest exactly
const customerSchema = z.object({
  type: z.enum(['customer', 'both']),
  first_name: z.string().min(1, "First name is required").max(255),
  middle_name: z.string().max(255).optional().nullable(),
  last_name: z.string().max(255).optional().nullable(),
  email: z.string().email("Invalid email").max(255).optional().or(z.literal('')),
  mobile: z.string().max(255).optional().nullable(),
  city: z.string().max(255).optional().nullable(),
  state: z.string().max(255).optional().nullable(),
  country: z.string().max(255).optional().nullable(),
});

type CustomerFormValues = z.infer<typeof customerSchema>;

export default function CustomersPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  const initialSearch = searchParams.get('cust_search') || "";
  const initialPage = parseInt(searchParams.get('cust_page') || "1", 10);

  const [searchTerm, setSearchTerm] = useState(initialSearch);
  const [currentPage, setCurrentPage] = useState(initialPage);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [isExporting, setIsExporting] = useState(false);

  // URL Debounce Sync
  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      const params = new URLSearchParams(searchParams.toString());
      if (searchTerm) {
        params.set('cust_search', searchTerm);
      } else {
        params.delete('cust_search');
      }
      params.set('cust_page', currentPage.toString());
      router.replace(`?${params.toString()}`);
    }, 400);
    return () => clearTimeout(delayDebounceFn);
  }, [searchTerm, currentPage, router, searchParams]);

  // Hooks
  const searchParam = searchTerm ? `&search=${encodeURIComponent(searchTerm)}` : '';
  const { data: customersData, isLoading, mutate } = useSWR(
    `/tenant/contacts?type=customer&per_page=20&page=${currentPage}${searchParam}`,
    fetcher,
    { revalidateOnFocus: false, keepPreviousData: true }
  );

  const customers = customersData?.data || [];

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<CustomerFormValues>({
    resolver: zodResolver(customerSchema),
    defaultValues: {
      type: 'customer',
      first_name: '',
      middle_name: '',
      last_name: '',
      email: '',
      mobile: '',
      city: '',
      state: '',
      country: '',
    }
  });

  const onSubmit = async (data: CustomerFormValues) => {
    try {
      await api.post('/tenant/contacts', data);
      toast.success("Customer added successfully!");
      setIsModalOpen(false);
      reset();
      mutate();
    } catch (err: unknown) {
      const errorObj = err as Record<string, any>;
      const errorMessage = errorObj.response?.data?.message || "Failed to add customer.";
      toast.error(errorMessage);
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm("Are you sure? This will archive the customer, but keep historical transactions intact.")) return;
    
    setDeletingId(id);
    try {
      await api.delete(`/tenant/contacts/${id}`);
      toast.success("Customer archived successfully.");
      mutate();
    } catch (err: unknown) {
      toast.error("Failed to archive customer.");
    } finally {
      setDeletingId(null);
    }
  };

  const exportCSV = async () => {
    try {
      setIsExporting(true);
      // Construct CSV from current loaded customers
      const csvContent = "ID,Name,Mobile,Email,City,State,Country,Created At\n" + customers.map((c: any) => {
        return `"${c.id}","${c.name}","${c.mobile || ''}","${c.email || ''}","${c.city || ''}","${c.state || ''}","${c.country || ''}","${c.created_at}"`;
      }).join("\n");

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `Customers_Export_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      toast.success("Customers exported successfully");
    } catch (err) {
      toast.error("Failed to export customers.");
    } finally {
      setIsExporting(false);
    }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto text-slate-900">
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <Users className="w-6 h-6 text-indigo-600" />
            Customers
          </h1>
          <p className="text-slate-500 text-sm mt-1">Manage your business customers and contacts.</p>
        </div>
        
        <div className="flex items-center gap-3 w-full md:w-auto">
          <div className="relative flex-1 md:w-64">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input 
              type="text" 
              placeholder="Search by name, mobile..." 
              value={searchTerm}
              onChange={(e) => { setSearchTerm(e.target.value); setCurrentPage(1); }}
              className="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
            />
          </div>
          <button 
            onClick={exportCSV}
            disabled={isExporting}
            className="flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors disabled:opacity-50"
          >
            {isExporting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Download className="w-4 h-4" />}
            <span className="hidden sm:inline">{isExporting ? 'Processing...' : 'Export'}</span>
          </button>
          <button 
            onClick={() => setIsModalOpen(true)}
            className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors"
          >
            <Plus className="w-4 h-4" />
            <span className="hidden sm:inline">Add Customer</span>
          </button>
        </div>
      </div>

      {/* High-Density Grid */}
      <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider">
                <th className="px-6 py-4 font-semibold">Name</th>
                <th className="px-6 py-4 font-semibold">Mobile</th>
                <th className="px-6 py-4 font-semibold">Email</th>
                <th className="px-6 py-4 font-semibold">City</th>
                <th className="px-6 py-4 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {isLoading ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i} className="animate-pulse">
                    <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-3/4"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-1/2"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-2/3"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-1/2"></div></td>
                    <td className="px-6 py-4 flex justify-end"><div className="h-4 bg-slate-200 rounded w-12"></div></td>
                  </tr>
                ))
              ) : customers.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
                    <div className="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                      <Users className="w-6 h-6 text-slate-400" />
                    </div>
                    <p className="font-medium text-slate-800">No customers found.</p>
                  </td>
                </tr>
              ) : (
                customers.map((item: any) => (
                  <tr key={item.id} className="hover:bg-slate-50/50 transition-colors">
                    <td className="px-6 py-4 text-sm font-medium text-slate-900">{item.name}</td>
                    <td className="px-6 py-4 text-sm text-slate-500">{item.mobile || '-'}</td>
                    <td className="px-6 py-4 text-sm text-slate-500">{item.email || '-'}</td>
                    <td className="px-6 py-4 text-sm text-slate-500">{item.city || '-'}</td>
                    <td className="px-6 py-4 text-sm text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button className="p-1.5 text-slate-400 hover:text-indigo-600 rounded transition-colors" title="Edit">
                          <Edit className="w-4 h-4" />
                        </button>
                        <button 
                          onClick={() => handleDelete(item.id)}
                          disabled={deletingId === item.id}
                          className="p-1.5 text-slate-400 hover:text-rose-600 rounded transition-colors disabled:opacity-50" 
                          title="Archive"
                        >
                          {deletingId === item.id ? <Loader2 className="w-4 h-4 animate-spin" /> : <Trash2 className="w-4 h-4" />}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* CREATE CUSTOMER MODAL */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col">
            <div className="flex items-center justify-between p-5 border-b border-slate-100">
              <h2 className="text-lg font-bold text-slate-800 flex items-center gap-2">
                <Users className="w-5 h-5 text-indigo-600" />
                Add New Customer
              </h2>
              <button 
                onClick={() => { setIsModalOpen(false); reset(); }}
                className="text-slate-400 hover:text-slate-600 transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit(onSubmit)} className="p-5 flex flex-col gap-4 overflow-y-auto max-h-[70vh] custom-scrollbar">
              
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-semibold text-slate-700">First Name <span className="text-rose-500">*</span></label>
                  <input 
                    {...register("first_name")}
                    className={clsx("w-full px-3 py-2 border rounded-lg text-sm", errors.first_name ? "border-rose-500" : "border-slate-300")}
                  />
                  {errors.first_name && <span className="text-xs text-rose-500 font-semibold">{errors.first_name.message}</span>}
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-semibold text-slate-700">Last Name</label>
                  <input 
                    {...register("last_name")}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-semibold text-slate-700">Mobile Phone</label>
                  <input 
                    {...register("mobile")}
                    className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                    placeholder="+1234567890"
                  />
                  {errors.mobile && <span className="text-xs text-rose-500 font-semibold">{errors.mobile.message}</span>}
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-semibold text-slate-700">Email Address</label>
                  <input 
                    type="email"
                    {...register("email")}
                    className={clsx("w-full px-3 py-2 border rounded-lg text-sm", errors.email ? "border-rose-500" : "border-slate-300")}
                  />
                  {errors.email && <span className="text-xs text-rose-500 font-semibold">{errors.email.message}</span>}
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-semibold text-slate-700">City</label>
                  <input {...register("city")} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-semibold text-slate-700">State</label>
                  <input {...register("state")} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-semibold text-slate-700">Country</label>
                  <input {...register("country")} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                </div>
              </div>

              {/* Submit */}
              <div className="mt-4 flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => { setIsModalOpen(false); reset(); }}
                  className="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-medium text-sm transition-colors disabled:opacity-50"
                >
                  {isSubmitting && <Loader2 className="w-4 h-4 animate-spin" />}
                  {isSubmitting ? 'Processing...' : 'Save Customer'}
                </button>
              </div>

            </form>
          </div>
        </div>
      )}

    </div>
  );
}
