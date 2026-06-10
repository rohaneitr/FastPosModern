"use client";

import React, { useState, useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import useSWR from "swr";
import { Search, Loader2, Download, BookOpen, AlertCircle } from "lucide-react";
import toast from "react-hot-toast";
import api from "@/lib/api";

const fetcher = (url: string) => api.get(url).then((res) => res.data);

export default function LedgerPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  const initialSearch = searchParams.get('ledger_search') || "";
  const initialPage = parseInt(searchParams.get('ledger_page') || "1", 10);

  const [searchTerm, setSearchTerm] = useState(initialSearch);
  const [currentPage, setCurrentPage] = useState(initialPage);
  const [isExporting, setIsExporting] = useState(false);

  // URL Debounce Sync
  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      const params = new URLSearchParams(searchParams.toString());
      if (searchTerm) {
        params.set('ledger_search', searchTerm);
      } else {
        params.delete('ledger_search');
      }
      params.set('ledger_page', currentPage.toString());
      router.replace(`?${params.toString()}`);
    }, 400);
    return () => clearTimeout(delayDebounceFn);
  }, [searchTerm, currentPage, router, searchParams]);

  // Hooks
  const searchParam = searchTerm ? `&search=${encodeURIComponent(searchTerm)}` : '';
  const { data: ledgerData, isLoading } = useSWR(
    `/tenant/reports/ledger?per_page=50&page=${currentPage}${searchParam}`,
    fetcher,
    { revalidateOnFocus: false, keepPreviousData: true }
  );

  const entries = ledgerData?.data || [];

  // Zero-Trust Math: Calculate running balance if not provided by backend.
  // Assumes backend provides a starting balance for the page, but we'll calculate
  // purely on the page data for visual demonstration if absent.
  let currentBalance = ledgerData?.meta?.starting_balance || 0;

  const exportCSV = async () => {
    try {
      setIsExporting(true);
      // In a real scenario, this would hit /tenant/reports/ledger/export
      // For now, we simulate client-side blob generation
      const csvContent = "Date,Account,Reference,Type,Debit,Credit,Balance\n" + entries.map((e: any) => {
        return `${e.date},${e.account},${e.reference_no},${e.type},${e.debit},${e.credit},${e.balance}`;
      }).join("\n");

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `Ledger_Report_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      toast.success("Ledger exported successfully");
    } catch (err) {
      toast.error("Failed to export Ledger.");
    } finally {
      setIsExporting(false);
    }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto text-slate-900">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <BookOpen className="w-6 h-6 text-indigo-600" />
            General Ledger
          </h1>
          <p className="text-slate-500 text-sm mt-1">Double-entry accounting log with strictly balanced transactions.</p>
        </div>
        
        <div className="flex items-center gap-3 w-full md:w-auto">
          <div className="relative flex-1 md:w-64">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input 
              type="text" 
              placeholder="Search reference or account..." 
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
            {isExporting ? 'Processing...' : 'Download CSV'}
          </button>
        </div>
      </div>

      <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider">
                <th className="px-6 py-4 font-semibold">Date</th>
                <th className="px-6 py-4 font-semibold">Account</th>
                <th className="px-6 py-4 font-semibold">Reference</th>
                <th className="px-6 py-4 font-semibold text-right">Debit</th>
                <th className="px-6 py-4 font-semibold text-right">Credit</th>
                <th className="px-6 py-4 font-semibold text-right">Balance</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {isLoading ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i} className="animate-pulse">
                    <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-24"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-32"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-24"></div></td>
                    <td className="px-6 py-4 flex justify-end"><div className="h-4 bg-slate-200 rounded w-16"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-16 ml-auto"></div></td>
                    <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-20 ml-auto"></div></td>
                  </tr>
                ))
              ) : entries.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                    <div className="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                      <AlertCircle className="w-6 h-6 text-slate-400" />
                    </div>
                    <p className="font-medium text-slate-800">No ledger entries found.</p>
                  </td>
                </tr>
              ) : (
                entries.map((entry: any, index: number) => {
                  const debit = Number(entry.debit) || 0;
                  const credit = Number(entry.credit) || 0;
                  // If backend provides running balance, use it. Otherwise, calculate visually.
                  const entryBalance = entry.balance !== undefined ? Number(entry.balance) : (currentBalance += debit - credit);
                  
                  return (
                    <tr key={entry.id || index} className="hover:bg-slate-50/50 transition-colors">
                      <td className="px-6 py-4 text-sm text-slate-500 whitespace-nowrap">{new Date(entry.created_at || entry.date).toLocaleString()}</td>
                      <td className="px-6 py-4 text-sm font-medium text-slate-900">{entry.account_name || entry.account}</td>
                      <td className="px-6 py-4 text-sm font-mono text-slate-500">{entry.reference_no}</td>
                      <td className="px-6 py-4 text-sm text-right font-medium text-emerald-600">
                        {debit > 0 ? `$${debit.toFixed(2)}` : '-'}
                      </td>
                      <td className="px-6 py-4 text-sm text-right font-medium text-rose-600">
                        {credit > 0 ? `$${credit.toFixed(2)}` : '-'}
                      </td>
                      <td className="px-6 py-4 text-sm text-right font-bold text-slate-800">
                        ${entryBalance.toFixed(2)}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
