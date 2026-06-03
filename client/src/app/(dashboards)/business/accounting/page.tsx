'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function AccountingPage() {
  const [expenses, setExpenses] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchExpenses();
  }, []);

  const fetchExpenses = async () => {
    setLoading(true);
    try {
      const res = await api.get('/expenses');
      if (res.data && res.data.data) {
        setExpenses(res.data.data);
      }
    } catch (err) {
      console.warn("Failed to fetch expenses", err);
      // Fallback
      setExpenses([
        { id: 1, reference_no: 'EXP-171822001', category_name: 'Rent', expense_date: '2026-06-01T08:00:00Z', total_amount: '2500.00', payment_status: 'paid' },
        { id: 2, reference_no: 'EXP-171822020', category_name: 'Utilities', expense_date: '2026-06-02T10:15:00Z', total_amount: '350.75', payment_status: 'paid' }
      ]);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex flex-col h-full gap-6 animate-in fade-in duration-500">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-pink-500">
            Accounting & Expenses
          </h1>
          <p className="text-text-muted mt-1">Track business expenses and financials.</p>
        </div>
        <button className="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg shadow-lg font-medium transition-colors">
          + Add Expense
        </button>
      </div>

      <div className="glass-card rounded-xl overflow-hidden border border-border">
        <table className="w-full text-left text-sm">
          <thead className="bg-surface/50 border-b border-border">
            <tr>
              <th className="p-4 font-semibold text-text-muted">Date</th>
              <th className="p-4 font-semibold text-text-muted">Reference No.</th>
              <th className="p-4 font-semibold text-text-muted">Category</th>
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
  );
}
