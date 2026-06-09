'use client';

import React, { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';

interface Plan {
  id: number;
  name: string;
  price: number;
  interval: string;
}

interface Business {
  id: number;
  name: string;
}

interface Reviewer {
  id: number;
  first_name: string;
  last_name: string;
}

interface SubscriptionRequest {
  id: number;
  business_id: number;
  plan_id: number;
  status: 'pending' | 'approved' | 'rejected';
  payment_method: string | null;
  transaction_reference: string | null;
  notes: string | null;
  reviewed_by: number | null;
  reviewed_at: string | null;
  created_at: string;
  business: Business;
  plan: Plan;
  reviewer: Reviewer | null;
}

interface PaginatedResponse {
  data: SubscriptionRequest[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

const STATUS_BADGE: Record<string, string> = {
  pending:  'bg-amber-500/15 text-amber-400 border-amber-500/30',
  approved: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
  rejected: 'bg-rose-500/15 text-rose-400 border-rose-500/30',
};

const STATUS_DOT: Record<string, string> = {
  pending:  'bg-amber-400',
  approved: 'bg-emerald-400',
  rejected: 'bg-rose-400',
};

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

export default function SubscriptionRequestsPage() {
  const [data, setData] = useState<PaginatedResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionId, setActionId] = useState<number | null>(null);
  const [toast, setToast] = useState<{ type: 'success' | 'error'; msg: string } | null>(null);
  const [statusFilter, setStatusFilter] = useState('pending');
  const [page, setPage] = useState(1);
  const [rejectModalId, setRejectModalId] = useState<number | null>(null);
  const [rejectionNotes, setRejectionNotes] = useState('');

  const showToast = (type: 'success' | 'error', msg: string) => {
    setToast({ type, msg });
    setTimeout(() => setToast(null), 4000);
  };

  const fetchRequests = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(page) });
      if (statusFilter) params.set('status', statusFilter);

      const res = await api.get(`/superadmin/subscription-requests?${params}`);
      setData(res.data);
    } catch (err: any) {
      showToast('error', err?.response?.data?.message ?? 'Failed to load requests.');
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter]);

  useEffect(() => { fetchRequests(); }, [fetchRequests]);

  const handleApprove = async (id: number) => {
    setActionId(id);
    try {
      await api.post(`/superadmin/subscription-requests/${id}/approve`);
      showToast('success', 'Subscription approved and provisioned.');
      fetchRequests();
    } catch (err: any) {
      showToast('error', err?.response?.data?.message ?? 'Approval failed.');
    } finally {
      setActionId(null);
    }
  };

  const handleReject = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!rejectModalId) return;
    setActionId(rejectModalId);
    try {
      await api.post(`/superadmin/subscription-requests/${rejectModalId}/reject`, {
        notes: rejectionNotes,
      });
      showToast('success', 'Request rejected.');
      setRejectModalId(null);
      setRejectionNotes('');
      fetchRequests();
    } catch (err: any) {
      showToast('error', err?.response?.data?.message ?? 'Rejection failed.');
    } finally {
      setActionId(null);
    }
  };

  return (
    <>
      {toast && (
        <div className={`fixed top-6 right-6 z-50 flex items-center gap-3 px-5 py-3 rounded-xl text-sm font-semibold shadow-2xl border animate-in slide-in-from-top-4 duration-300 ${
            toast.type === 'success' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/15 border-rose-500/30 text-rose-300'
        }`}>
          <span>{toast.type === 'success' ? '✅' : '❌'}</span>
          {toast.msg}
        </div>
      )}

      {rejectModalId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={() => setRejectModalId(null)} />
          <div className="relative w-full max-w-lg bg-surface border border-rose-500/30 rounded-2xl p-6 z-10">
            <h2 className="text-lg font-bold text-white mb-4">Reject Request</h2>
            <form onSubmit={handleReject} className="flex flex-col gap-4">
              <textarea
                value={rejectionNotes}
                onChange={(e) => setRejectionNotes(e.target.value)}
                placeholder="Reason for rejection (optional)..."
                className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm text-white resize-none"
                rows={4}
              />
              <div className="flex gap-3 justify-end mt-2">
                <button type="button" onClick={() => setRejectModalId(null)} className="px-5 py-2 rounded-xl text-sm font-semibold text-text-muted border border-border hover:text-white transition-colors">
                  Cancel
                </button>
                <button type="submit" disabled={actionId === rejectModalId} className="px-5 py-2 rounded-xl text-sm font-bold bg-rose-500 hover:bg-rose-600 text-white transition-colors disabled:opacity-50">
                  {actionId === rejectModalId ? 'Rejecting...' : 'Confirm Reject'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-white mb-1">Subscription Requests</h1>
            <p className="text-text-muted text-sm">Review tenant requests for offline subscription upgrades.</p>
          </div>
          <button onClick={fetchRequests} disabled={loading} className="shrink-0 px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white transition-colors">
            {loading ? 'Refreshing...' : 'Refresh'}
          </button>
        </div>

        <div className="flex gap-2">
          {(['pending', 'approved', 'rejected', ''] as const).map((s) => (
            <button
              key={s || 'all'}
              onClick={() => { setStatusFilter(s); setPage(1); }}
              className={`px-4 py-2.5 rounded-xl text-sm font-semibold border transition-colors capitalize ${
                statusFilter === s
                  ? s === 'pending' ? 'bg-amber-500/20 border-amber-500/40 text-amber-400'
                    : s === 'approved' ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-400'
                    : s === 'rejected' ? 'bg-rose-500/20 border-rose-500/40 text-rose-400'
                    : 'bg-surface border-border text-white'
                  : 'border-border text-text-muted hover:text-white hover:border-border/80'
              }`}
            >
              {s || 'All'}
            </button>
          ))}
        </div>

        <div className="bg-surface/30 border border-border rounded-2xl overflow-hidden">
          {loading ? (
            <div className="flex items-center justify-center py-20 text-text-muted">Loading requests…</div>
          ) : !data?.data.length ? (
            <div className="flex flex-col items-center justify-center py-20 text-text-muted">
              <span className="text-5xl opacity-40 mb-4">📥</span>
              <p className="font-semibold text-white">No requests found</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">Business Name</th>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">Plan</th>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">Price / Interval</th>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">Status</th>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">Date</th>
      <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {(data?.data || [])?.length > 0 ? (
      (data?.data || []).map((item, index) => (
      <tr key={item.id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.business?.name || `Business #${item.business_id}`}</td>
        <td className="px-6 py-4 text-white font-medium">{item.plan?.name || `Plan #${item.plan_id}`}</td>
        <td className="px-6 py-4 text-white font-medium">${item.plan?.price || 0} / {item.plan?.interval || 'N/A'}</td>
        <td className="px-6 py-4">
          <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold border ${STATUS_BADGE[item.status] || ''}`}>
            {item.status}
          </span>
        </td>
        <td className="px-6 py-4 text-text-muted text-sm">{formatDate(item.created_at)}</td>
        <td className="px-6 py-4 text-right flex justify-end gap-3 items-center">
          {item.status === 'pending' ? (
            <>
              <button disabled={actionId === item.id} onClick={() => handleApprove(item.id)} className="text-emerald-500 hover:text-emerald-400 font-medium text-sm disabled:opacity-50">Approve</button>
              <button disabled={actionId === item.id} onClick={() => setRejectModalId(item.id)} className="text-rose-500 hover:text-rose-400 font-medium text-sm disabled:opacity-50">Reject</button>
            </>
          ) : (
            <span className="text-text-muted text-sm italic">Resolved</span>
          )}
        </td>
      </tr>
    ))) : (
      <tr>
        <td colSpan={6} className="px-6 py-8 text-center text-text-muted">No records found.</td>
      </tr>
    )}
  </tbody>
</table>
</div>
            </div>
          )}
        </div>
        {data && data.last_page > 1 && (
          <div className="flex items-center justify-between text-sm">
             <p className="text-text-muted">Showing {data.data.length} of {data.total}</p>
             <div className="flex gap-2">
               <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1 text-text-muted hover:text-white border border-border rounded-lg disabled:opacity-50">Prev</button>
               <span className="px-3 py-1 text-white">{page} / {data.last_page}</span>
               <button onClick={() => setPage(p => Math.min(data.last_page, p + 1))} disabled={page === data.last_page} className="px-3 py-1 text-text-muted hover:text-white border border-border rounded-lg disabled:opacity-50">Next</button>
             </div>
          </div>
        )}
      </div>
    </>
  );
}
