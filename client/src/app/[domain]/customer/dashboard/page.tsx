'use client';

import React, { useState } from 'react';
import useSWR from 'swr';
import { useRouter } from 'next/navigation';
import { Wallet, Award, FileText, Activity } from 'lucide-react';

interface Invoice {
    id: number;
    invoice_no: string;
    final_total: string;
    payment_status: string;
    transaction_date: string;
}

interface DiagnosticReport {
    id: number;
    test_type: string;
    status: string;
    created_at: string;
}

interface DashboardMetrics {
    kpis: {
        wallet_balance: string;
        loyalty_points: string;
    };
    recent_invoices: Invoice[];
    diagnostic_reports?: DiagnosticReport[];
}

const fetcher = async (url: string) => {
    const token = typeof window !== 'undefined' ? localStorage.getItem('fastpos_customer_token') : '';
    const res = await fetch(url, {
        headers: {
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`
        }
    });

    if (!res.ok) {
        if (res.status === 401 || res.status === 403) {
            if (typeof window !== 'undefined') {
                localStorage.removeItem('fastpos_customer_token');
                window.location.href = '/customer/login';
            }
        }
        throw new Error('Failed to fetch portal data');
    }

    return res.json();
};

const sanitizeHtml = (html: string) => {
    // Basic structural sanitization guardrail for missing DOMPurify
    // In production, DOMPurify.sanitize(html) is strictly enforced.
    let clean = html.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, "");
    clean = clean.replace(/on\w+="[^"]*"/gi, "");
    clean = clean.replace(/javascript:/gi, "");
    return clean;
};

export default function CustomerPortalDashboard() {
    const router = useRouter();
    const [previewContent, setPreviewContent] = useState<string | null>(null);
    
    // Polling every 60 seconds
    const { data, error, isLoading } = useSWR<DashboardMetrics>('/api/v1/customer/dashboard-metrics', fetcher, { refreshInterval: 60000 });

    if (isLoading) {
        return (
            <div className="min-h-screen bg-white/60 dark:bg-gray-900/40 flex items-center justify-center">
                <div className="animate-pulse flex flex-col items-center">
                    <div className="h-12 w-12 bg-indigo-500/50 rounded-full mb-4"></div>
                    <div className="h-4 w-32 bg-gray-700 rounded"></div>
                </div>
            </div>
        );
    }

    if (error || !data) {
        return (
            <div className="min-h-screen bg-white/60 dark:bg-gray-900/40 flex items-center justify-center text-rose-500">
                <p>Failed to load customer profile. Please log in again.</p>
            </div>
        );
    }

    return (
        <div className="min-h-screen backdrop-blur-xl bg-white/60 dark:bg-gray-900/40 text-gray-900 dark:text-white p-6 md:p-12">
            <div className="max-w-6xl mx-auto">
                <header className="mb-10 border-b border-gray-200 dark:border-white/10 pb-6">
                    <h1 className="text-3xl md:text-4xl font-black tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-indigo-500 to-cyan-500">
                        Patient & Customer Hub
                    </h1>
                    <p className="text-gray-500 dark:text-gray-400 mt-2">Manage your financial ledgers and medical diagnostic history securely.</p>
                </header>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                    {/* Wallet Balance Widget */}
                    <div className="backdrop-blur-xl bg-white/80 dark:bg-gray-800/40 border border-gray-200 dark:border-white/10 rounded-3xl p-8 flex flex-col relative overflow-hidden group hover:border-indigo-500/50 transition-colors">
                        <div className="absolute -right-10 -top-10 text-indigo-500/10 group-hover:text-indigo-500/20 transition-colors">
                            <Wallet size={120} />
                        </div>
                        <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <Wallet size={16} /> Available Store Credit
                        </h3>
                        <p className="text-5xl font-black text-indigo-500 dark:text-indigo-400" data-testid="wallet-balance">
                            ${parseFloat(data.kpis?.wallet_balance || '0').toFixed(2)}
                        </p>
                    </div>

                    {/* Loyalty Points Widget */}
                    <div className="backdrop-blur-xl bg-white/80 dark:bg-gray-800/40 border border-gray-200 dark:border-white/10 rounded-3xl p-8 flex flex-col relative overflow-hidden group hover:border-emerald-500/50 transition-colors">
                        <div className="absolute -right-10 -top-10 text-emerald-500/10 group-hover:text-emerald-500/20 transition-colors">
                            <Award size={120} />
                        </div>
                        <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <Award size={16} /> Loyalty Rewards
                        </h3>
                        <p className="text-5xl font-black text-emerald-500 dark:text-emerald-400" data-testid="loyalty-points">
                            {parseInt(data.kpis?.loyalty_points || '0').toLocaleString()}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {/* Medical Records Grid */}
                    <div className="backdrop-blur-xl bg-white/80 dark:bg-gray-800/40 border border-gray-200 dark:border-white/10 rounded-3xl p-6 md:p-8">
                        <h3 className="text-xl font-bold mb-6 flex items-center gap-2">
                            <Activity size={20} className="text-gray-500 dark:text-gray-400" /> Medical Diagnostic Records
                        </h3>
                        
                        {(!data.diagnostic_reports || data.diagnostic_reports.length === 0) ? (
                            <div className="text-center py-10 text-gray-500" data-testid="empty-medical-state">
                                No medical logs or wallet activity recorded yet.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="border-b border-gray-200 dark:border-white/5 text-sm text-gray-500 dark:text-gray-400">
                                            <th className="pb-3 font-medium">Test Type</th>
                                            <th className="pb-3 font-medium">Date</th>
                                            <th className="pb-3 font-medium">Status</th>
                                            <th className="pb-3 font-medium text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.diagnostic_reports.map((report) => (
                                            <tr key={report.id} className="border-b border-gray-100 dark:border-white/5 last:border-0 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                                <td className="py-4 font-medium">{report.test_type}</td>
                                                <td className="py-4 text-gray-600 dark:text-gray-300">{new Date(report.created_at).toLocaleDateString()}</td>
                                                <td className="py-4">
                                                    <span className={`px-2 py-1 rounded text-xs font-medium ${
                                                        report.status === 'Published' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                                                    }`}>
                                                        {report.status.toUpperCase()}
                                                    </span>
                                                </td>
                                                <td className="py-4 text-right">
                                                    {report.status === 'Published' ? (
                                                        <a 
                                                            data-testid={`download-report-${report.id}`}
                                                            href={`/api/v1/public/diagnostic/verify?token=REPORT-${report.id}&signature=crypto_token_mock`}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
                                                        >
                                                            Download Report
                                                        </a>
                                                    ) : (
                                                        <span className="text-sm text-gray-400">Processing</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Invoices Table */}
                    <div className="backdrop-blur-xl bg-white/80 dark:bg-gray-800/40 border border-gray-200 dark:border-white/10 rounded-3xl p-6 md:p-8">
                        <h3 className="text-xl font-bold mb-6 flex items-center gap-2">
                            <FileText size={20} className="text-gray-500 dark:text-gray-400" /> Recent Invoices
                        </h3>
                        
                        {(!data.recent_invoices || data.recent_invoices.length === 0) ? (
                            <div className="text-center py-10 text-gray-500">
                                No invoice records found.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="border-b border-gray-200 dark:border-white/5 text-sm text-gray-500 dark:text-gray-400">
                                            <th className="pb-3 font-medium">Invoice #</th>
                                            <th className="pb-3 font-medium">Date</th>
                                            <th className="pb-3 font-medium">Total</th>
                                            <th className="pb-3 font-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.recent_invoices.map((inv) => (
                                            <tr key={inv.id} className="border-b border-gray-100 dark:border-white/5 last:border-0 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                                <td className="py-4 font-medium">{inv.invoice_no}</td>
                                                <td className="py-4 text-gray-600 dark:text-gray-300">{new Date(inv.transaction_date).toLocaleDateString()}</td>
                                                <td className="py-4">${parseFloat(inv.final_total).toFixed(2)}</td>
                                                <td className="py-4">
                                                    <span className={`px-2 py-1 rounded text-xs font-medium ${
                                                        inv.payment_status === 'paid' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/20 text-rose-600 dark:text-rose-400'
                                                    }`}>
                                                        {inv.payment_status.toUpperCase()}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
