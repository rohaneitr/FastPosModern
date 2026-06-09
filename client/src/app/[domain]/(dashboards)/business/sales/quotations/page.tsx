'use client';

import React, { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import Link from 'next/link';

interface Quotation {
  id: number;
  invoice_no: string;
  transaction_date: string;
  customer_name: string | null;
  final_total: string | number;
  status: string;
  is_quotation: boolean;
}

export default function QuotationsPage() {
  const [quotations, setQuotations] = useState<Quotation[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [converting, setConverting] = useState<number | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  const showToast = (message: string, type: 'success' | 'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchQuotations = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ status: 'quotation' });
      if (search) params.set('search', search);
      const res = await api.get(`/sales?${params}`);
      setQuotations(res.data?.data || res.data || []);
    } catch {
      setQuotations([]);
      showToast('Failed to load quotations.', 'error');
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => { fetchQuotations(); }, [fetchQuotations]);

  const handleConvert = async (q: Quotation) => {
    if (!confirm(`Convert quotation ${q.invoice_no} to a final sale? Stock will be decremented.`)) return;
    setConverting(q.id);
    try {
      const res = await api.post(`/sales/${q.id}/convert`);
      showToast(`Converted! New invoice: ${res.data.invoice_no}`, 'success');
      fetchQuotations();
    } catch (e: any) {
      showToast(e.response?.data?.message || 'Conversion failed.', 'error');
    } finally {
      setConverting(null);
    }
  };

  const handleDelete = async (q: Quotation) => {
    if (!confirm(`Delete quotation ${q.invoice_no}? This cannot be undone.`)) return;
    try {
      await api.delete(`/sales/${q.id}`);
      setQuotations(prev => prev.filter(x => x.id !== q.id));
      showToast('Quotation deleted.', 'success');
    } catch (e: any) {
      showToast(e.response?.data?.message || 'Failed to delete quotation.', 'error');
    }
  };

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500 pb-12">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-red-500/20 text-red-300 border-red-500/50'}`}>
          {toast.type === 'success' ? '✓' : '✕'} {toast.message}
        </div>
      )}

      {/* Header */}
      <div className="flex flex-wrap justify-between items-start gap-4">
        <div>
          <nav className="text-xs text-text-muted mb-2 flex items-center gap-2">
            <Link href="/business/sales" className="hover:text-white transition-colors">Sales Hub</Link>
            <span>/</span>
            <span className="text-white">Quotations</span>
          </nav>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-violet-400 to-cyan-400">Quotations</h1>
          <p className="text-text-muted mt-1 text-sm">Pre-sale quotations — review and convert to finalized invoices.</p>
        </div>
        <Link href="/business/sales" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white transition-all">
          ← Back to Sales Hub
        </Link>
      </div>

      {/* Search */}
      <div className="glass-card rounded-2xl border border-white/5 p-4">
        <input value={search} onChange={e => setSearch(e.target.value)}
          className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-sm text-white outline-none focus:border-violet-500/50 transition-all"
          placeholder="Search by quotation number or customer..." />
      </div>

      {/* Stats bar */}
      <div className="flex gap-4 items-center text-sm">
        <span className="glass-card rounded-xl px-4 py-2 border border-white/5">
          <span className="text-text-muted">Total Quotations: </span>
          <span className="font-bold text-violet-400">{quotations.length}</span>
        </span>
        <span className="glass-card rounded-xl px-4 py-2 border border-white/5">
          <span className="text-text-muted">Value: </span>
          <span className="font-bold text-cyan-400">
            ${quotations.reduce((s, q) => s + parseFloat(String(q.final_total || 0)), 0).toFixed(2)}
          </span>
        </span>
      </div>

      {/* Table */}
      <div className="glass-card rounded-2xl border border-white/5 overflow-hidden">
        <div className="overflow-x-auto">
          <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">invoice no</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">transaction date</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">customer name</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">final total</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">status</th>
              <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {(quotations || [])?.length > 0 ? (
      (quotations || []).map((item, index) => (
      <tr key={item.id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.invoice_no}</td>
                <td className="px-6 py-4 text-white font-medium">{item.transaction_date}</td>
                <td className="px-6 py-4 text-white font-medium">{item.customer_name}</td>
                <td className="px-6 py-4 text-white font-medium">{item.final_total}</td>
                <td className="px-6 py-4 text-white font-medium">{item.status}</td>
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
      </div>
    </div>
  );
}
