"use client";

import React, { useState, useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import useSWR from "swr";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { AlertTriangle, ArrowRightLeft, Package, Search, Plus, Loader2, X } from "lucide-react";
import toast from "react-hot-toast";
import clsx from "clsx";
import api from "@/lib/api";

const fetcher = (url: string) => api.get(url).then((res) => res.data);

// Validation Schema
const transferSchema = z.object({
  product_id: z.number({ message: "Product is required" }).min(1, "Select a product"),
  from_location_id: z.number({ message: "Source branch is required" }).min(1, "Select source"),
  to_location_id: z.number({ message: "Destination branch is required" }).min(1, "Select destination"),
  quantity: z.number({ message: "Quantity is required" })
    .positive("Quantity must be greater than 0")
    .min(0.01, "Minimum transfer amount is 0.01"),
  note: z.string().optional(),
}).refine((data) => data.from_location_id !== data.to_location_id, {
  message: "Source and destination cannot be the same branch",
  path: ["to_location_id"],
});

type TransferFormValues = z.infer<typeof transferSchema>;

// Types
interface Location {
  id: number;
  name: string;
}

interface ProductStock {
  id: number;
  name: string;
  sku: string;
  category: { name: string } | null;
  stock_quantity: number;
  location_id: number;
  location_name: string;
}

export default function InventoryPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  const initialSearch = searchParams.get('inv_search') || "";
  const initialPage = parseInt(searchParams.get('inv_page') || "1", 10);

  const [searchTerm, setSearchTerm] = useState(initialSearch);
  const [currentPage, setCurrentPage] = useState(initialPage);
  const [isTransferModalOpen, setIsTransferModalOpen] = useState(false);
  
  // URL Debounce Sync
  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      const params = new URLSearchParams();
      if (searchTerm) params.set('inv_search', searchTerm);
      params.set('inv_page', currentPage.toString());
      router.replace(`?${params.toString()}`);
    }, 400);
    return () => clearTimeout(delayDebounceFn);
  }, [searchTerm, currentPage, router]);

  // Hooks
  const searchParam = searchTerm ? `&search=${encodeURIComponent(searchTerm)}` : '';
  const { data: inventoryData, isLoading, mutate } = useSWR(
    `/tenant/inventory?per_page=50&page=${currentPage}${searchParam}`,
    fetcher,
    { revalidateOnFocus: false, keepPreviousData: true }
  );

  const { data: locationsData } = useSWR('/tenant/locations', fetcher);

  const products: ProductStock[] = inventoryData?.data || [];
  const locations: Location[] = locationsData?.data || [];

  // Transfer Form Setup
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<TransferFormValues>({
    resolver: zodResolver(transferSchema),
    defaultValues: {
      product_id: 0,
      from_location_id: 0,
      to_location_id: 0,
      quantity: 0,
      note: '',
    }
  });

  const onTransferSubmit = async (data: TransferFormValues) => {
    try {
      await api.post('/tenant/inventory/transfer', data);
      toast.success("Stock transferred successfully");
      setIsTransferModalOpen(false);
      reset();
      mutate(); // Revalidate grid
    } catch (err: unknown) {
      const errorObj = err as Record<string, any>;
      const errorMessage = errorObj.response?.data?.error || errorObj.response?.data?.message || "Transfer failed due to a server error.";
      toast.error(errorMessage);
    }
  };

  const lowStockThreshold = 10;

  return (
    <div className="p-6 max-w-7xl mx-auto text-slate-900">
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <Package className="w-6 h-6 text-indigo-600" />
            Inventory Master
          </h1>
          <p className="text-slate-500 text-sm mt-1">Manage stock across all locations.</p>
        </div>
        
        <div className="flex items-center gap-3 w-full md:w-auto">
          <div className="relative flex-1 md:w-64">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input 
              type="text" 
              placeholder="Search SKU or Name..." 
              value={searchTerm}
              onChange={(e) => { setSearchTerm(e.target.value); setCurrentPage(1); }}
              className="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
            />
          </div>
          <button 
            onClick={() => setIsTransferModalOpen(true)}
            className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors"
          >
            <ArrowRightLeft className="w-4 h-4" />
            Stock Transfer
          </button>
        </div>
      </div>

      {/* High-Density Grid */}
      <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider">
                <th className="px-6 py-4 font-semibold">Product Name</th>
                <th className="px-6 py-4 font-semibold">SKU</th>
                <th className="px-6 py-4 font-semibold">Category</th>
                <th className="px-6 py-4 font-semibold">Branch</th>
                <th className="px-6 py-4 font-semibold text-right">Available Stock</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {isLoading ? (
                // SKELETON LOADER
                Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i} className="animate-pulse">
                    <td className="px-6 py-4">
                      <div className="h-4 bg-slate-200 rounded w-3/4"></div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="h-4 bg-slate-200 rounded w-1/2"></div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="h-4 bg-slate-200 rounded w-2/3"></div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="h-4 bg-slate-200 rounded w-1/2"></div>
                    </td>
                    <td className="px-6 py-4 flex justify-end">
                      <div className="h-4 bg-slate-200 rounded w-12"></div>
                    </td>
                  </tr>
                ))
              ) : products.length === 0 ? (
                // EMPTY STATE
                <tr>
                  <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
                    <div className="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                      <Package className="w-6 h-6 text-slate-400" />
                    </div>
                    <p className="font-medium text-slate-800">No inventory found.</p>
                  </td>
                </tr>
              ) : (
                products.map((item) => (
                  <tr key={`${item.id}-${item.location_id}`} className="hover:bg-slate-50/50 transition-colors">
                    <td className="px-6 py-4 text-sm font-medium text-slate-900">{item.name}</td>
                    <td className="px-6 py-4 text-sm text-slate-500 font-mono">{item.sku}</td>
                    <td className="px-6 py-4 text-sm text-slate-500">{item.category?.name || '-'}</td>
                    <td className="px-6 py-4 text-sm text-slate-500">{item.location_name}</td>
                    <td className="px-6 py-4 text-sm text-right font-medium">
                      {item.stock_quantity <= lowStockThreshold ? (
                        <div className="flex items-center justify-end gap-1.5 text-rose-500 font-bold bg-rose-50 px-2.5 py-1 rounded-md inline-flex border border-rose-100">
                          <AlertTriangle className="w-3.5 h-3.5" />
                          {item.stock_quantity}
                        </div>
                      ) : (
                        <span className="text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-md inline-flex border border-emerald-100">
                          {item.stock_quantity}
                        </span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* STOCK TRANSFER MODAL */}
      {isTransferModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col">
            <div className="flex items-center justify-between p-5 border-b border-slate-100">
              <h2 className="text-lg font-bold text-slate-800 flex items-center gap-2">
                <ArrowRightLeft className="w-5 h-5 text-indigo-600" />
                Transfer Stock
              </h2>
              <button 
                onClick={() => { setIsTransferModalOpen(false); reset(); }}
                className="text-slate-400 hover:text-slate-600 transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit(onTransferSubmit)} className="p-5 flex flex-col gap-4">
              
              {/* Product */}
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-slate-700">Select Product</label>
                <select 
                  {...register("product_id", { valueAsNumber: true })}
                  className={clsx(
                    "w-full px-3 py-2 bg-white border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                    errors.product_id ? "border-rose-500 focus:ring-rose-500" : "border-slate-300"
                  )}
                >
                  <option value="0">-- Choose a Product --</option>
                  {products.map(p => (
                    <option key={p.id} value={p.id}>{p.name} ({p.sku})</option>
                  ))}
                </select>
                {errors.product_id && (
                  <span className="text-xs font-semibold text-rose-500">{errors.product_id.message}</span>
                )}
              </div>

              {/* Source Branch */}
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-slate-700">Source Branch</label>
                <select 
                  {...register("from_location_id", { valueAsNumber: true })}
                  className={clsx(
                    "w-full px-3 py-2 bg-white border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                    errors.from_location_id ? "border-rose-500 focus:ring-rose-500" : "border-slate-300"
                  )}
                >
                  <option value="0">-- From Location --</option>
                  {locations.map((l: any) => (
                    <option key={l.id} value={l.id}>{l.name}</option>
                  ))}
                </select>
                {errors.from_location_id && (
                  <span className="text-xs font-semibold text-rose-500">{errors.from_location_id.message}</span>
                )}
              </div>

              {/* Destination Branch */}
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-slate-700">Destination Branch</label>
                <select 
                  {...register("to_location_id", { valueAsNumber: true })}
                  className={clsx(
                    "w-full px-3 py-2 bg-white border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                    errors.to_location_id ? "border-rose-500 focus:ring-rose-500" : "border-slate-300"
                  )}
                >
                  <option value="0">-- To Location --</option>
                  {locations.map((l: any) => (
                    <option key={l.id} value={l.id}>{l.name}</option>
                  ))}
                </select>
                {errors.to_location_id && (
                  <span className="text-xs font-semibold text-rose-500">{errors.to_location_id.message}</span>
                )}
              </div>

              {/* Quantity */}
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-slate-700">Quantity</label>
                <input 
                  type="number"
                  step="0.0001"
                  {...register("quantity", { valueAsNumber: true })}
                  className={clsx(
                    "w-full px-3 py-2 bg-white border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500",
                    errors.quantity ? "border-rose-500 focus:ring-rose-500" : "border-slate-300"
                  )}
                  placeholder="e.g. 50"
                />
                {errors.quantity && (
                  <span className="text-xs font-semibold text-rose-500">{errors.quantity.message}</span>
                )}
              </div>

              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-slate-700">Note (Optional)</label>
                <input 
                  type="text"
                  {...register("note")}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder="Reason for transfer"
                />
              </div>

              {/* Submit */}
              <div className="mt-4 flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => { setIsTransferModalOpen(false); reset(); }}
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
                  {isSubmitting ? 'Processing...' : 'Execute Transfer'}
                </button>
              </div>

            </form>
          </div>
        </div>
      )}

    </div>
  );
}
