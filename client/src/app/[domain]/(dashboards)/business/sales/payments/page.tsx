'use client';

import React, { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import Link from 'next/link';

interface Payment {
  id: number;
  transaction_id: number;
  invoice_no: string;
  customer_name: string | null;
  amount: string | number;
  method: string;
  paid_on: string;
  final_total: string | number;
  note?: string | null;
}

const METHOD_ICONS: Record<string, string> = {
  cash: '💵', card: '💳', bank_transfer: '🏦', bkash: '📱', sslcommerz: '🌐',
};

export default function PaymentsPage() {
  const [payments, setPayments] = useState<Payment[]>([]);
  const [pagination, setPagination] = useState<{ current_page: number; last_page: number; total: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  // Stats
  const [stats, setStats] = useState({ total: 0, cash: 0, card: 0, digital: 0, amount: 0 });

  const showToast = (message: string, type: 'success' | 'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchPayments = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/sales/payments?page=${page}&per_page=20`);
      const data: Payment[] = res.data?.data || res.data || [];
      setPayments(data);

      if (res.data?.current_page) {
        setPagination({ current_page: res.data.current_page, last_page: res.data.last_page, total: res.data.total });
      }

      const totalAmt = data.reduce((s, p) => s + parseFloat(String(p.amount || 0)), 0);
      setStats({
        total: res.data?.total ?? data.length,
        cash: data.filter(p => p.method === 'cash').length,
        card: data.filter(p => p.method === 'card').length,
        digital: data.filter(p => ['bkash', 'sslcommerz', 'bank_transfer'].includes(p.method)).length,
        amount: totalAmt,
      });
    } catch {
      setPayments([]);
      showToast('Failed to load payment history.', 'error');
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => { fetchPayments(); }, [fetchPayments]);

  const methodBadge = (method: string) => {
    const colors: Record<string, string> = {
      cash: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
      card: 'bg-blue-500/10 text-blue-400 border-blue-500/30',
      bank_transfer: 'bg-purple-500/10 text-purple-400 border-purple-500/30',
      bkash: 'bg-pink-500/10 text-pink-400 border-pink-500/30',
      sslcommerz: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30',
    };
    return (
      <span className={`px-2.5 py-1 rounded-full text-xs font-bold uppercase border flex items-center gap-1 ${colors[method] || 'bg-white/10 text-text-muted border-border'}`}>
        {METHOD_ICONS[method] || '💰'} {method.replace('_', ' ')}
      </span>
    );
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500 pb-12">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-red-500/20 text-red-300 border-red-500/50'}`}>
          {toast.type === 'success' ? '✓' : '✕'} {toast.message}
        </div>
      )}

      <div className="flex flex-wrap justify-between items-start gap-4">
        <div>
          <nav className="text-xs text-text-muted mb-2 flex items-center gap-2">
            <Link href="/business/sales" className="hover:text-white transition-colors">Sales Hub</Link>
            <span>/</span><span className="text-white">Payments</span>
          </nav>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-400">Payment History</h1>
          <p className="text-text-muted mt-1 text-sm">All payments received across finalized sales.</p>
        </div>
        <Link href="/business/sales" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white transition-all">← Back</Link>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Total Payments', value: pagination?.total ?? stats.total, color: 'text-blue-400', icon: '💳' },
          { label: 'Cash', value: stats.cash, color: 'text-emerald-400', icon: '💵' },
          { label: 'Card', value: stats.card, color: 'text-indigo-400', icon: '💳' },
          { label: 'Amount (page)', value: `$${stats.amount.toFixed(2)}`, color: 'text-purple-400', icon: '💰' },
        ].map(s => (
          <div key={s.label} className="glass-card rounded-2xl p-4 border border-white/5">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-lg">{s.icon}</span>
              <span className="text-xs text-text-muted font-semibold uppercase tracking-wider">{s.label}</span>
            </div>
            <div className={`text-2xl font-black ${s.color}`}>{s.value}</div>
          </div>
        ))}
      </div>

      {/* Table */}
      <div className="glass-card rounded-2xl border border-white/5 overflow-hidden">
        <div className="overflow-x-auto">
          <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">invoice no</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">customer name</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">amount</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">method</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">final total</th>
              <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {(payments || [])?.length > 0 ? (
      (payments || []).map((item, index) => (
      <tr key={item.id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.invoice_no}</td>
                <td className="px-6 py-4 text-white font-medium">{item.customer_name}</td>
                <td className="px-6 py-4 text-white font-medium">{item.amount}</td>
                <td className="px-6 py-4 text-white font-medium">{item.method}</td>
                <td className="px-6 py-4 text-white font-medium">{item.final_total}</td>
                <td className="px-6 py-4 text-right"><button className="text-rose-500 hover:text-rose-400 font-medium text-sm">View</button></td>
      </tr>
    ))) : (
      <tr>
        <td colSpan={10} className="px-6 py-8 text-center text-text-muted">No records found.</td>
      </tr>
    )}
  </tbody>
</table>
</div>
        </div>

        {pagination && pagination.last_page > 1 && (
          <div className="p-4 border-t border-border flex items-center justify-between text-sm">
            <span className="text-text-muted">Page {pagination.current_page} of {pagination.last_page} · {pagination.total} records</span>
            <div className="flex gap-2">
              <button disabled={page <= 1} onClick={() => setPage(p => p - 1)}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white hover:border-blue-500/30 disabled:opacity-30 transition-all">← Prev</button>
              <button disabled={page >= pagination.last_page} onClick={() => setPage(p => p + 1)}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white hover:border-blue-500/30 disabled:opacity-30 transition-all">Next →</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
