'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function AccountingPage() {
  const [expenses, setExpenses] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);
  const [categories, setCategories] = useState<any[]>([]);
  const [formData, setFormData] = useState({
    expense_category_id: '', expense_date: '', total_amount: '', payment_method: 'cash', note: ''
  });

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  useEffect(() => { 
    fetchExpenses(); 
    fetchCategories();
  }, []);

  const fetchCategories = async () => {
    try {
      const res = await api.get('/expense-categories');
      setCategories(res.data || []);
    } catch (err) {}
  };

  const fetchExpenses = async () => {
    setLoading(true);
    try {
      const res = await api.get('/expenses');
      setExpenses(res.data?.data || res.data || []);
    } catch (err) {
      showToast('Failed to load expenses. Check API connection.', 'error');
    } finally {
      setLoading(false);
    }
  };

  const openCreate = () => {
    setEditingId(null);
    setFormData({ expense_category_id: '', expense_date: '', total_amount: '', payment_method: 'cash', note: '' });
    setIsModalOpen(true);
  };

  const openEdit = (e: any) => {
    setEditingId(e.id);
    setFormData({
      expense_category_id: e.expense_category_id || '',
      expense_date: e.expense_date ? e.expense_date.split('T')[0] : '',
      total_amount: e.total_amount || '',
      payment_method: e.payment_method || 'cash',
      note: e.note || ''
    });
    setIsModalOpen(true);
  };

  const handleSave = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setIsSaving(true);
    try {
      if (editingId) {
        await api.put(`/expenses/${editingId}`, formData);
        showToast('Expense updated successfully!', 'success');
      } else {
        await api.post('/expenses', formData);
        showToast('Expense created successfully!', 'success');
      }
      setIsModalOpen(false);
      fetchExpenses();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to save expense.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this expense record? This cannot be undone.')) return;
    try {
      await api.delete(`/expenses/${id}`);
      setExpenses(prev => prev.filter(e => e.id !== id));
      showToast('Expense deleted.', 'success');
    } catch (err) {
      showToast('Failed to delete expense.', 'error');
    }
  };

  return (
    <div className="flex flex-col h-full gap-6 animate-in fade-in duration-500 relative">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border
          ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-red-500/20 text-red-300 border-red-500/50'}`}>
          {toast.type === 'success' ? '✓' : '✕'} {toast.message}
        </div>
      )}

      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-500">
            Accounting & Expenses
          </h1>
          <p className="text-text-muted mt-1">Track and manage all business expenses.</p>
        </div>
        <button onClick={openCreate} className="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2.5 rounded-xl shadow-lg font-bold transition-all transform hover:scale-105">
          + Add Expense
        </button>
      </div>

      <div className="glass-card rounded-2xl overflow-hidden border border-border">
        <div className="overflow-x-auto">
          <div className="w-full overflow-x-auto">
<table className="w-full text-left text-sm">
          <thead className="bg-surface/50 border-b border-border">
            <tr>
              <th className="p-4 font-semibold text-text-muted">Date</th>
              <th className="p-4 font-semibold text-text-muted">Reference No.</th>
              <th className="p-4 font-semibold text-text-muted">Category</th>
              <th className="p-4 font-semibold text-text-muted">Payment</th>
              <th className="p-4 font-semibold text-text-muted text-center">Status</th>
              <th className="p-4 font-semibold text-text-muted text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr><td colSpan={5} className="p-8 text-center text-text-muted">Loading...</td></tr>
            ) : expenses.length === 0 ? (
              <tr><td colSpan={5} className="p-8 text-center text-text-muted">No expenses recorded yet.</td></tr>
            ) : (
              expenses.map(e => (
                <tr key={e.id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                  <td className="p-4">{new Date(e.expense_date).toLocaleDateString()}</td>
                  <td className="p-4 font-medium text-text-muted">{e.reference_no}</td>
                  <td className="p-4 text-purple-400 font-medium">{e.category_name || 'Uncategorized'}</td>
                  <td className="p-4 text-text-muted uppercase text-xs">{e.payment_method}</td>
                  <td className="p-4 text-center">
                    <span className={`px-2 py-1 rounded-full text-xs font-bold uppercase
                      ${e.payment_status === 'paid' ? 'bg-success/20 text-success' : 'bg-warning/20 text-warning'}
                    `}>
                      {e.payment_status}
                    </span>
                  </td>
                  <td className="p-4 text-right font-bold text-lg">${parseFloat(e.total_amount).toFixed(2)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
</div>
        </div>
      </div>

      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-md shadow-2xl animate-in zoom-in-95">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold text-white">{editingId ? 'Edit Expense' : 'New Expense'}</h2>
              <button onClick={() => setIsModalOpen(false)} className="text-text-muted hover:text-white transition-colors text-xl">✕</button>
            </div>
            <form onSubmit={handleSave} className="flex flex-col gap-4">
              <div>
                <label className="block text-sm font-semibold text-text-muted mb-1.5">Category *</label>
                <div className="flex gap-2">
                  <select required value={formData.expense_category_id} onChange={e => setFormData({...formData, expense_category_id: e.target.value})}
                    className="flex-1 bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-purple-500/50 focus:ring-2 focus:ring-purple-500/20 transition-all">
                    <option value="">Select Category...</option>
                    {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                  <button type="button" onClick={async () => {
                    const name = prompt("Enter new category name:");
                    if (name) {
                      try { await api.post('/expense-categories', { name }); fetchCategories(); } catch(e){}
                    }
                  }} className="bg-surface border border-border px-4 py-2 rounded-xl text-white hover:bg-surface/80">+</button>
                </div>
              </div>
              <div>
                <label className="block text-sm font-semibold text-text-muted mb-1.5">Payment Method *</label>
                <select required value={formData.payment_method} onChange={e => setFormData({...formData, payment_method: e.target.value})}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-purple-500/50 transition-all">
                  <option value="cash">Cash</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="mobile_banking">Mobile Banking</option>
                  <option value="card">Card</option>
                </select>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-text-muted mb-1.5">Date *</label>
                  <input required type="date" value={formData.expense_date} onChange={e => setFormData({...formData, expense_date: e.target.value})}
                    className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-purple-500/50 transition-all" />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-text-muted mb-1.5">Amount ($) *</label>
                  <input required type="number" step="0.01" min="0" value={formData.total_amount} onChange={e => setFormData({...formData, total_amount: e.target.value})}
                    className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white font-mono outline-none focus:border-purple-500/50 transition-all"
                    placeholder="0.00" />
                </div>
              </div>
              <div>
                <label className="block text-sm font-semibold text-text-muted mb-1.5">Note (optional)</label>
                <textarea value={formData.note} onChange={e => setFormData({...formData, note: e.target.value})}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white h-20 resize-none outline-none focus:border-purple-500/50 transition-all"
                  placeholder="Any additional details..." />
              </div>
              <div className="flex gap-3 mt-2">
                <button type="button" onClick={() => setIsModalOpen(false)} className="flex-1 py-2.5 bg-background border border-border rounded-xl font-medium text-text-muted hover:text-white hover:bg-surface transition-colors">Cancel</button>
                <button type="submit" disabled={isSaving} className="flex-1 py-2.5 bg-purple-500 hover:bg-purple-600 text-white rounded-xl font-bold transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                  {isSaving ? <><span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Saving...</> : 'Save Expense'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
