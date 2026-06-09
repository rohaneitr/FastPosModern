'use client';

import React, { useState, useEffect, useCallback } from 'react';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Plan {
  id: number;
  name: string;
  plan_type?: string;
  price: number;
}

interface Reviewer {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
}

interface TenantRequest {
  id: number;
  tenant_id: number | null;
  business_name: string;
  type: 'web' | 'hybrid' | 'mobile';
  plan_id: number;
  transaction_id: string | null;
  kyc_docs: Record<string, string>[] | null;
  status: 'pending' | 'approved' | 'rejected';
  reviewed_by: number | null;
  reviewed_at: string | null;
  rejection_reason: string | null;
  created_at: string;
  plan: Plan | null;
  reviewer: Reviewer | null;
}

interface PaginatedResponse {
  data: TenantRequest[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const TYPE_BADGE: Record<string, string> = {
  web:    'bg-blue-500/15 text-blue-400 border-blue-500/30',
  hybrid: 'bg-violet-500/15 text-violet-400 border-violet-500/30',
  mobile: 'bg-cyan-500/15 text-cyan-400 border-cyan-500/30',
};

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

// ─── Reject Modal ─────────────────────────────────────────────────────────────

function RejectModal({
  request,
  onClose,
  onConfirm,
  loading,
}: {
  request: TenantRequest;
  onClose: () => void;
  onConfirm: (reason: string) => void;
  loading: boolean;
}) {
  const [reason, setReason] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (reason.trim().length >= 10) onConfirm(reason.trim());
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="relative w-full max-w-lg bg-surface border border-rose-500/30 rounded-2xl shadow-2xl shadow-rose-500/10 p-6 z-10">
        {/* Header */}
        <div className="flex items-start gap-4 mb-5">
          <div className="w-10 h-10 rounded-xl bg-rose-500/15 border border-rose-500/30 flex items-center justify-center text-lg shrink-0">
            🚫
          </div>
          <div>
            <h2 className="text-lg font-bold text-white">Reject Tenant Request</h2>
            <p className="text-sm text-text-muted mt-0.5">
              <span className="text-white font-semibold">{request.business_name}</span>
              {' '}— {request.plan?.name} Plan
            </p>
          </div>
          <button
            onClick={onClose}
            className="ml-auto text-text-muted hover:text-white transition-colors"
            aria-label="Close"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <div>
            <label className="block text-sm font-semibold text-text-muted mb-2">
              Rejection Reason <span className="text-rose-400">*</span>
            </label>
            <textarea
              id="rejection_reason_input"
              rows={4}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Explain why this request is being rejected (min. 10 characters)..."
              className="w-full bg-background border border-border rounded-xl px-4 py-3 text-sm text-white placeholder:text-text-muted/50 outline-none focus:border-rose-500/60 resize-none transition-colors"
              required
              minLength={10}
            />
            <p className="text-xs text-text-muted mt-1">{reason.length} / 1000 chars</p>
          </div>

          <div className="flex gap-3 justify-end">
            <button
              type="button"
              onClick={onClose}
              className="px-5 py-2 rounded-xl text-sm font-semibold text-text-muted border border-border hover:text-white hover:border-border/80 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              id="confirm_rejection_btn"
              disabled={loading || reason.trim().length < 10}
              className="px-5 py-2 rounded-xl text-sm font-bold bg-rose-500 hover:bg-rose-600 text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              {loading ? (
                <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              ) : '🚫'}
              Confirm Rejection
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function ApprovalsPage() {
  const [data, setData]           = useState<PaginatedResponse | null>(null);
  const [loading, setLoading]     = useState(true);
  const [actionId, setActionId]   = useState<number | null>(null);
  const [rejectTarget, setRejectTarget] = useState<TenantRequest | null>(null);
  const [toast, setToast]         = useState<{ type: 'success' | 'error'; msg: string } | null>(null);
  const [statusFilter, setStatusFilter] = useState('pending');
  const [search, setSearch]       = useState('');
  const [page, setPage]           = useState(1);

  // ── Toast helper ───────────────────────────────────────────────────────────
  const showToast = (type: 'success' | 'error', msg: string) => {
    setToast({ type, msg });
    setTimeout(() => setToast(null), 4000);
  };

  // ── Fetch ──────────────────────────────────────────────────────────────────
  const fetchRequests = useCallback(async () => {
    setLoading(true);
    try {
      const { default: api } = await import('@/lib/api');
      const params = new URLSearchParams({ page: String(page) });
      if (statusFilter) params.set('status', statusFilter);
      if (search.trim()) params.set('search', search.trim());

      const res = await api.get(`/superadmin/tenant-requests?${params}`);
      setData(res.data);
    } catch (err: any) {
      showToast('error', err?.response?.data?.message ?? 'Failed to load approval queue.');
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter, search]);

  useEffect(() => { fetchRequests(); }, [fetchRequests]);

  // ── Approve ────────────────────────────────────────────────────────────────
  const handleApprove = async (id: number) => {
    setActionId(id);
    try {
      const { default: api } = await import('@/lib/api');
      await api.post(`/superadmin/tenant-requests/${id}/approve`);
      showToast('success', 'Tenant approved and provisioned successfully.');
      fetchRequests();
    } catch (err: any) {
      showToast('error', err?.response?.data?.message ?? 'Approval failed.');
    } finally {
      setActionId(null);
    }
  };

  // ── Reject ─────────────────────────────────────────────────────────────────
  const handleReject = async (reason: string) => {
    if (!rejectTarget) return;
    setActionId(rejectTarget.id);
    try {
      const { default: api } = await import('@/lib/api');
      await api.post(`/superadmin/tenant-requests/${rejectTarget.id}/reject`, {
        rejection_reason: reason,
      });
      showToast('success', 'Request rejected.');
      setRejectTarget(null);
      fetchRequests();
    } catch (err: any) {
      showToast('error', err?.response?.data?.message ?? 'Rejection failed.');
    } finally {
      setActionId(null);
    }
  };

  // ─────────────────────────────────────────────────────────────────────────
  // Render
  // ─────────────────────────────────────────────────────────────────────────

  const pendingCount = data?.data.filter(r => r.status === 'pending').length ?? 0;

  return (
    <>
      {/* ── Toast ────────────────────────────────────────────────────────── */}
      {toast && (
        <div
          className={`fixed top-6 right-6 z-50 flex items-center gap-3 px-5 py-3 rounded-xl text-sm font-semibold shadow-2xl border animate-in slide-in-from-top-4 duration-300 ${
            toast.type === 'success'
              ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300'
              : 'bg-rose-500/15 border-rose-500/30 text-rose-300'
          }`}
        >
          <span>{toast.type === 'success' ? '✅' : '❌'}</span>
          {toast.msg}
        </div>
      )}

      {/* ── Reject Modal ─────────────────────────────────────────────────── */}
      {rejectTarget && (
        <RejectModal
          request={rejectTarget}
          onClose={() => setRejectTarget(null)}
          onConfirm={handleReject}
          loading={actionId === rejectTarget.id}
        />
      )}

      {/* ── Page ─────────────────────────────────────────────────────────── */}
      <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-3 mb-1">
              <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-500">
                Pending Approvals
              </h1>
              {pendingCount > 0 && (
                <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 animate-pulse">
                  {pendingCount} pending
                </span>
              )}
            </div>
            <p className="text-text-muted text-sm">
              Gate 1 KYC queue — review and approve new tenant onboarding requests.
            </p>
          </div>
          <button
            id="refresh_approvals_btn"
            onClick={fetchRequests}
            disabled={loading}
            className="shrink-0 px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white hover:border-amber-500/40 transition-colors flex items-center gap-2"
          >
            <svg
              width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"
              className={loading ? 'animate-spin' : ''}
            >
              <polyline points="23 4 23 10 17 10"/>
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
            </svg>
            Refresh
          </button>
        </div>

        {/* Filters */}
        <div className="flex flex-col sm:flex-row gap-3">
          {/* Search */}
          <div className="relative flex-1">
            <svg
              className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted"
              width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
            >
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
              id="approvals_search_input"
              type="text"
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
              placeholder="Search by business name…"
              className="w-full bg-surface border border-border rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder:text-text-muted/60 outline-none focus:border-amber-500/50 transition-colors"
            />
          </div>

          {/* Status filter */}
          <div className="flex gap-2">
            {(['pending', 'approved', 'rejected', ''] as const).map((s) => (
              <button
                key={s || 'all'}
                id={`filter_${s || 'all'}_btn`}
                onClick={() => { setStatusFilter(s); setPage(1); }}
                className={`px-4 py-2.5 rounded-xl text-sm font-semibold border transition-colors capitalize ${
                  statusFilter === s
                    ? s === 'pending'
                      ? 'bg-amber-500/20 border-amber-500/40 text-amber-400'
                      : s === 'approved'
                      ? 'bg-emerald-500/20 border-emerald-500/40 text-emerald-400'
                      : s === 'rejected'
                      ? 'bg-rose-500/20 border-rose-500/40 text-rose-400'
                      : 'bg-surface border-border text-white'
                    : 'border-border text-text-muted hover:text-white hover:border-border/80'
                }`}
              >
                {s || 'All'}
              </button>
            ))}
          </div>
        </div>

        {/* Table card */}
        <div className="bg-surface/30 border border-border rounded-2xl overflow-hidden">
          {loading ? (
            <div className="flex items-center justify-center py-20 gap-3 text-text-muted">
              <span className="w-5 h-5 border-2 border-amber-500/30 border-t-amber-400 rounded-full animate-spin" />
              Loading approval queue…
            </div>
          ) : !data?.data.length ? (
            <div className="flex flex-col items-center justify-center py-20 gap-4 text-text-muted">
              <span className="text-5xl opacity-40">🛡️</span>
              <div className="text-center">
                <p className="font-semibold text-white">No requests found</p>
                <p className="text-sm mt-1">
                  {statusFilter === 'pending' ? 'The approval queue is clear.' : 'Try a different filter.'}
                </p>
              </div>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">Business Name</th>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">App Type</th>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">Plan</th>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">Status</th>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">Date</th>
      <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {(data?.data || [])?.length > 0 ? (
      (data?.data || []).map((item, index) => (
      <tr key={item.id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.business_name}</td>
        <td className="px-6 py-4 text-white font-medium capitalize">{item.type}</td>
        <td className="px-6 py-4 text-white font-medium">{item.plan?.name || 'N/A'} - ${item.plan?.price || 0}</td>
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
              <button disabled={actionId === item.id} onClick={() => setRejectTarget(item)} className="text-rose-500 hover:text-rose-400 font-medium text-sm disabled:opacity-50">Reject</button>
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

        {/* Pagination */}
        {data && data.last_page > 1 && (
          <div className="flex items-center justify-between text-sm">
            <p className="text-text-muted">
              Showing {data.data.length} of {data.total} requests
            </p>
            <div className="flex items-center gap-2">
              <button
                id="prev_page_btn"
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white hover:border-amber-500/40 disabled:opacity-40 transition-colors"
              >
                ← Prev
              </button>
              <span className="px-3 py-1.5 text-white font-semibold">
                {page} / {data.last_page}
              </span>
              <button
                id="next_page_btn"
                onClick={() => setPage(p => Math.min(data.last_page, p + 1))}
                disabled={page === data.last_page}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white hover:border-amber-500/40 disabled:opacity-40 transition-colors"
              >
                Next →
              </button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
