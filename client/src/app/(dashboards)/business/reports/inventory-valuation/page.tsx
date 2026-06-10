"use client";

import React from "react";
import useSWR from "swr";
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import { Package, TrendingUp, AlertCircle, Loader2 } from "lucide-react";
import api from "@/lib/api";

const fetcher = (url: string) => api.get(url).then((res) => res.data);

export default function InventoryValuationPage() {
  const { data, isLoading, error } = useSWR('/tenant/reports/valuation', fetcher);

  return (
    <div className="max-w-7xl mx-auto p-6 md:p-10 bg-slate-50 min-h-screen font-sans">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-slate-800 tracking-tight flex items-center gap-2">
          <Package className="w-8 h-8 text-indigo-600" />
          Inventory Valuation
        </h1>
        <p className="text-slate-500 mt-1">Real-time asset value calculated dynamically via Weighted Average Cost (WAC).</p>
      </div>

      {isLoading ? (
        <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 animate-pulse h-[400px] flex items-center justify-center">
          <Loader2 className="w-10 h-10 animate-spin text-indigo-300" />
        </div>
      ) : error ? (
        <div className="bg-rose-50 text-rose-600 p-6 rounded-2xl border border-rose-100 flex items-center gap-3 font-bold">
          <AlertCircle className="w-6 h-6" />
          Failed to load valuation data.
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-1 flex flex-col gap-6">
            <div className="bg-indigo-600 p-6 rounded-3xl shadow-lg shadow-indigo-200 flex flex-col relative overflow-hidden">
              <div className="absolute -top-4 -right-4 p-4 opacity-10">
                <TrendingUp className="w-32 h-32 text-white" />
              </div>
              <span className="text-indigo-100 text-sm font-semibold flex items-center gap-2 mb-2 relative z-10">
                Total Capital Locked
              </span>
              <span className="text-5xl font-black text-white tracking-tight relative z-10">
                ${Number(data?.total_capital_locked || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
              </span>
              <span className="text-xs text-indigo-200 font-medium mt-4 relative z-10">
                Calculated across all tracked products using active WAC and unreserved stock.
              </span>
            </div>
            
            <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
              <h3 className="font-bold text-slate-800 mb-2">Accounting Compliance</h3>
              <p className="text-sm text-slate-500">
                This valuation assumes FIFO stock consumption for WAC derivation.
                Divergences between physical stock and digital records may artificially inflate this number.
                Always perform physical audits (Blind Counts) to ensure capital accuracy.
              </p>
            </div>
          </div>

          <div className="lg:col-span-2 bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-slate-100 h-[500px] flex flex-col">
            <h3 className="text-lg font-bold text-slate-800 mb-6">Valuation Trend (Last 90 Days)</h3>
            <div className="flex-1 w-full min-h-0">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data?.trend || []} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                  <defs>
                    <linearGradient id="colorVal" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#4f46e5" stopOpacity={0.3}/>
                      <stop offset="95%" stopColor="#4f46e5" stopOpacity={0}/>
                    </linearGradient>
                  </defs>
                  <XAxis 
                    dataKey="date" 
                    stroke="#94a3b8" 
                    fontSize={12} 
                    tickLine={false} 
                    axisLine={false}
                    tickFormatter={(val) => {
                      const d = new Date(val);
                      return `${d.getMonth()+1}/${d.getDate()}`;
                    }}
                  />
                  <YAxis 
                    stroke="#94a3b8" 
                    fontSize={12} 
                    tickLine={false} 
                    axisLine={false} 
                    tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`} 
                  />
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                  <Tooltip 
                    contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)' }}
                    itemStyle={{ fontWeight: 'bold', color: '#4f46e5' }}
                    labelStyle={{ color: '#64748b', fontWeight: 'bold', marginBottom: '4px' }}
                    formatter={(value: any) => {
                      if (typeof value === 'number') {
                        return [`$${value.toFixed(2)}`, 'Valuation'];
                      }
                      return [value, 'Valuation'];
                    }}
                  />
                  <Area 
                    type="monotone" 
                    dataKey="value" 
                    name="Valuation" 
                    stroke="#4f46e5" 
                    fillOpacity={1} 
                    fill="url(#colorVal)" 
                    strokeWidth={3} 
                  />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
