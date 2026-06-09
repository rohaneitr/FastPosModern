'use client';

import React, { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import Link from 'next/link';

interface ReturnRecord {
  id: number;
  invoice_no: string;
  transaction_date: string;
  customer_name: string | null;
  final_total: string | number;
  payment_status: string;
}

export default function ReturnsPage() {
  const [returns, setReturns] = useState<ReturnRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  // New Return Modal
  const [returnModal, setReturnModal] = useState(false);
  const [returnForm, setReturnForm] = useState({ transaction_id: '', return_amount: '', reason: '' });
  const [isSaving, setIsSaving] = useState(false);

  const showToast = (message: string, type: 'success' | 'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchReturns = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ status: 'sell_return' });
      if (search) params.set('search', search);
      const res = await api.get(`/sales?${params}`);
      setReturns(res.data?.data || res.data || []);
    } catch {
      setReturns([]);
      showToast('Failed to load returns.', 'error');
    } finally {
      setLoading(false);
    }
  }, [search]);

  useEffect(() => { fetchReturns(); }, [fetchReturns]);

  const handleCreateReturn = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);
    try {
      await api.post('/sales/return', {
        transaction_id: parseInt(returnForm.transaction_id),
        return_amount: parseFloat(returnForm.return_amount),
        reason: returnForm.reason,
        lines: [],
      });
      showToast('Return processed! Inventory has been restored.', 'success');
      setReturnModal(false);
      setReturnForm({ transaction_id: '', return_amount: '', reason: '' });
      fetchReturns();
    } catch (e: any) {
      showToast(e.response?.data?.message || 'Return failed.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const totalRefunded = returns.reduce((s, r) => s + parseFloat(String(r.final_total || 0)), 0);

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
            <span>/</span><span className="text-white">Returns</span>
          </nav>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-rose-400 to-pink-400">Sell Returns</h1>
          <p className="text-text-muted mt-1 text-sm">Track refunds and inventory restorations from returned sales.</p>
        </div>
        <div className="flex gap-3">
          <Link href="/business/sales" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white transition-all">← Back</Link>
          <button onClick={() => setReturnModal(true)}
            className="bg-rose-500 hover:bg-rose-600 text-white px-5 py-2 rounded-xl font-bold transition-all shadow-lg shadow-rose-500/20 text-sm">
            + New Return
          </button>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="glass-card rounded-2xl p-4 border border-white/5">
          <div className="text-xs text-text-muted font-semibold uppercase tracking-wider mb-1">Total Returns</div>
          <div className="text-2xl font-black text-rose-400">{returns.length}</div>
        </div>
        <div className="glass-card rounded-2xl p-4 border border-white/5">
          <div className="text-xs text-text-muted font-semibold uppercase tracking-wider mb-1">Total Refunded</div>
          <div className="text-2xl font-black text-pink-400">${totalRefunded.toFixed(2)}</div>
        </div>
      </div>

      {/* Search */}
      <div className="glass-card rounded-2xl border border-white/5 p-4">
        <input value={search} onChange={e => setSearch(e.target.value)}
          className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-sm text-white outline-none focus:border-rose-500/50 transition-all"
          placeholder="Search return records..." />
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
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">payment status</th>
              <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {(returns || [])?.length > 0 ? (
      (returns || []).map((item, index) => (
      <tr key={item.id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.invoice_no}</td>
                <td className="px-6 py-4 text-white font-medium">{item.transaction_date}</td>
                <td className="px-6 py-4 text-white font-medium">{item.customer_name}</td>
                <td className="px-6 py-4 text-white font-medium">{item.final_total}</td>
                <td className="px-6 py-4 text-white font-medium">{item.payment_status}</td>
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

      {/* New Return Modal */}
      {returnModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-md shadow-2xl animate-in zoom-in-95">
            <div className="flex justify-between items-center mb-5">
              <div>
                <h2 className="text-lg font-bold text-white">Process Return</h2>
                <p className="text-xs text-text-muted mt-0.5">Inventory will be restored automatically.</p>
              </div>
              <button onClick={() => setReturnModal(false)} className="text-text-muted hover:text-white text-xl transition-colors">✕</button>
            </div>
            <form onSubmit={handleCreateReturn} className="flex flex-col gap-4">
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Sale ID *</label>
                <input required type="number" value={returnForm.transaction_id} onChange={e => setReturnForm({ ...returnForm, transaction_id: e.target.value })}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white font-mono outline-none focus:border-rose-500/50 transition-all"
                  placeholder="Enter the Sale / Transaction ID" />
              </div>
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Return Amount *</label>
                <input required type="number" step="0.01" min="0.01" value={returnForm.return_amount} onChange={e => setReturnForm({ ...returnForm, return_amount: e.target.value })}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white font-mono text-lg outline-none focus:border-rose-500/50 transition-all"
                  placeholder="0.00" />
              </div>
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Reason (optional)</label>
                <textarea value={returnForm.reason} onChange={e => setReturnForm({ ...returnForm, reason: e.target.value })} rows={2}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-rose-500/50 transition-all resize-none"
                  placeholder="e.g. Defective product, Customer changed mind..." />
              </div>
              <div className="flex gap-3 mt-1">
                <button type="button" onClick={() => setReturnModal(false)}
                  className="flex-1 py-2.5 bg-background border border-border rounded-xl font-medium text-text-muted hover:text-white transition-colors">Cancel</button>
                <button type="submit" disabled={isSaving}
                  className="flex-1 py-2.5 bg-rose-500 hover:bg-rose-600 text-white rounded-xl font-bold transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                  {isSaving ? <><span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />Processing…</> : '↩ Process Return'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
