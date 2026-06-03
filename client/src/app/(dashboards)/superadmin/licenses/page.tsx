'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

interface License {
  id: number;
  business_name: string;
  business_id: number;
  plan_name: string;
  status: string;
  trial_ends_at: string | null;
  current_period_end: string | null;
}

export default function SuperadminLicenses() {
  const [licenses, setLicenses] = useState<License[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => { fetchLicenses(); }, []);

  const fetchLicenses = async () => {
    setLoading(true);
    try {
      // Reuse the businesses endpoint — each business has a subscription which acts as its "license"
      const res = await api.get('/superadmin/businesses');
      const businesses = res.data?.data || res.data || [];
      setLicenses(Array.isArray(businesses) ? businesses : []);
    } catch {
      setLicenses([]);
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (biz: any) => {
    if (!biz.is_active) return { label: 'Suspended', cls: 'bg-danger/20 text-danger' };
    return { label: 'Active', cls: 'bg-success/20 text-success' };
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-500">
            License Keys &amp; Business Subscriptions
          </h1>
          <p className="text-text-muted mt-1">View all active tenant licenses and subscription statuses.</p>
        </div>
      </div>

      {/* Stats Row */}
      <div className="grid grid-cols-3 gap-4">
        <div className="glass-card rounded-xl border border-border p-5">
          <p className="text-text-muted text-sm">Total Tenants</p>
          <p className="text-3xl font-black text-white mt-1">{licenses.length}</p>
        </div>
        <div className="glass-card rounded-xl border border-border p-5">
          <p className="text-text-muted text-sm">Active</p>
          <p className="text-3xl font-black text-success mt-1">{licenses.filter((b: any) => b.is_active).length}</p>
        </div>
        <div className="glass-card rounded-xl border border-border p-5">
          <p className="text-text-muted text-sm">Suspended</p>
          <p className="text-3xl font-black text-danger mt-1">{licenses.filter((b: any) => !b.is_active).length}</p>
        </div>
      </div>

      {/* License Table */}
      <div className="glass-card rounded-xl border border-border overflow-hidden">
        {loading ? (
          <div className="text-center p-12 text-text-muted">Loading licenses...</div>
        ) : licenses.length === 0 ? (
          <div className="text-center p-12 text-text-muted">No businesses registered yet.</div>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-text-muted">
                <th className="p-4 font-medium">Business</th>
                <th className="p-4 font-medium">Owner</th>
                <th className="p-4 font-medium">Status</th>
                <th className="p-4 font-medium">Created</th>
              </tr>
            </thead>
            <tbody>
              {licenses.map((biz: any) => {
                const badge = getStatusBadge(biz);
                return (
                  <tr key={biz.id} className="border-b border-border/50 hover:bg-white/[0.02] transition-colors">
                    <td className="p-4 font-bold text-white">{biz.business_name || biz.name}</td>
                    <td className="p-4">
                      <span className="text-text-muted">{biz.owner_email || '—'}</span>
                    </td>
                    <td className="p-4">
                      <span className={`px-2.5 py-1 rounded-full text-xs font-bold uppercase ${badge.cls}`}>{badge.label}</span>
                    </td>
                    <td className="p-4 text-text-muted">{biz.created_at ? new Date(biz.created_at).toLocaleDateString() : '—'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
