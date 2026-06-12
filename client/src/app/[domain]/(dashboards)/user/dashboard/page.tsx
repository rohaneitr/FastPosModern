'use client';

import React, { useState } from 'react';
import useSWR from 'swr';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';

interface TimelineItem {
    date: string;
    revenue: number;
    cogs: number;
    profit: number;
}

interface AnalyticsData {
    kpis: {
        gross_revenue: number;
        revenue_variance: number;
        cogs: number;
        cogs_variance: number;
        gross_profit: number;
        profit_variance: number;
        net_margin: number;
        margin_variance: number;
    };
    timeline: TimelineItem[];
}

const fetcher = (url: string) => fetch(url, {
    headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}` // Basic fallback if needed, assume axios/fetch interceptors typically handle it
    }
}).then((res) => res.json());

export default function DashboardPage() {
    const [range, setRange] = useState('30d');
    const { data, error, isLoading } = useSWR<AnalyticsData>(`/api/v1/analytics/overview?range=${range}`, fetcher);

    const formatCurrency = (val: number) => {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
    };

    const renderVariance = (val: number) => {
        const isPositive = val >= 0;
        const color = isPositive ? 'text-emerald-500 bg-emerald-100/10' : 'text-rose-500 bg-rose-100/10';
        return (
            <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${color}`}>
                {isPositive ? '+' : ''}{val.toFixed(2)}%
            </span>
        );
    };

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900 p-8 text-gray-900 dark:text-white transition-colors duration-300">
            {/* Header section */}
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
                <div>
                    <h1 className="text-3xl font-black tracking-tight">Executive Dashboard</h1>
                    <p className="text-gray-500 dark:text-gray-400 mt-1">Global performance metrics & intelligence.</p>
                </div>
                <div className="mt-4 sm:mt-0">
                    <select
                        data-testid="timeline-filter"
                        value={range}
                        onChange={(e) => setRange(e.target.value)}
                        className="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full p-2.5 shadow-sm"
                    >
                        <option value="today">Today</option>
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                        <option value="ytd">This Fiscal Year</option>
                    </select>
                </div>
            </div>

            {/* KPI Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                {isLoading ? (
                    Array(4).fill(0).map((_, i) => (
                        <div key={i} className="h-32 rounded-2xl bg-gray-200 dark:bg-gray-800 animate-pulse skeleton-pulse"></div>
                    ))
                ) : data ? (
                    <>
                        <div className="backdrop-blur-xl bg-white/60 dark:bg-gray-900/40 border border-gray-200 dark:border-white/10 shadow-xl rounded-2xl p-6 transition-all duration-300 hover:scale-[1.02]">
                            <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">Gross Revenue</h3>
                            <div className="mt-2 flex items-baseline gap-2">
                                <p className="text-3xl font-bold">{formatCurrency(data.kpis.gross_revenue)}</p>
                            </div>
                            <div className="mt-4">
                                {renderVariance(data.kpis.revenue_variance)}
                                <span className="ml-2 text-xs text-gray-500">vs previous period</span>
                            </div>
                        </div>

                        <div className="backdrop-blur-xl bg-white/60 dark:bg-gray-900/40 border border-gray-200 dark:border-white/10 shadow-xl rounded-2xl p-6 transition-all duration-300 hover:scale-[1.02]">
                            <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">COGS</h3>
                            <div className="mt-2 flex items-baseline gap-2">
                                <p className="text-3xl font-bold">{formatCurrency(data.kpis.cogs)}</p>
                            </div>
                            <div className="mt-4">
                                {/* For COGS, negative variance might be good, but we render standard for now */}
                                {renderVariance(data.kpis.cogs_variance)}
                                <span className="ml-2 text-xs text-gray-500">vs previous period</span>
                            </div>
                        </div>

                        <div className="backdrop-blur-xl bg-white/60 dark:bg-gray-900/40 border border-gray-200 dark:border-white/10 shadow-xl rounded-2xl p-6 transition-all duration-300 hover:scale-[1.02]">
                            <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">Gross Profit</h3>
                            <div className="mt-2 flex items-baseline gap-2">
                                <p className="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{formatCurrency(data.kpis.gross_profit)}</p>
                            </div>
                            <div className="mt-4">
                                {renderVariance(data.kpis.profit_variance)}
                                <span className="ml-2 text-xs text-gray-500">vs previous period</span>
                            </div>
                        </div>

                        <div className="backdrop-blur-xl bg-white/60 dark:bg-gray-900/40 border border-gray-200 dark:border-white/10 shadow-xl rounded-2xl p-6 transition-all duration-300 hover:scale-[1.02]">
                            <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">Net Margin</h3>
                            <div className="mt-2 flex items-baseline gap-2">
                                <p className="text-3xl font-bold">{data.kpis.net_margin.toFixed(2)}%</p>
                            </div>
                            <div className="mt-4">
                                {renderVariance(data.kpis.margin_variance)}
                                <span className="ml-2 text-xs text-gray-500">vs previous period</span>
                            </div>
                        </div>
                    </>
                ) : (
                    <div className="col-span-4 text-center text-rose-500">Failed to load KPIs.</div>
                )}
            </div>

            {/* Performance Chart */}
            <div className="backdrop-blur-xl bg-white/60 dark:bg-gray-900/40 border border-gray-200 dark:border-white/10 shadow-2xl rounded-2xl p-6 h-[400px]">
                <h3 className="text-lg font-bold mb-4">Rolling Performance Matrix</h3>
                
                {isLoading ? (
                    <div className="w-full h-full bg-gray-200 dark:bg-gray-800 animate-pulse rounded skeleton-pulse flex items-center justify-center">
                        <span className="text-gray-400">Loading Matrix...</span>
                    </div>
                ) : data && data.timeline ? (
                    <div className="w-full h-[300px]">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={data.timeline} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
                                <defs>
                                    <linearGradient id="colorRevenue" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#4F46E5" stopOpacity={0.3}/> {/* Indigo-600 */}
                                        <stop offset="95%" stopColor="#4F46E5" stopOpacity={0}/>
                                    </linearGradient>
                                    <linearGradient id="colorProfit" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#10B981" stopOpacity={0.3}/> {/* Emerald-500 */}
                                        <stop offset="95%" stopColor="#10B981" stopOpacity={0}/>
                                    </linearGradient>
                                    <linearGradient id="colorCogs" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#F43F5E" stopOpacity={0.3}/> {/* Rose-500 */}
                                        <stop offset="95%" stopColor="#F43F5E" stopOpacity={0}/>
                                    </linearGradient>
                                </defs>
                                <XAxis dataKey="date" stroke="#888888" fontSize={12} tickLine={false} axisLine={false} />
                                <YAxis stroke="#888888" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `$${value}`} />
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#374151" opacity={0.2} />
                                <Tooltip 
                                    contentStyle={{ backgroundColor: 'rgba(17, 24, 39, 0.9)', borderRadius: '8px', border: 'none', color: '#fff' }}
                                    itemStyle={{ fontFamily: 'monospace' }}
                                    formatter={(value: any, name: any) => [formatCurrency(Number(value)), name]}
                                />
                                <Legend />
                                <Area type="monotone" dataKey="revenue" name="Gross Revenue" stroke="#4F46E5" strokeWidth={2} fillOpacity={1} fill="url(#colorRevenue)" />
                                <Area type="monotone" dataKey="cogs" name="COGS" stroke="#F43F5E" strokeWidth={2} fillOpacity={1} fill="url(#colorCogs)" />
                                <Area type="monotone" dataKey="profit" name="Gross Profit" stroke="#10B981" strokeWidth={2} fillOpacity={1} fill="url(#colorProfit)" />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                ) : (
                    <div className="w-full h-full flex items-center justify-center text-rose-500">Failed to render chart.</div>
                )}
            </div>
        </div>
    );
}
