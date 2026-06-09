'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useCurrency } from '@/lib/currency';

export default function DueCollectionPage() {
  const { format } = useCurrency();
  const [customers, setCustomers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [totalReceivable, setTotalReceivable] = useState(0);

  // Payment Modal State
  const [showModal, setShowModal] = useState(false);
  const [activeCustomer, setActiveCustomer] = useState<any>(null);
  const [payAmount, setPayAmount] = useState('');
  const [payMethod, setPayMethod] = useState('cash');
  const [payNote, setPayNote] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const fetchDues = async () => {
    setLoading(true);
    try {
      const res = await api.get('/contacts/due');
      setCustomers(res.data);
      const total = res.data.reduce((sum: number, c: any) => sum + parseFloat(c.total_due), 0);
      setTotalReceivable(total);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDues();
  }, []);

  const handleOpenPayment = (customer: any) => {
    setActiveCustomer(customer);
    setPayAmount(parseFloat(customer.total_due).toFixed(2));
    setPayMethod('cash');
    setPayNote('');
    setShowModal(true);
  };

  const handleReceivePayment = async (e: React.FormEvent) => {
    e.preventDefault();
    const amount = parseFloat(payAmount);
    if (isNaN(amount) || amount <= 0) return showToast('Invalid amount', 'error');
    if (amount > parseFloat(activeCustomer.total_due)) return showToast('Amount exceeds total due', 'error');

    setIsSubmitting(true);
    try {
      await api.post(`/contacts/${activeCustomer.id}/payments`, {
        amount,
        method: payMethod,
        note: payNote
      });
      showToast('Payment received successfully!', 'success');
      setShowModal(false);
      fetchDues();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to process payment', 'error');
    } finally {
      setIsSubmitting(false);
    }
  };

  const sendReminder = async (customer: any) => {
    if (!confirm(`Send SMS reminder to ${customer.name}?`)) return;
    try {
      await api.post(`/contacts/${customer.id}/reminder`, {
        amount: format(customer.total_due)
      });
      showToast(`Reminder sent to ${customer.name}`, 'success');
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to send SMS', 'error');
    }
  };

  return (
    <div className="flex flex-col gap-6 p-2 animate-in fade-in duration-500 pb-12 relative h-full overflow-y-auto">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border
          ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-rose-500/20 text-rose-300 border-rose-500/50'}`}>
          {toast.type === 'success' ? '✓' : '✕'} {toast.message}
        </div>
      )}

      <div>
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-rose-400 to-orange-400">
          Due Collection Dashboard
        </h1>
        <p className="text-text-muted mt-1">Manage accounts receivable and collect outstanding customer dues.</p>
      </div>

      {/* KPI Widget */}
      <div className="glass-card rounded-2xl p-8 border border-border shadow-2xl relative overflow-hidden bg-gradient-to-br from-rose-500/10 to-orange-500/5 flex items-center justify-between">
        <div className="absolute -top-20 -right-20 w-40 h-40 blur-[80px] rounded-full bg-rose-500/20 pointer-events-none"></div>
        <div>
          <p className="text-text-muted uppercase tracking-widest font-bold text-sm mb-2">Total Accounts Receivable</p>
          <h2 className="text-5xl font-black text-white drop-shadow-md">{format(totalReceivable)}</h2>
        </div>
        <div className="text-6xl opacity-80 drop-shadow-xl">💸</div>
      </div>

      {/* Due Table */}
      <div className="glass-card rounded-2xl border border-border flex flex-col flex-1 overflow-hidden">
        <div className="p-5 border-b border-border flex justify-between items-center bg-surface/30">
          <h2 className="text-xl font-bold text-white">Outstanding Balances</h2>
          <button onClick={fetchDues} className="text-text-muted hover:text-white transition-colors">🔄 Refresh</button>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-border bg-surface/50">
                <th className="p-4 text-xs font-semibold text-text-muted uppercase tracking-wider">Customer</th>
                <th className="p-4 text-xs font-semibold text-text-muted uppercase tracking-wider">Contact Info</th>
                <th className="p-4 text-xs font-semibold text-text-muted uppercase tracking-wider text-right">Total Due</th>
                <th className="p-4 text-xs font-semibold text-text-muted uppercase tracking-wider text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={4} className="p-8 text-center"><span className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin inline-block"></span></td></tr>
              ) : customers.length === 0 ? (
                <tr><td colSpan={4} className="p-8 text-center text-emerald-400 font-bold text-lg">🎉 No outstanding dues! All accounts are clear.</td></tr>
              ) : customers.map(c => (
                <tr key={c.id} className="border-b border-border/50 hover:bg-surface/30 transition-colors">
                  <td className="p-4">
                    <div className="font-bold text-white text-lg">{c.name}</div>
                    <div className="text-xs text-text-muted">ID: CUST-{c.id.toString().padStart(4, '0')}</div>
                  </td>
                  <td className="p-4">
                    {c.mobile && <div className="text-sm text-blue-300">📞 {c.mobile}</div>}
                    {c.email && <div className="text-sm text-text-muted mt-0.5">✉️ {c.email}</div>}
                  </td>
                  <td className="p-4 text-right">
                    <div className="font-bold text-rose-400 text-xl">{format(c.total_due)}</div>
                  </td>
                  <td className="p-4 text-right">
                    <div className="flex justify-end gap-3 items-center">
                      <button 
                        onClick={() => sendReminder(c)}
                        title="Send SMS Reminder"
                        className="p-2 rounded-full bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 transition-colors border border-blue-500/20"
                      >
                        🔔
                      </button>
                      <button 
                        onClick={() => handleOpenPayment(c)}
                        className="bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-bold px-4 py-2 rounded-xl transition-all flex items-center gap-2"
                      >
                        💳 Receive Payment
                      </button>
                      <a href={`/${(window.location.pathname.split('/')[1] || 'd')}/business/contacts/${c.id}?tab=ledger`} className="p-2 rounded-full bg-surface text-text-muted hover:text-white transition-colors border border-border" title="View Ledger">
                        📄
                      </a>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Receive Payment Modal */}
      {showModal && activeCustomer && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-md shadow-2xl animate-in zoom-in-95 relative overflow-hidden">
            <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-400 to-emerald-600"></div>
            
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold text-white flex items-center gap-2">💳 Receive Payment</h2>
              <button onClick={() => setShowModal(false)} className="text-text-muted hover:text-white transition-colors text-xl">✕</button>
            </div>

            <div className="bg-background/50 rounded-xl p-4 border border-border mb-6">
              <p className="text-sm text-text-muted mb-1">Customer: <span className="font-bold text-white">{activeCustomer.name}</span></p>
              <p className="text-sm text-text-muted">Total Due: <span className="font-bold text-rose-400 text-lg">{format(activeCustomer.total_due)}</span></p>
            </div>

            <form onSubmit={handleReceivePayment} className="flex flex-col gap-4">
              <div>
                <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">Payment Amount *</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold">$</span>
                  <input 
                    type="number" step="0.01" min="0.01" max={activeCustomer.total_due} required 
                    value={payAmount} onChange={e => setPayAmount(e.target.value)}
                    className="w-full bg-background border border-border rounded-xl pl-8 pr-4 py-3 text-white font-mono text-lg outline-none focus:border-emerald-500 transition-colors"
                    placeholder="0.00" 
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">Payment Method *</label>
                <select 
                  value={payMethod} onChange={e => setPayMethod(e.target.value)}
                  className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-500 transition-colors cursor-pointer"
                >
                  <option value="cash">Cash</option>
                  <option value="card">Card / POS</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="bkash">bKash / Mobile Money</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">Transaction Note</label>
                <input 
                  type="text" 
                  value={payNote} onChange={e => setPayNote(e.target.value)}
                  className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-500 transition-colors"
                  placeholder="Optional reference note..." 
                />
              </div>

              <div className="flex gap-3 mt-4">
                <button type="button" onClick={() => setShowModal(false)} className="flex-1 py-3 bg-background border border-border rounded-xl font-medium text-text-muted hover:text-white transition-colors">Cancel</button>
                <button 
                  type="submit" 
                  disabled={isSubmitting || !payAmount} 
                  className="flex-[2] bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-bold transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/20"
                >
                  {isSubmitting ? <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/> : null}
                  Confirm Payment
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
