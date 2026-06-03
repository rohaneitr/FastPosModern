'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function SuperadminSubscriptions() {
  const [plans, setPlans] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState({ name: '', price: '0', interval: 'month', max_users: '1', max_locations: '1' });

  useEffect(() => { fetchPlans(); }, []);

  const fetchPlans = async () => {
    setLoading(true);
    try {
      const res = await api.get('/superadmin/plans');
      setPlans(Array.isArray(res.data) ? res.data : []);
    } catch { setPlans([]); }
    finally { setLoading(false); }
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post('/superadmin/plans', {
        ...form,
        price: parseFloat(form.price),
        max_users: parseInt(form.max_users),
        max_locations: parseInt(form.max_locations),
      });
      setShowModal(false);
      setForm({ name: '', price: '0', interval: 'month', max_users: '1', max_locations: '1' });
      fetchPlans();
    } catch { alert('Failed to create plan'); }
    finally { setSubmitting(false); }
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-rose-400 to-orange-500">
            Subscriptions &amp; Billing
          </h1>
          <p className="text-text-muted mt-1">Manage SaaS packages, pricing tiers, and active subscriptions.</p>
        </div>
        <button onClick={() => setShowModal(true)} className="bg-gradient-to-r from-rose-500 to-orange-600 hover:from-rose-600 hover:to-orange-700 text-white px-6 py-2.5 rounded-xl shadow-lg shadow-rose-500/25 font-bold transition-all active:scale-[0.98]">
          + Create Plan
        </button>
      </div>

      {/* Plans Grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {loading ? (
          <div className="col-span-3 text-center p-12 text-text-muted">Loading plans...</div>
        ) : plans.length === 0 ? (
          <div className="col-span-3 glass-card rounded-xl border border-border p-12 text-center text-text-muted">
            No plans configured yet. Create your first plan above.
          </div>
        ) : (
          plans.map((plan: any) => (
            <div key={plan.id} className="glass-card rounded-xl border border-border p-6 flex flex-col gap-4 hover:border-rose-500/50 transition-colors">
              <div className="flex justify-between items-start">
                <h3 className="text-xl font-bold text-white">{plan.name}</h3>
                <span className={`px-2 py-0.5 rounded-full text-xs font-bold uppercase ${plan.is_active !== false ? 'bg-success/20 text-success' : 'bg-danger/20 text-danger'}`}>
                  {plan.is_active !== false ? 'Active' : 'Inactive'}
                </span>
              </div>
              <div className="flex items-baseline gap-1">
                <span className="text-4xl font-black text-white">${parseFloat(plan.price).toFixed(0)}</span>
                <span className="text-text-muted text-sm">/{plan.interval}</span>
              </div>
              <div className="border-t border-border pt-4 flex flex-col gap-2 text-sm">
                <div className="flex justify-between"><span className="text-text-muted">Max Users</span><span className="font-bold">{plan.max_users >= 999 ? 'Unlimited' : plan.max_users}</span></div>
                <div className="flex justify-between"><span className="text-text-muted">Max Locations</span><span className="font-bold">{plan.max_locations >= 999 ? 'Unlimited' : plan.max_locations}</span></div>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Create Plan Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-surface border border-border w-full max-w-md rounded-2xl shadow-2xl p-6 relative">
            <button onClick={() => setShowModal(false)} className="absolute top-4 right-4 text-text-muted hover:text-white">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <h2 className="text-2xl font-bold text-white mb-6">Create New Plan</h2>
            <form onSubmit={handleCreate} className="flex flex-col gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Plan Name *</label>
                <input required value={form.name} onChange={e => setForm({...form, name: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-rose-500/50" placeholder="e.g. Pro" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Price (USD) *</label>
                  <input type="number" step="0.01" required value={form.price} onChange={e => setForm({...form, price: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-rose-500/50" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Billing Interval</label>
                  <select value={form.interval} onChange={e => setForm({...form, interval: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-rose-500/50">
                    <option value="month">Monthly</option>
                    <option value="year">Yearly</option>
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Max Users</label>
                  <input type="number" min="1" value={form.max_users} onChange={e => setForm({...form, max_users: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-rose-500/50" />
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Max Locations</label>
                  <input type="number" min="1" value={form.max_locations} onChange={e => setForm({...form, max_locations: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-rose-500/50" />
                </div>
              </div>
              <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={() => setShowModal(false)} className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white font-medium">Cancel</button>
                <button type="submit" disabled={submitting} className="bg-rose-500 hover:bg-rose-600 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg disabled:opacity-50">
                  {submitting ? 'Creating...' : 'Create Plan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
