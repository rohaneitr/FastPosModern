'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function PurchasesPage() {
  const [purchases, setPurchases] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchPurchases();
  }, []);

  const fetchPurchases = async () => {
    setLoading(true);
    try {
      const res = await api.get('/purchases');
      if (res.data && res.data.data) {
        setPurchases(res.data.data);
      }
    } catch (err) {
      console.warn("Failed to fetch purchases", err);
      // Fallback
      setPurchases([
        { id: 1, invoice_no: 'PO-12345', status: 'received', final_total: '1250.00', transaction_date: '2026-06-01T10:00:00Z' },
        { id: 2, invoice_no: 'PO-12346', status: 'pending', final_total: '450.50', transaction_date: '2026-06-02T11:30:00Z' }
      ]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex flex-col h-full gap-6 animate-in fade-in duration-500">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-emerald-400">
            Purchases & Supply Chain
          </h1>
          <p className="text-text-muted mt-1">Manage purchase orders and stock receiving.</p>
        </div>
        <button className="bg-primary hover:bg-primary-hover text-white px-6 py-2 rounded-lg shadow-lg font-medium">
          + New Purchase
        </button>
      </div>

      <div className="glass-card rounded-xl overflow-hidden border border-border">
        <table className="w-full text-left text-sm">
          <thead className="bg-surface/50 border-b border-border">
            <tr>
              <th className="p-4 font-semibold text-text-muted">Date</th>
              <th className="p-4 font-semibold text-text-muted">Invoice No.</th>
              <th className="p-4 font-semibold text-text-muted">Status</th>
              <th className="p-4 font-semibold text-text-muted text-right">Total Amount</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={4} className="p-8 text-center text-text-muted">Loading...</td></tr>
            ) : purchases.length === 0 ? (
              <tr><td colSpan={4} className="p-8 text-center text-text-muted">No purchases found.</td></tr>
            ) : (
              purchases.map(p => (
                <tr key={p.id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                  <td className="p-4">{new Date(p.transaction_date).toLocaleDateString()}</td>
                  <td className="p-4 font-medium">{p.invoice_no}</td>
                  <td className="p-4">
                    <span className={`px-2 py-1 rounded-full text-xs font-bold uppercase
                      ${p.status === 'received' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}
                    `}>
                      {p.status}
                    </span>
                  </td>
                  <td className="p-4 text-right font-semibold">${parseFloat(p.final_total).toFixed(2)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
