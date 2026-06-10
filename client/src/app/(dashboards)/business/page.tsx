"use client";

import React, { useState } from "react";
import useSWR from "swr";
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import { DollarSign, TrendingUp, Package, AlertCircle, ShoppingCart, PlusCircle, Bell } from "lucide-react";
import { CommandPalette } from "@/components/CommandPalette";
import api from "@/lib/api";

const fetcher = (url: string) => api.get(url).then((res) => res.data);

// Subcomponent: Skeleton Cards
const MetricSkeleton = () => (
  <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 animate-pulse">
    <div className="h-4 bg-slate-200 rounded w-1/3 mb-4"></div>
    <div className="h-8 bg-slate-200 rounded w-1/2"></div>
  </div>
);

// Subcomponent: Notifications
function TopBar() {
  const { data, error } = useSWR('/tenant/notifications', fetcher, { refreshInterval: 10000 });
  
  const notifications = data?.data || [];
  const unread = notifications.filter((n: any) => !n.read_at).length;

  return (
    <div className="flex justify-end items-center mb-8">
      <div className="relative cursor-pointer hover:bg-slate-100 p-2 rounded-full transition-colors">
        <Bell className="w-6 h-6 text-slate-600" />
        {unread > 0 && (
          <span className="absolute top-1 right-1 w-3 h-3 bg-rose-500 rounded-full border-2 border-slate-50"></span>
        )}
      </div>
      {/* Could map through notifications here if a dropdown was built */}
    </div>
  );
}

export default function BusinessDashboard() {
  const { data, error, isLoading } = useSWR('/tenant/dashboard/metrics', fetcher);

  const metrics = data?.metrics || { total_sales: 0, total_cost: 0, products_count: 0, low_stock: 0 };
  const chartData = data?.chart || [];

  const isEmpty = !isLoading && chartData.length === 0 && metrics.products_count === 0;

  return (
    <div className="max-w-7xl mx-auto p-6 md:p-10 bg-slate-50 min-h-screen font-sans">
      <CommandPalette />
      
      <TopBar />

      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-800 tracking-tight">Business Overview</h1>
          <p className="text-slate-500 mt-1">Press <kbd className="bg-slate-200 px-1.5 py-0.5 rounded text-xs font-mono">⌘K</kbd> to open the global command palette.</p>
        </div>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <MetricSkeleton /><MetricSkeleton /><MetricSkeleton /><MetricSkeleton />
        </div>
      ) : isEmpty ? (
        // Zero-Trust Empty State
        <div className="bg-white rounded-3xl p-12 text-center shadow-sm border border-slate-100 flex flex-col items-center justify-center min-h-[400px]">
          <div className="w-20 h-20 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mb-6">
            <Package className="w-10 h-10" />
          </div>
          <h2 className="text-2xl font-bold text-slate-800 mb-2">Welcome to FastPOS!</h2>
          <p className="text-slate-500 max-w-md mx-auto mb-8">
            Your enterprise system is ready. Start by adding your first product to the inventory to unlock sales and analytics.
          </p>
          <button className="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-bold transition-colors flex items-center gap-2 shadow-lg shadow-indigo-200">
            <PlusCircle className="w-5 h-5" />
            Add First Product
          </button>
        </div>
      ) : (
        <>
          {/* Metric Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
              <span className="text-slate-500 text-sm font-semibold flex items-center gap-2 mb-2">
                <DollarSign className="w-4 h-4 text-emerald-500" /> Total Revenue
              </span>
              <span className="text-3xl font-bold text-slate-800">${metrics.total_sales.toFixed(2)}</span>
            </div>
            
            <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
              <span className="text-slate-500 text-sm font-semibold flex items-center gap-2 mb-2">
                <TrendingUp className="w-4 h-4 text-indigo-500" /> Cost of Goods
              </span>
              <span className="text-3xl font-bold text-slate-800">${metrics.total_cost.toFixed(2)}</span>
            </div>

            <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
              <span className="text-slate-500 text-sm font-semibold flex items-center gap-2 mb-2">
                <ShoppingCart className="w-4 h-4 text-blue-500" /> Total Products
              </span>
              <span className="text-3xl font-bold text-slate-800">{metrics.products_count}</span>
            </div>

            <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
              <span className="text-slate-500 text-sm font-semibold flex items-center gap-2 mb-2">
                <AlertCircle className="w-4 h-4 text-rose-500" /> Low Stock Alerts
              </span>
              <span className="text-3xl font-bold text-rose-600">{metrics.low_stock}</span>
            </div>
          </div>

          {/* Dynamic Chart */}
          <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 h-[400px]">
            <h3 className="text-lg font-bold text-slate-800 mb-6">Sales vs Cost (Profit Margin)</h3>
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorSales" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#10b981" stopOpacity={0.3}/>
                    <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                  </linearGradient>
                  <linearGradient id="colorCost" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3}/>
                    <stop offset="95%" stopColor="#6366f1" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <XAxis dataKey="date" stroke="#94a3b8" fontSize={12} tickLine={false} axisLine={false} />
                <YAxis stroke="#94a3b8" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `$${value}`} />
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f1f5f9" />
                <Tooltip 
                  contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }}
                  itemStyle={{ fontWeight: 'bold' }}
                />
                <Area type="monotone" dataKey="sales" stroke="#10b981" fillOpacity={1} fill="url(#colorSales)" strokeWidth={3} />
                <Area type="monotone" dataKey="cost" stroke="#6366f1" fillOpacity={1} fill="url(#colorCost)" strokeWidth={3} />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </>
      )}
    </div>
  );
}
