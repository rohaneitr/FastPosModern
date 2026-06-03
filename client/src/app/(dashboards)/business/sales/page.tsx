'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function AdvancedSalesPage() {
  const [activeTab, setActiveTab] = useState('final');
  const [sales, setSales] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSales(activeTab);
  }, [activeTab]);

  const fetchSales = async (status: string) => {
    setLoading(true);
    try {
      const res = await api.get(`/sales?status=${status}`);
      if (res.data && res.data.data) {
        setSales(res.data.data);
      }
    } catch (err) {
      console.warn("Failed to fetch sales", err);
      // Fallback Demo Data
      if (status === 'final') {
        setSales([{ id: 1, transaction_date: '2026-06-01', invoice_no: 'INV-1001', customer_name: 'John Doe', final_total: '150.00', payment_status: 'paid', discount_amount: '0' }]);
      } else if (status === 'draft') {
        setSales([{ id: 2, transaction_date: '2026-06-02', invoice_no: 'DRF-1002', customer_name: 'Walk-in Customer', final_total: '45.00', payment_status: 'due', discount_amount: '0' }]);
      } else if (status === 'quotation') {
        setSales([{ id: 3, transaction_date: '2026-06-02', invoice_no: 'QUO-1003', customer_name: 'Acme Corp', final_total: '1200.00', payment_status: 'due', discount_amount: '100.00' }]);
      } else if (status === 'returns') {
        setSales([{ id: 4, transaction_date: '2026-06-03', invoice_no: 'RET-1004', customer_name: 'John Doe', final_total: '50.00', payment_status: 'refunded', discount_amount: '0' }]);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleProcessReturn = async (id: number, amount: string) => {
    if (confirm('Are you sure you want to process a return for this sale? Inventory will be restored.')) {
      try {
        await api.post('/sales/return', { transaction_id: id, return_amount: amount, lines: [{ product_id: 1, quantity: 1 }] });
        alert('Return Processed Successfully!');
      } catch (e) {
        alert('Failed to process return. Ensure API is reachable.');
      }
    }
  };

  const tabs = [
    { id: 'final', label: 'All Sales' },
    { id: 'draft', label: 'Drafts' },
    { id: 'quotation', label: 'Quotations' },
    { id: 'returns', label: 'Sell Returns' },
    { id: 'agents', label: 'Commission Agents' },
  ];

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">
            Advanced Sales
          </h1>
          <p className="text-text-muted mt-1">Manage Sales, Drafts, Quotations, Returns and Agents.</p>
        </div>
        <button className="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2 rounded-lg shadow-lg font-medium transition-colors">
          + POS Terminal
        </button>
      </div>

      {/* Tabs */}
      <div className="glass-card rounded-xl p-2 inline-flex self-start gap-2 flex-wrap">
        {tabs.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
              activeTab === tab.id 
                ? 'bg-emerald-500 text-white shadow-md' 
                : 'text-text-muted hover:text-white hover:bg-white/5'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="glass-card rounded-xl border border-border p-6 min-h-[400px]">
        
        {['final', 'draft', 'quotation', 'returns'].includes(activeTab) && (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface/50 border-b border-border">
                <tr>
                  <th className="p-4 font-semibold text-text-muted">Date</th>
                  <th className="p-4 font-semibold text-text-muted">Invoice No.</th>
                  <th className="p-4 font-semibold text-text-muted">Customer</th>
                  <th className="p-4 font-semibold text-text-muted">Status</th>
                  <th className="p-4 font-semibold text-text-muted text-right">Total</th>
                  <th className="p-4 font-semibold text-text-muted text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={6} className="p-8 text-center text-text-muted">Loading...</td></tr>
                ) : sales.length === 0 ? (
                  <tr><td colSpan={6} className="p-8 text-center text-text-muted">No records found.</td></tr>
                ) : sales.map((s, i) => (
                  <tr key={i} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                    <td className="p-4">{new Date(s.transaction_date).toLocaleDateString()}</td>
                    <td className="p-4 font-mono text-emerald-400">{s.invoice_no}</td>
                    <td className="p-4 font-medium">{s.customer_name || 'Walk-in'}</td>
                    <td className="p-4">
                      <span className="px-2 py-1 rounded-full text-xs font-bold uppercase bg-surface border border-border">
                        {activeTab}
                      </span>
                    </td>
                    <td className="p-4 text-right font-bold">${parseFloat(s.final_total).toFixed(2)}</td>
                    <td className="p-4 text-center">
                      {activeTab === 'final' ? (
                        <button onClick={() => handleProcessReturn(s.id, s.final_total)} className="text-danger hover:text-red-400 font-medium text-xs">Process Return</button>
                      ) : activeTab === 'quotation' ? (
                        <button className="text-primary hover:text-blue-400 font-medium text-xs">Convert to Sale</button>
                      ) : (
                        <button className="text-text-muted hover:text-white font-medium text-xs">View</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {activeTab === 'agents' && (
          <div className="animate-in slide-in-from-right-4">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold">Sales Commission Agents</h2>
              <button className="text-sm bg-primary/20 text-primary px-3 py-1 rounded">+ Add Agent</button>
            </div>
            <p className="text-text-muted mb-6">Track external agents or employees who receive a percentage commission on sales they broker.</p>
            
            <div className="grid gap-4 md:grid-cols-2">
              <div className="bg-surface/30 border border-border p-4 rounded-xl flex items-center justify-between">
                <div>
                  <div className="font-bold text-lg">Mike Ross</div>
                  <div className="text-sm text-text-muted">mike@fastpos.com</div>
                </div>
                <div className="text-right">
                  <div className="text-emerald-400 font-bold text-xl">15%</div>
                  <div className="text-xs text-text-muted">Commission Rate</div>
                </div>
              </div>
            </div>
          </div>
        )}

      </div>
    </div>
  );
}
