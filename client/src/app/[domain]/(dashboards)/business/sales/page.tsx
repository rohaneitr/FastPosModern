'use client';

import React, { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useCartStore } from '@/store/useCartStore';

interface Sale {
  id: number;
  invoice_no: string;
  transaction_date: string;
  customer_name: string | null;
  final_total: string | number;
  amount_due: string | number;
  payment_status: 'paid' | 'partial' | 'due' | 'refunded';
  status: string;
  is_quotation: boolean;
  cashier_name?: string;
}

interface Pagination {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

const PAYMENT_METHODS = ['cash', 'card', 'bank_transfer', 'bkash', 'sslcommerz'];

export default function SalesHubPage() {
  const router = useRouter();
  const { clearCart, addItem, updateQuantity } = useCartStore();
  const [sales, setSales] = useState<Sale[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [page, setPage] = useState(1);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  // Add Payment modal
  const [paymentModal, setPaymentModal] = useState<{ open: boolean; sale: Sale | null }>({ open: false, sale: null });
  const [payAmount, setPayAmount] = useState('');
  const [payMethod, setPayMethod] = useState('cash');
  const [payNote, setPayNote] = useState('');
  const [isSavingPay, setIsSavingPay] = useState(false);

  // Stats
  const [stats, setStats] = useState({ total: 0, paid: 0, partial: 0, due: 0, revenue: 0 });

  const showToast = (message: string, type: 'success' | 'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchSales = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ status: 'final', page: String(page), per_page: '15' });
      if (search) params.set('search', search);
      if (startDate) params.set('start_date', startDate);
      if (endDate) params.set('end_date', endDate);

      const res = await api.get(`/sales?${params}`);
      const data = res.data?.data || res.data || [];
      setSales(Array.isArray(data) ? data : []);

      if (res.data?.current_page) {
        setPagination({
          current_page: res.data.current_page,
          last_page: res.data.last_page,
          total: res.data.total,
          per_page: res.data.per_page,
        });
      }

      // Fetch global stats from dashboard instead of paginated data
      try {
        const statsRes = await api.get('/reports/dashboard');
        setStats({
          total: statsRes.data.total_sales_count || statsRes.data.total_invoices || 0,
          paid: statsRes.data.paid_sales_count || 0,
          partial: statsRes.data.partial_sales_count || 0,
          due: statsRes.data.due_sales_count || 0,
          revenue: statsRes.data.total_revenue || 0,
        });
      } catch (statsErr) {
      }
    } catch {
      setSales([]);
      showToast('Failed to load sales. Check API connection.', 'error');
    } finally {
      setLoading(false);
    }
  }, [page, search, startDate, endDate]);

  useEffect(() => { fetchSales(); }, [fetchSales]);

  const handleProcessReturn = async (sale: Sale) => {
    if (!confirm(`Process a full return for invoice ${sale.invoice_no}? Inventory will be restored.`)) return;
    try {
      await api.post('/sales/return', { transaction_id: sale.id, return_amount: parseFloat(String(sale.final_total)), lines: [] });
      showToast('Return processed! Inventory restored.', 'success');
      fetchSales();
    } catch (e: any) {
      showToast(e.response?.data?.message || 'Failed to process return.', 'error');
    }
  };

  const handleConvertQuotation = async (sale: Sale) => {
    try {
      const res = await api.get(`/invoices/${sale.id}`);
      const invoiceData = res.data;
      
      clearCart();
      
      invoiceData.lines?.forEach((line: any) => {
        addItem({
          id: line.product_id,
          name: line.product_name,
          price: parseFloat(line.unit_price),
          has_serial_number: false
        });
        updateQuantity(line.product_id, parseFloat(line.quantity));
      });
      
      showToast('Quotation loaded into POS!', 'success');
      router.push('/user/pos');
    } catch (err: any) {
      showToast('Failed to load quotation details.', 'error');
    }
  };

  const handleSendEmail = async (id: number) => {
    const email = prompt("Enter customer email address:", "");
    if (!email) return;

    try {
      await api.post(`/sales/${id}/email`, { email });
      showToast('Email queued for delivery!', 'success');
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to send email', 'error');
    }
  };

  const openPaymentModal = (sale: Sale) => {
    setPaymentModal({ open: true, sale });
    setPayAmount(String(parseFloat(String(sale.amount_due || 0)).toFixed(2)));
    setPayMethod('cash');
    setPayNote('');
  };

