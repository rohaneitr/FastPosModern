'use client';

import React, { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import api from '@/lib/api';

export default function CustomerLedgerPage() {
  const params = useParams();
  const router = useRouter();
  const contactId = params.id as string;

  const [contact, setContact] = useState<any>(null);
  const [ledger, setLedger] = useState<any[]>([]);
  const [summary, setSummary] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Payment Modal State
  const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
  const [paymentForm, setPaymentForm] = useState({
    amount: '',
    method: 'cash',
    note: ''
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const [ledgerRes, summaryRes] = await Promise.all([
        api.get(`/contacts/${contactId}/ledger`),
        api.get(`/contacts/${contactId}/ledger/summary`)
      ]);
      setContact(ledgerRes.data.contact);
      setLedger(ledgerRes.data.ledger);
      setSummary(summaryRes.data);
    } catch (err) {
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (contactId) fetchData();
  }, [contactId]);

  const handleReceivePayment = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post(`/contacts/${contactId}/payments`, paymentForm);
      alert('Payment recorded successfully');
      setIsPaymentModalOpen(false);
      setPaymentForm({ amount: '', method: 'cash', note: '' });
      fetchData();
    } catch (err: any) {
      alert(`Failed: ${err.response?.data?.message || err.message}`);
    }
  };

  if (loading) {
    return (
      <div className="flex h-full items-center justify-center">
        <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  if (!contact) {
    return <div className="p-6 text-center text-text-muted">Contact not found.</div>;
  }

  return (
    <div className="flex flex-col h-full gap-6 animate-in fade-in duration-500">
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <button 
            onClick={() => router.back()}
            className="text-sm text-text-muted hover:text-white mb-2 flex items-center gap-1 transition-colors"
          >
            &larr; Back to Contacts
          </button>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-purple-400">
            {contact.name || `${contact.first_name} ${contact.last_name}`}
          </h1>
          <p className="text-text-muted mt-1 uppercase text-xs tracking-wider font-semibold">
            {contact.type} Ledger & Statement
          </p>
        </div>
        
        <div className="flex gap-3">
          <button 
            onClick={() => window.print()}
            className="glass bg-white/5 hover:bg-white/10 text-white px-4 py-2 rounded-lg transition-all font-medium flex items-center gap-2"
          >
            🖨️ Print Statement
          </button>
          <button 
            onClick={() => setIsPaymentModalOpen(true)}
            className="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2 rounded-lg shadow-lg hover:shadow-[0_0_15px_rgba(16,185,129,0.5)] transition-all font-medium flex items-center gap-2"
          >
            💰 Receive Payment
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      {summary && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="glass-card p-5 rounded-xl border border-white/5 flex flex-col gap-1">
            <span className="text-text-muted text-sm font-medium">Total Sales</span>
            <span className="text-2xl font-bold text-white">${Number(summary.total_sales).toFixed(2)}</span>
          </div>
          <div className="glass-card p-5 rounded-xl border border-white/5 flex flex-col gap-1">
            <span className="text-text-muted text-sm font-medium">Total Payments</span>
            <span className="text-2xl font-bold text-emerald-400">${Number(summary.total_payments).toFixed(2)}</span>
          </div>
          <div className="glass-card p-5 rounded-xl border border-white/5 flex flex-col gap-1">
            <span className="text-text-muted text-sm font-medium">Total Returns</span>
            <span className="text-2xl font-bold text-amber-400">${Number(summary.total_returns).toFixed(2)}</span>
          </div>
          <div className="glass-card p-5 rounded-xl border border-white/5 flex flex-col gap-1 bg-gradient-to-br from-primary/10 to-transparent">
            <span className="text-text-muted text-sm font-medium">Outstanding Balance</span>
            <span className={`text-3xl font-bold ${Number(summary.total_due) > 0 ? 'text-rose-400' : 'text-emerald-400'}`}>
              ${Number(summary.total_due).toFixed(2)}
            </span>
          </div>
        </div>
      )}

      {/* Ledger Table */}
      <div className="glass-card rounded-xl border border-border flex-1 overflow-hidden flex flex-col">
        <div className="p-4 border-b border-border bg-white/5 flex justify-between items-center">
          <h2 className="font-semibold text-lg">Transaction History</h2>
        </div>
        <div className="overflow-x-auto flex-1">
          <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted">Date</th>
      <th className="px-6 py-4 font-semibold text-text-muted">Particulars</th>
      <th className="px-6 py-4 font-semibold text-text-muted text-right">Debit ($)</th>
      <th className="px-6 py-4 font-semibold text-text-muted text-right">Credit ($)</th>
      <th className="px-6 py-4 font-semibold text-text-muted text-right">Balance ($)</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {ledger.length > 0 ? (
      ledger.map((item, index) => (
      <tr key={item.ref_id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.date ? new Date(item.date).toLocaleDateString() : '-'}</td>
        <td className="px-6 py-4 text-white font-medium">{item.description}</td>
        <td className="px-6 py-4 text-right text-rose-400 font-mono">{Number(item.debit) > 0 ? Number(item.debit).toFixed(2) : '-'}</td>
        <td className="px-6 py-4 text-right text-emerald-400 font-mono">{Number(item.credit) > 0 ? Number(item.credit).toFixed(2) : '-'}</td>
        <td className="px-6 py-4 text-right font-bold text-white font-mono">{Number(item.balance).toFixed(2)}</td>
      </tr>
    ))) : (
      <tr>
        <td colSpan={5} className="px-6 py-8 text-center text-text-muted">No records found.</td>
      </tr>
    )}
  </tbody>
</table>
</div>
        </div>
      </div>

      {/* Receive Payment Modal */}
      {isPaymentModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
          <div className="glass-card w-full max-w-md rounded-2xl p-6 shadow-2xl border border-white/10 animate-in zoom-in-95">
            <h2 className="text-xl font-bold mb-4">Receive Payment</h2>
            <form onSubmit={handleReceivePayment} className="flex flex-col gap-4">
              <div>
                <label className="block text-sm font-medium text-text-muted mb-1">Amount ($)</label>
                <input 
                  type="number"
                  step="0.01"
                  required
                  value={paymentForm.amount}
                  onChange={e => setPaymentForm({...paymentForm, amount: e.target.value})}
                  className="w-full bg-background/50 border border-border rounded-lg px-4 py-2.5 outline-none focus:border-primary text-white"
                  placeholder={`Due: $${Number(summary?.total_due || 0).toFixed(2)}`}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-text-muted mb-1">Payment Method</label>
                <select 
                  value={paymentForm.method}
                  onChange={e => setPaymentForm({...paymentForm, method: e.target.value})}
                  className="w-full bg-background/50 border border-border rounded-lg px-4 py-2.5 outline-none focus:border-primary text-white"
                >
                  <option value="cash">Cash</option>
                  <option value="card">Card</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="bkash">bKash</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-text-muted mb-1">Note (Optional)</label>
                <textarea 
                  value={paymentForm.note}
                  onChange={e => setPaymentForm({...paymentForm, note: e.target.value})}
                  className="w-full bg-background/50 border border-border rounded-lg px-4 py-2.5 outline-none focus:border-primary text-white min-h-[80px]"
                  placeholder="Payment reference or note..."
                />
              </div>
              <div className="flex gap-3 justify-end mt-2">
                <button type="button" onClick={() => setIsPaymentModalOpen(false)} className="px-5 py-2.5 rounded-lg text-text-muted hover:bg-white/5 transition-colors font-medium">
                  Cancel
                </button>
                <button type="submit" className="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-medium shadow-lg shadow-emerald-500/30 transition-all">
                  Confirm Payment
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <style jsx global>{`
        @media print {
          body * {
            visibility: hidden;
          }
          .glass-card, .glass-card * {
            visibility: visible;
          }
          .glass-card {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            border: none;
            box-shadow: none;
            background: white !important;
            color: black !important;
          }
          /* Override dark mode text */
          .text-white, .text-text-muted, .text-primary, .text-emerald-400, .text-rose-400 {
            color: black !important;
          }
          th, td {
            border-color: #ccc !important;
          }
          h1, p {
            visibility: visible;
            color: black !important;
          }
          button { display: none !important; }
        }
      `}</style>
    </div>
  );
}
