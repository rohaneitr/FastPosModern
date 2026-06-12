'use client';

import React, { useEffect, useState } from 'react';
import api from '@/lib/api';
import { Layers, AlertTriangle, Package, Calendar } from 'lucide-react';

interface InventoryLayer {
    id: number;
    product_name: string;
    sku: string;
    product_id: number;
    original_qty: string;
    remaining_qty: string;
    unit_cost: string;
    created_at: string;
}

export default function InventoryLayersDashboard() {
    const [layersMap, setLayersMap] = useState<Record<string, InventoryLayer[]>>({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        fetchLayers();
    }, []);

    const fetchLayers = async () => {
        try {
            const { data } = await api.get('/api/v1/inventory/layers');
            setLayersMap(data);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to load inventory layers');
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <div className="flex h-64 items-center justify-center">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col items-center justify-center h-64 bg-red-500/10 rounded-2xl border border-red-500/20 p-8 backdrop-blur-md">
                <AlertTriangle className="h-12 w-12 text-red-500 mb-4" />
                <h3 className="text-xl font-semibold text-red-400">Layer Retrieval Failed</h3>
                <p className="text-red-300 mt-2">{error}</p>
            </div>
        );
    }

    const formatCurrency = (amount: string) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 4,
            maximumFractionDigits: 4,
        }).format(parseFloat(amount));
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    return (
        <div className="space-y-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div>
                <h1 className="text-3xl font-bold tracking-tight text-white flex items-center gap-3">
                    <Layers className="h-8 w-8 text-primary" />
                    FIFO Cost Layers
                </h1>
                <p className="mt-2 text-slate-400">
                    Real-time visualization of granular inventory valuation and dynamic cost bases.
                </p>
            </div>

            {Object.keys(layersMap).length === 0 ? (
                <div className="bg-slate-800/40 border border-slate-700 rounded-3xl p-12 text-center backdrop-blur-xl">
                    <Package className="h-16 w-16 text-slate-500 mx-auto mb-4 opacity-50" />
                    <h3 className="text-xl font-medium text-slate-300">No Active Cost Layers</h3>
                    <p className="text-slate-400 mt-2">All products currently have a zero balance.</p>
                </div>
            ) : (
                <div className="space-y-6">
                    {Object.entries(layersMap).map(([productName, layers]) => (
                        <div key={productName} className="bg-slate-900/50 border border-slate-800 rounded-2xl overflow-hidden backdrop-blur-md shadow-2xl transition-all duration-300 hover:border-slate-700">
                            <div className="bg-slate-800/50 px-6 py-4 border-b border-slate-800 flex justify-between items-center">
                                <div className="flex items-center gap-4">
                                    <div className="h-10 w-10 bg-primary/10 rounded-full flex items-center justify-center border border-primary/20">
                                        <Package className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-semibold text-white">{productName}</h3>
                                        <p className="text-sm text-slate-400">SKU: {layers[0]?.sku}</p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm text-slate-400">Active Layers</p>
                                    <p className="text-lg font-mono font-medium text-white">{layers.length}</p>
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="bg-slate-800/30 text-slate-400 text-xs uppercase tracking-wider">
                                            <th className="px-6 py-3 font-medium">Layer Origination</th>
                                            <th className="px-6 py-3 font-medium">Original Qty</th>
                                            <th className="px-6 py-3 font-medium">Remaining Qty</th>
                                            <th className="px-6 py-3 font-medium text-right">Unit Cost Basis</th>
                                            <th className="px-6 py-3 font-medium text-right">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-800">
                                        {layers.map((layer) => {
                                            const isNegative = parseFloat(layer.remaining_qty) < 0;
                                            
                                            return (
                                                <tr key={layer.id} className={`transition-colors hover:bg-slate-800/30 ${isNegative ? 'bg-red-900/10' : ''}`}>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-300 flex items-center gap-2">
                                                        <Calendar className="h-4 w-4 text-slate-500" />
                                                        {formatDate(layer.created_at)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-400 font-mono">
                                                        {parseFloat(layer.original_qty).toFixed(4)}
                                                    </td>
                                                    <td className={`px-6 py-4 whitespace-nowrap text-sm font-mono font-medium ${isNegative ? 'text-red-400' : 'text-white'}`}>
                                                        {parseFloat(layer.remaining_qty).toFixed(4)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-300 font-mono text-right">
                                                        {formatCurrency(layer.unit_cost)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right">
                                                        {isNegative ? (
                                                            <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20 animate-pulse shadow-[0_0_10px_rgba(239,68,68,0.2)]">
                                                                Negative Stock Debt - Awaiting PO True-Up
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                                Active Cost Layer
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
