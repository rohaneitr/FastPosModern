'use client';

import React, { Suspense, useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';

interface Shipment {
  id: number;
  transaction_id: number;
  invoice_no?: string;
  shipping_address: string;
  shipping_status: 'pending' | 'shipped' | 'in_transit' | 'delivered' | 'failed';
  tracking_number: string | null;
  estimated_delivery: string | null;
  note: string | null;
  created_at: string;
}

const SHIPPING_STATUSES = ['pending', 'shipped', 'in_transit', 'delivered', 'failed'] as const;

const statusConfig: Record<string, { label: string; color: string }> = {
  pending:    { label: 'Pending',    color: 'bg-amber-500/10 text-amber-400 border-amber-500/30' },
  shipped:    { label: 'Shipped',    color: 'bg-blue-500/10 text-blue-400 border-blue-500/30' },
  in_transit: { label: 'In Transit', color: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30' },
  delivered:  { label: 'Delivered',  color: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' },
  failed:     { label: 'Failed',     color: 'bg-rose-500/10 text-rose-400 border-rose-500/30' },
};

function ShipmentsContent() {
  const searchParams = useSearchParams();
  const prefillSaleId = searchParams.get('sale_id') || '';

  const [shipmentModal, setShipmentModal] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  // We load recent sales (final) to display shipment status inline
  const [sales, setSales] = useState<any[]>([]);
  const [loadingSales, setLoadingSales] = useState(true);

  const [form, setForm] = useState({
    sale_id: prefillSaleId,
    shipping_address: '',
    shipping_status: 'pending' as typeof SHIPPING_STATUSES[number],
    tracking_number: '',
    estimated_delivery: '',
    note: '',
  });

  const showToast = (message: string, type: 'success' | 'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchSales = useCallback(async () => {
    setLoadingSales(true);
    try {
      const res = await api.get('/sales?status=final&per_page=50');
      setSales(res.data?.data || res.data || []);
    } catch {
      setSales([]);
    } finally {
      setLoadingSales(false);
    }
  }, []);

  useEffect(() => { fetchSales(); }, [fetchSales]);
  useEffect(() => { if (prefillSaleId) { setForm(f => ({ ...f, sale_id: prefillSaleId })); setShipmentModal(true); } }, [prefillSaleId]);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.sale_id) { showToast('Please enter a Sale ID.', 'error'); return; }
    setIsSaving(true);
    try {
      await api.post(`/sales/${form.sale_id}/shipment`, {
        shipping_address: form.shipping_address,
        shipping_status: form.shipping_status,
        tracking_number: form.tracking_number || null,
        estimated_delivery: form.estimated_delivery || null,
        note: form.note || null,
      });
      showToast('Shipment saved!', 'success');
      setShipmentModal(false);
      fetchSales();
    } catch (e: any) {
      showToast(e.response?.data?.message || 'Failed to save shipment.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const openCreateFor = (saleId: number) => {
    setForm({ sale_id: String(saleId), shipping_address: '', shipping_status: 'pending', tracking_number: '', estimated_delivery: '', note: '' });
    setShipmentModal(true);
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
            <span>/</span><span className="text-white">Shipments</span>
          </nav>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-cyan-400 to-blue-400">Shipments</h1>
          <p className="text-text-muted mt-1 text-sm">Manage delivery tracking for finalized sales orders.</p>
        </div>
        <div className="flex gap-3">
          <Link href="/business/sales" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white transition-all">← Back</Link>
          <button onClick={() => { setForm({ sale_id: '', shipping_address: '', shipping_status: 'pending', tracking_number: '', estimated_delivery: '', note: '' }); setShipmentModal(true); }}
            className="bg-cyan-500 hover:bg-cyan-600 text-white px-5 py-2 rounded-xl font-bold transition-all shadow-lg shadow-cyan-500/20 text-sm">
            🚚 Add Shipment
          </button>
        </div>
      </div>

      {/* Shipment status overview */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
        {SHIPPING_STATUSES.map(s => {
          const cfg = statusConfig[s];
          return (
            <div key={s} className="glass-card rounded-xl p-3 border border-white/5 flex flex-col gap-1">
              <span className={`self-start px-2 py-0.5 rounded-full text-xs font-bold border ${cfg.color}`}>{cfg.label}</span>
              <span className="text-lg font-black text-white">—</span>
            </div>
          );
        })}
      </div>

      {/* Sales list with ship button */}
      <div className="glass-card rounded-2xl border border-white/5 overflow-hidden">
        <div className="p-4 border-b border-border">
          <h2 className="text-sm font-bold text-white">Recent Finalized Sales</h2>
          <p className="text-xs text-text-muted mt-0.5">Click "Ship" to create or update shipment for any sale.</p>
        </div>
        <div className="overflow-x-auto">
          <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">invoice no</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">shipping address</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">shipping status</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">tracking number</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">estimated delivery</th>
              <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {(sales || [])?.length > 0 ? (
      (sales || []).map((item, index) => (
      <tr key={item.id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.invoice_no}</td>
                <td className="px-6 py-4 text-white font-medium">{item.shipping_address}</td>
                <td className="px-6 py-4 text-white font-medium">{item.shipping_status}</td>
                <td className="px-6 py-4 text-white font-medium">{item.tracking_number}</td>
                <td className="px-6 py-4 text-white font-medium">{item.estimated_delivery}</td>
                <td className="px-6 py-4 text-right"><button onClick={() => openCreateFor(item.id)} className="text-cyan-500 hover:text-cyan-400 font-medium text-sm">Ship</button></td>
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

      {/* Shipment Modal */}
      {shipmentModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-lg shadow-2xl animate-in zoom-in-95 max-h-[90vh] overflow-y-auto">
            <div className="flex justify-between items-center mb-5">
              <div>
                <h2 className="text-lg font-bold text-white">Create / Update Shipment</h2>
                <p className="text-xs text-text-muted mt-0.5">Enter shipping details for the sale.</p>
              </div>
              <button onClick={() => setShipmentModal(false)} className="text-text-muted hover:text-white text-xl transition-colors">✕</button>
            </div>
            <form onSubmit={handleSave} className="flex flex-col gap-4">
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Sale ID *</label>
                <input required type="number" value={form.sale_id} onChange={e => setForm({ ...form, sale_id: e.target.value })}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white font-mono outline-none focus:border-cyan-500/50 transition-all"
                  placeholder="Transaction / Sale ID" />
              </div>
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Shipping Address *</label>
                <textarea required value={form.shipping_address} onChange={e => setForm({ ...form, shipping_address: e.target.value })} rows={2}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-cyan-500/50 transition-all resize-none"
                  placeholder="Full delivery address..." />
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="text-xs font-semibold text-text-muted mb-1.5 block">Shipping Status *</label>
                  <select value={form.shipping_status} onChange={e => setForm({ ...form, shipping_status: e.target.value as any })}
                    className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-cyan-500/50 transition-all">
                    {SHIPPING_STATUSES.map(s => <option key={s} value={s}>{statusConfig[s].label}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-xs font-semibold text-text-muted mb-1.5 block">Tracking Number</label>
                  <input value={form.tracking_number} onChange={e => setForm({ ...form, tracking_number: e.target.value })}
                    className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white font-mono outline-none focus:border-cyan-500/50 transition-all"
                    placeholder="e.g. TRK-12345" />
                </div>
              </div>
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Estimated Delivery</label>
                <input type="date" value={form.estimated_delivery} onChange={e => setForm({ ...form, estimated_delivery: e.target.value })}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-cyan-500/50 transition-all" />
              </div>
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Note</label>
                <input value={form.note} onChange={e => setForm({ ...form, note: e.target.value })}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-cyan-500/50 transition-all"
                  placeholder="Additional shipping notes..." />
              </div>
              <div className="flex gap-3 mt-1">
                <button type="button" onClick={() => setShipmentModal(false)}
                  className="flex-1 py-2.5 bg-background border border-border rounded-xl font-medium text-text-muted hover:text-white transition-colors">Cancel</button>
                <button type="submit" disabled={isSaving}
                  className="flex-1 py-2.5 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl font-bold transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                  {isSaving ? <><span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />Saving…</> : '🚚 Save Shipment'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

export default function ShipmentsPage() {
  return (
    <Suspense fallback={<div className="p-8 text-text-muted">Loading shipments…</div>}>
      <ShipmentsContent />
    </Suspense>
  );
}
