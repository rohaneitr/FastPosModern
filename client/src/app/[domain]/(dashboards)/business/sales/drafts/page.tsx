'use client';

import React, { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import Link from 'next/link';

interface DraftSale {
  id: number;
  invoice_no: string;
  transaction_date: string;
  customer_name: string | null;
  final_total: string | number;
}

export default function DraftsPage() {
  const [drafts, setDrafts] = useState<DraftSale[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [converting, setConverting] = useState<number | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  const showToast = (message: string, type: 'success' | 'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchDrafts = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ status: 'draft' });
      if (search) params.set('search', search);
      const res = await api.get(`/sales?${params}`);
      setDrafts(res.data?.data || res.data || []);
    } catch {
      setDrafts([]);
      showToast('Failed to load drafts.', 'error');
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => { fetchDrafts(); }, [fetchDrafts]);

  const handleConvert = async (d: DraftSale) => {
    if (!confirm(`Finalize draft ${d.invoice_no}? Stock will be decremented and invoice locked.`)) return;
    setConverting(d.id);
    try {
      const res = await api.post(`/sales/${d.id}/convert`);
      showToast(`Finalized! Invoice: ${res.data.invoice_no}`, 'success');
      fetchDrafts();
    } catch (e: any) {
      showToast(e.response?.data?.message || 'Conversion failed.', 'error');
    } finally {
      setConverting(null);
    }
  };

  const handleDelete = async (d: DraftSale) => {
    if (!confirm(`Delete draft ${d.invoice_no}?`)) return;
    try {
      await api.delete(`/sales/${d.id}`);
      setDrafts(prev => prev.filter(x => x.id !== d.id));
      showToast('Draft deleted.', 'success');
    } catch (e: any) {
      showToast(e.response?.data?.message || 'Failed to delete.', 'error');
    }
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
            <span>/</span><span className="text-white">Drafts</span>
          </nav>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-400">Draft Sales</h1>
          <p className="text-text-muted mt-1 text-sm">Incomplete sales saved as drafts — finalize when ready.</p>
        </div>
        <Link href="/business/sales" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white transition-all">← Back</Link>
      </div>

      <div className="glass-card rounded-2xl border border-white/5 p-4">
        <input value={search} onChange={e => setSearch(e.target.value)}
          className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-sm text-white outline-none focus:border-amber-500/50 transition-all"
          placeholder="Search by draft number or customer..." />
      </div>

      <div className="flex gap-4 text-sm">
        <span className="glass-card rounded-xl px-4 py-2 border border-white/5">
          <span className="text-text-muted">Drafts: </span><span className="font-bold text-amber-400">{drafts.length}</span>
        </span>
        <span className="glass-card rounded-xl px-4 py-2 border border-white/5">
          <span className="text-text-muted">Pending: </span>
          <span className="font-bold text-orange-400">${drafts.reduce((s, d) => s + parseFloat(String(d.final_total || 0)), 0).toFixed(2)}</span>
        </span>
      </div>

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
              <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {(drafts || [])?.length > 0 ? (
      (drafts || []).map((item, index) => (
      <tr key={item.id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.invoice_no}</td>
                <td className="px-6 py-4 text-white font-medium">{item.transaction_date}</td>
                <td className="px-6 py-4 text-white font-medium">{item.customer_name}</td>
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
      </div>
    </div>
  );
}
