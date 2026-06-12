"use client";

import React, { useState, useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import useSWR from "swr";
import dynamic from "next/dynamic";
import { DollarSign, TrendingUp, AlertCircle, Calendar, BarChart3, Loader2 } from "lucide-react";
import api from "@/lib/api";

const fetcher = (url: string) => api.get(url).then((res) => res.data);

const PNLChart = dynamic(() => import("./PNLChart"), {
  ssr: false,
  loading: () => (
    <div className="w-full h-full flex flex-col items-center justify-center bg-slate-50/50 rounded-xl animate-pulse text-slate-400">
      <Loader2 className="w-10 h-10 animate-spin mb-3 text-indigo-300" />
      <span className="font-semibold text-sm">Loading visualizer...</span>
    </div>
  ),
});

// Default date range: Last 30 Days
const defaultEnd = new Date().toISOString().split('T')[0];
const thirtyDaysAgo = new Date();
thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
const defaultStart = thirtyDaysAgo.toISOString().split('T')[0];

export default function ProfitLossPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  const initialStart = searchParams.get('start_date') || defaultStart;
  const initialEnd = searchParams.get('end_date') || defaultEnd;
  const initialMethod = searchParams.get('method') || 'accrual';

  const [startDate, setStartDate] = useState(initialStart);
  const [endDate, setEndDate] = useState(initialEnd);
  const [accountingMethod, setAccountingMethod] = useState(initialMethod);

  // URL Debounce Sync
  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      const params = new URLSearchParams(searchParams.toString());
      params.set('start_date', startDate);
      params.set('end_date', endDate);
      params.set('method', accountingMethod);
      router.replace(`?${params.toString()}`);
    }, 400);
    return () => clearTimeout(delayDebounceFn);
  }, [startDate, endDate, accountingMethod, router, searchParams]);

  // Hook targeting FinancialReportController@profitAndLoss
  const { data, isLoading, error } = useSWR(
    `/tenant/reports/profit-loss?start_date=${startDate}&end_date=${endDate}&accounting_method=${accountingMethod}`,
    fetcher,
    { revalidateOnFocus: false }
  );

  const metrics = data?.metrics || { revenue: 0, cogs: 0, gross_profit: 0, refunds: 0 };
  const chartData = data?.chart || [];

  return (
    <div className="max-w-7xl mx-auto p-6 md:p-10 bg-slate-50 min-h-screen font-sans">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-800 tracking-tight flex items-center gap-2">
            <BarChart3 className="w-8 h-8 text-indigo-600" />
            Profit & Loss (P&L)
          </h1>
          <p className="text-slate-500 mt-1">Real-time Gross Profit calculated dynamically via Weighted Average Cost (WAC).</p>
        </div>

        <div className="flex flex-col md:flex-row items-center gap-3 w-full md:w-auto">
          {/* Accounting Method Toggle */}
          <div className="flex bg-slate-200 p-1 rounded-xl shadow-sm border border-slate-200 w-full md:w-auto">
            <button
              onClick={() => setAccountingMethod('accrual')}
              className={`flex-1 md:flex-none px-4 py-1.5 text-sm font-bold rounded-lg transition-colors ${
                accountingMethod === 'accrual' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'
              }`}
            >
              Accrual Basis
            </button>
            <button
              onClick={() => setAccountingMethod('cash')}
              className={`flex-1 md:flex-none px-4 py-1.5 text-sm font-bold rounded-lg transition-colors ${
                accountingMethod === 'cash' ? 'bg-white text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'
              }`}
            >
              Cash Basis
            </button>
          </div>

          <div className="flex items-center gap-3 w-full md:w-auto bg-white p-2 rounded-xl shadow-sm border border-slate-200">
            <Calendar className="w-5 h-5 text-slate-400 ml-2" />
            <input 
              type="date" 
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="px-2 py-1 outline-none text-sm font-medium text-slate-700 bg-transparent cursor-pointer"
            />
            <span className="text-slate-300">-</span>
            <input 
              type="date" 
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="px-2 py-1 outline-none text-sm font-medium text-slate-700 bg-transparent cursor-pointer"
            />
          </div>
        </div>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          {[1, 2, 3].map(i => (
            <div key={i} className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 animate-pulse">
              <div className="h-4 bg-slate-200 rounded w-1/3 mb-4"></div>
              <div className="h-8 bg-slate-200 rounded w-1/2"></div>
            </div>
          ))}
        </div>
      ) : error ? (
        <div className="bg-rose-50 text-rose-600 p-6 rounded-2xl border border-rose-100 flex items-center gap-3 font-bold mb-8">
          <AlertCircle className="w-6 h-6" />
          Failed to load financial data. Ensure the backend endpoint /tenant/reports/profit-loss is available.
        </div>
      ) : (
        <>
          {/* P&L Metric Cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col relative overflow-hidden">
              <div className="absolute top-0 right-0 p-4 opacity-10"><DollarSign className="w-16 h-16 text-emerald-500" /></div>
              <span className="text-slate-500 text-sm font-semibold flex items-center gap-2 mb-2">Net Revenue</span>
              <span className="text-4xl font-black text-slate-800 tracking-tight">${Number(metrics.revenue).toFixed(2)}</span>
              {metrics.refunds > 0 && <span className="text-xs text-rose-500 font-bold mt-2">Includes ${Number(metrics.refunds).toFixed(2)} in Refunds</span>}
            </div>
            
            <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col relative overflow-hidden">
              <div className="absolute top-0 right-0 p-4 opacity-10"><TrendingUp className="w-16 h-16 text-rose-500" /></div>
              <span className="text-slate-500 text-sm font-semibold flex items-center gap-2 mb-2">Cost of Goods Sold (COGS)</span>
              <span className="text-4xl font-black text-rose-600 tracking-tight">${Number(metrics.cogs).toFixed(2)}</span>
              <span className="text-xs text-slate-400 font-medium mt-2">Calculated via historical WAC</span>
            </div>

            <div className="bg-indigo-600 p-6 rounded-2xl shadow-lg shadow-indigo-200 flex flex-col relative overflow-hidden">
              <div className="absolute top-0 right-0 p-4 opacity-10"><BarChart3 className="w-16 h-16 text-white" /></div>
              <span className="text-indigo-100 text-sm font-semibold flex items-center gap-2 mb-2">Gross Profit</span>
              <span className="text-4xl font-black text-white tracking-tight">${Number(metrics.gross_profit).toFixed(2)}</span>
              <span className="text-xs text-indigo-200 font-medium mt-2">Margin: {metrics.revenue > 0 ? ((metrics.gross_profit / metrics.revenue) * 100).toFixed(1) : '0.0'}%</span>
            </div>
          </div>

          {/* Dynamic Area Chart */}
          <div className="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-slate-100 h-[500px]">
            <h3 className="text-lg font-bold text-slate-800 mb-6">Revenue vs COGS (Timeline)</h3>
            {chartData.length === 0 ? (
              <div className="w-full h-full flex flex-col items-center justify-center text-slate-400">
                <BarChart3 className="w-12 h-12 mb-2 opacity-50" />
                <p className="font-semibold">No data available for this date range.</p>
              </div>
            ) : (
              <PNLChart chartData={chartData} />
            )}
          </div>
        </>
      )}
    </div>
  );
}
