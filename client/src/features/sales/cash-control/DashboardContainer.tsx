'use client';

import React, { useState } from 'react';
import useSWR from 'swr';
import { ComposedChart, Line, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { useAuth } from '../../../contexts/AuthContext';

interface ModuleMetrics { 
    revenue: number; 
    volume: number; 
    color: string; 
}

type AnalyticsResponse = { 
    global: { 
        revenue: number; 
        profit: number; 
    }; 
    modules: Record<string, ModuleMetrics | null>;
}

const fetcher = (url: string) => fetch(url, {
    headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`
    }
}).then((res) => res.json());

export default function DashboardContainer() {
    const { user } = useAuth();
    const activeModules = (user as any)?.business?.active_modules || [];
    const [viewMode, setViewMode] = useState<string>('global');

    const { data, error, isLoading } = useSWR<AnalyticsResponse>('/api/v1/analytics/consolidated-overview', fetcher);

    if (isLoading) {
        return <div className="p-8 text-white">Loading Enterprise Analytics Matrix...</div>;
    }

    if (error || !data) {
        return <div className="p-8 text-rose-500">Analytics Engine Failure. Circuit Breaker Engaged.</div>;
    }

    // Format metrics into Recharts structure
    const activeModulesList = Array.isArray(activeModules) ? activeModules : [];
    
    const chartData = activeModulesList.map((slug: string) => {
        const moduleData = data.modules[slug];
        if (!moduleData) return null;

        return {
            name: slug.toUpperCase(),
            revenue: moduleData.revenue,
            volume: moduleData.volume,
            fillColor: moduleData.color
        };
    }).filter(Boolean);

    // If 'global' is selected, aggregate total. 
    // If specific module is selected, filter chartData.
    const displayData = viewMode === 'global' ? chartData : chartData.filter((d: any) => d?.name === viewMode.toUpperCase());

    return (
        <div className="min-h-screen bg-gray-900 text-white p-8">
            <h1 className="text-3xl font-black tracking-tight mb-8">Consolidated Executive View</h1>

            {/* Dynamic Filter Pills */}
            <div className="flex gap-4 mb-8">
                <button 
                    onClick={() => setViewMode('global')}
                    className={`px-4 py-2 rounded-full font-medium transition-all ${viewMode === 'global' ? 'bg-indigo-600' : 'bg-gray-800 hover:bg-gray-700'}`}
                >
                    Global Overview
                </button>
                {activeModulesList.map((slug: string) => (
                    <button 
                        key={slug}
                        onClick={() => setViewMode(slug)}
                        className={`px-4 py-2 rounded-full font-medium transition-all capitalize ${viewMode === slug ? 'bg-indigo-600' : 'bg-gray-800 hover:bg-gray-700'}`}
                    >
                        {slug.replace('-', ' ')} Matrix
                    </button>
                ))}
            </div>

            {/* Global Aggregation Cards */}
            <div className="grid grid-cols-2 gap-6 mb-8">
                <div className="backdrop-blur-xl bg-gray-800/40 border border-white/10 rounded-2xl p-6">
                    <h3 className="text-sm font-medium text-gray-400 uppercase tracking-widest">Global Gross Revenue</h3>
                    <p data-testid="global-revenue" className="text-4xl font-bold mt-2">${data.global.revenue.toLocaleString()}</p>
                </div>
                <div className="backdrop-blur-xl bg-gray-800/40 border border-white/10 rounded-2xl p-6">
                    <h3 className="text-sm font-medium text-gray-400 uppercase tracking-widest">Global Net Profit</h3>
                    <p className="text-4xl font-bold text-emerald-400 mt-2">${data.global.profit.toLocaleString()}</p>
                </div>
            </div>

            {/* Matrix Visualization */}
            <div className="backdrop-blur-xl bg-gray-800/40 border border-white/10 rounded-2xl p-6 h-[500px]">
                <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart data={displayData} margin={{ top: 20, right: 20, bottom: 20, left: 20 }}>
                        <CartesianGrid stroke="#374151" strokeDasharray="3 3" vertical={false} />
                        <XAxis dataKey="name" stroke="#9CA3AF" />
                        <YAxis yAxisId="left" stroke="#9CA3AF" tickFormatter={(value) => `$${value}`} />
                        <YAxis yAxisId="right" orientation="right" stroke="#9CA3AF" />
                        <Tooltip 
                            contentStyle={{ backgroundColor: 'rgba(17, 24, 39, 0.9)', border: 'none', borderRadius: '8px' }}
                            itemStyle={{ fontFamily: 'monospace' }}
                        />
                        <Legend />
                        <Bar yAxisId="right" dataKey="volume" name="Transaction Volume" barSize={40} fill="#10B981" />
                        <Line yAxisId="left" type="monotone" dataKey="revenue" name="Module Revenue" stroke="#4F46E5" strokeWidth={3} />
                    </ComposedChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