  const handleAddPayment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!paymentModal.sale) return;
    setIsSavingPay(true);
    try {
      await api.post(`/sales/${paymentModal.sale.id}/payment`, { amount: parseFloat(payAmount), method: payMethod, note: payNote });
      showToast('Payment recorded successfully!', 'success');
      setPaymentModal({ open: false, sale: null });
      fetchSales();
    } catch (e: any) {
      showToast(e.response?.data?.message || 'Failed to record payment.', 'error');
    } finally {
      setIsSavingPay(false);
    }
  };

  const statusBadge = (s: Sale) => {
    const cfg: Record<string, string> = {
      paid: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
      partial: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
      due: 'bg-rose-500/10 text-rose-400 border-rose-500/30',
    };
    return <span className={`px-2.5 py-1 rounded-full text-xs font-bold uppercase border ${cfg[s.payment_status] || cfg['due']}`}>{s.payment_status}</span>;
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
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">Sales Hub</h1>
          <p className="text-text-muted mt-1 text-sm">All finalised sales invoices · manage payments, returns &amp; shipments.</p>
        </div>
        <div className="flex gap-3 flex-wrap">
          <Link href="/business/sales/quotations" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white hover:border-emerald-500/30 transition-all">📋 Quotations</Link>
          <Link href="/business/sales/drafts" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white hover:border-emerald-500/30 transition-all">📝 Drafts</Link>
          <Link href="/business/sales/returns" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white hover:border-rose-500/30 transition-all">↩ Returns</Link>
          <Link href="/business/sales/shipments" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white hover:border-cyan-500/30 transition-all">🚚 Shipments</Link>
          <Link href="/business/sales/payments" className="px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white hover:border-blue-500/30 transition-all">💳 Payments</Link>
          <button onClick={() => router.push('/user/pos')} className="bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2 rounded-xl font-bold transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-2 text-sm">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
            POS Terminal
          </button>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Total Invoices', value: pagination?.total ?? stats.total, color: 'text-cyan-400', icon: '🧾' },
          { label: 'Paid', value: stats.paid, color: 'text-emerald-400', icon: '✅' },
          { label: 'Partial / Due', value: stats.partial + stats.due, color: 'text-amber-400', icon: '⏳' },
          { label: 'Revenue (page)', value: `$${stats.revenue.toFixed(2)}`, color: 'text-purple-400', icon: '💰' },
        ].map(s => (
          <div key={s.label} className="glass-card rounded-2xl p-4 border border-white/5">
            <div className="flex items-center gap-2 mb-1">
              <span className="text-xl">{s.icon}</span>
              <span className="text-xs text-text-muted font-semibold uppercase tracking-wider">{s.label}</span>
            </div>
            <div className={`text-2xl font-black ${s.color}`}>{s.value}</div>
          </div>
        ))}
      </div>

      {/* Filters */}
      <div className="glass-card rounded-2xl border border-white/5 p-4 flex flex-wrap gap-3 items-end">
        <div className="flex-1 min-w-[200px]">
          <label className="text-xs text-text-muted font-semibold mb-1 block">Search Invoice / Customer</label>
          <input
            value={search} onChange={e => { setSearch(e.target.value); setPage(1); }}
            className="w-full bg-background border border-border rounded-xl px-4 py-2 text-sm text-white outline-none focus:border-emerald-500/50 transition-all"
            placeholder="INV-123... or customer name"
          />
        </div>
        <div>
          <label className="text-xs text-text-muted font-semibold mb-1 block">From</label>
          <input type="date" value={startDate} onChange={e => { setStartDate(e.target.value); setPage(1); }}
            className="bg-background border border-border rounded-xl px-3 py-2 text-sm text-white outline-none focus:border-emerald-500/50 transition-all" />
        </div>
        <div>
          <label className="text-xs text-text-muted font-semibold mb-1 block">To</label>
          <input type="date" value={endDate} onChange={e => { setEndDate(e.target.value); setPage(1); }}
            className="bg-background border border-border rounded-xl px-3 py-2 text-sm text-white outline-none focus:border-emerald-500/50 transition-all" />
        </div>
        {(search || startDate || endDate) && (
          <button onClick={() => { setSearch(''); setStartDate(''); setEndDate(''); setPage(1); }}
            className="px-4 py-2 rounded-xl text-sm border border-border text-text-muted hover:text-white hover:border-rose-500/30 transition-all">
            ✕ Clear
          </button>
        )}
        <div className="flex-1" />
        <button 
          onClick={async () => {
            try {
              const params = new URLSearchParams();
              if (startDate) params.set('start_date', startDate);
              if (endDate) params.set('end_date', endDate);
              const res = await api.get(`/reports/sales/export?${params.toString()}`, { responseType: 'blob' });
              
              const url = window.URL.createObjectURL(new Blob([res.data]));
              const link = document.createElement('a');
              link.href = url;
              link.setAttribute('download', `sales_export_${new Date().getTime()}.csv`);
              document.body.appendChild(link);
              link.click();
              link.parentNode?.removeChild(link);
              window.URL.revokeObjectURL(url);
            } catch (err) {
              showToast('Failed to export CSV', 'error');
            }
          }}
          className="px-4 py-2 rounded-xl text-sm font-bold bg-blue-500/10 border border-blue-500/30 text-blue-400 hover:bg-blue-500/20 transition-all flex items-center gap-2"
        >
          ⬇️ Download CSV
        </button>
      </div>

      {/* Table */}
      <div className="glass-card rounded-2xl border border-white/5 overflow-hidden">
        <div className="overflow-x-auto">
          <div className="w-full overflow-x-auto">
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
                        {s.status}
                      </span>
                    </td>
                    <td className="p-4 text-right font-bold">${parseFloat(String(s.final_total)).toFixed(2)}</td>
                    <td className="p-4 text-center">
                      <div className="flex gap-2 justify-center">
                        <button onClick={() => handleSendEmail(s.id)} className="text-white hover:text-emerald-400 font-medium text-xs px-2 py-1 bg-surface border border-border rounded-md shadow-sm hover:border-emerald-500/50 transition-all flex items-center gap-1">
                          ✉️ Email
                        </button>
                        {s.status === 'final' ? (
                          <>
                            <button onClick={() => router.push(`/business/sales/${s.id}/invoice`)} className="text-text-muted hover:text-white font-medium text-xs px-2 py-1">View</button>
                            <button onClick={() => handleProcessReturn(s)} className="text-danger hover:text-red-400 font-medium text-xs px-2 py-1">Return</button>
                          </>
                        ) : s.status === 'quotation' ? (
                          <button onClick={() => handleConvertQuotation(s)} className="text-primary hover:text-blue-400 font-medium text-xs px-2 py-1">Convert</button>
                        ) : (
                          <button onClick={() => router.push(`/business/sales/${s.id}/invoice`)} className="text-text-muted hover:text-white font-medium text-xs px-2 py-1">View</button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
</div>
        </div>

        {/* Pagination */}
        {pagination && pagination.last_page > 1 && (
          <div className="p-4 border-t border-border flex items-center justify-between text-sm">
            <span className="text-text-muted">Page {pagination.current_page} of {pagination.last_page} · {pagination.total} records</span>
            <div className="flex gap-2">
              <button disabled={page <= 1} onClick={() => setPage(p => p - 1)}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white hover:border-emerald-500/30 disabled:opacity-30 transition-all">← Prev</button>
              <button disabled={page >= pagination.last_page} onClick={() => setPage(p => p + 1)}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white hover:border-emerald-500/30 disabled:opacity-30 transition-all">Next →</button>
            </div>
          </div>
        )}
      </div>

      {/* Add Payment Modal */}
      {paymentModal.open && paymentModal.sale && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-md shadow-2xl animate-in zoom-in-95">
            <div className="flex justify-between items-center mb-5">
              <div>
                <h2 className="text-lg font-bold text-white">Record Payment</h2>
                <p className="text-xs text-text-muted mt-0.5">{paymentModal.sale.invoice_no} · Due: <span className="text-rose-400 font-bold">${parseFloat(String(paymentModal.sale.amount_due)).toFixed(2)}</span></p>
              </div>
              <button onClick={() => setPaymentModal({ open: false, sale: null })} className="text-text-muted hover:text-white text-xl transition-colors">✕</button>
            </div>
            <form onSubmit={handleAddPayment} className="flex flex-col gap-4">
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Amount *</label>
                <input required type="number" step="0.01" min="0.01" value={payAmount} onChange={e => setPayAmount(e.target.value)}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white font-mono text-lg outline-none focus:border-emerald-500/50 transition-all" />
              </div>
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Payment Method *</label>
                <select value={payMethod} onChange={e => setPayMethod(e.target.value)}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-emerald-500/50 transition-all">
                  {PAYMENT_METHODS.map(m => <option key={m} value={m} className="capitalize">{m.replace('_', ' ')}</option>)}
                </select>
              </div>
              <div>
                <label className="text-xs font-semibold text-text-muted mb-1.5 block">Note (optional)</label>
                <input value={payNote} onChange={e => setPayNote(e.target.value)}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-emerald-500/50 transition-all"
                  placeholder="e.g. partial payment #2" />
              </div>
              <div className="flex gap-3 mt-1">
                <button type="button" onClick={() => setPaymentModal({ open: false, sale: null })}
                  className="flex-1 py-2.5 bg-background border border-border rounded-xl font-medium text-text-muted hover:text-white transition-colors">Cancel</button>
                <button type="submit" disabled={isSavingPay}
                  className="flex-1 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-bold transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                  {isSavingPay ? <><span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />Saving…</> : '💳 Record Payment'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
