'use client';

import React, { useState, useEffect, useCallback } from 'react';

// ─── Types ────────────────────────────────────────────────────────────────────

interface AuditEntry {
  id: number;
  causer_name: string | null;
  event: string;
  description: string;
  subject_type: string | null;
  subject_id: number | null;
  subject_label: string | null;
  ip_address: string | null;
  properties: Record<string, any> | null;
  created_at: string;
}

interface AuditResponse {
  logs: {
    data: AuditEntry[];
    current_page: number;
    last_page: number;
    total: number;
  };
  event_types: string[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const EVENT_META: Record<string, { label: string; icon: string; color: string }> = {
  tenant_approved: { label: 'Tenant Approved',  icon: '✅', color: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20' },
  tenant_rejected: { label: 'Tenant Rejected',  icon: '🚫', color: 'text-rose-400 bg-rose-500/10 border-rose-500/20' },
  tenant_deleted:  { label: 'Tenant Deleted',   icon: '🗑️', color: 'text-rose-500 bg-rose-500/15 border-rose-500/30' },
  license_revoked: { label: 'License Revoked',  icon: '🔒', color: 'text-amber-400 bg-amber-500/10 border-amber-500/20' },
  modules_updated: { label: 'Modules Updated',  icon: '🧩', color: 'text-violet-400 bg-violet-500/10 border-violet-500/20' },
};

function eventMeta(event: string) {
  return EVENT_META[event] ?? {
    label: event.replace(/_/g, ' '),
    icon: '📋',
    color: 'text-text-muted bg-surface/50 border-border',
  };
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function timeAgo(iso: string) {
  const diff = Date.now() - new Date(iso).getTime();
  const mins  = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days  = Math.floor(diff / 86400000);
  if (mins < 1)   return 'just now';
  if (hours < 1)  return `${mins}m ago`;
  if (days  < 1)  return `${hours}h ago`;
  return `${days}d ago`;
}

// ─── Properties diff viewer ───────────────────────────────────────────────────

function PropertiesExpander({ entry }: { entry: AuditEntry }) {
  const [open, setOpen] = useState(false);
  if (!entry.properties) return null;
  return (
    <div className="mt-2">
      <button
        onClick={() => setOpen(o => !o)}
        className="text-xs text-text-muted hover:text-white transition-colors flex items-center gap-1"
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
          className={`transition-transform ${open ? 'rotate-90' : ''}`}>
          <polyline points="9 18 15 12 9 6"/>
        </svg>
        {open ? 'Hide' : 'Show'} details
      </button>
      {open && (
        <pre className="mt-2 text-xs bg-background/60 border border-border rounded-lg p-3 text-text-muted overflow-x-auto max-h-40">
          {JSON.stringify(entry.properties, null, 2)}
        </pre>
      )}
    </div>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function AuditLogsPage() {
  const [data, setData]             = useState<AuditResponse | null>(null);
  const [loading, setLoading]       = useState(true);
  const [search, setSearch]         = useState('');
  const [eventFilter, setEventFilter] = useState('');
  const [page, setPage]             = useState(1);
  const [toast, setToast]           = useState<string | null>(null);

  const showToast = (msg: string) => { setToast(msg); setTimeout(() => setToast(null), 3000); };

  // ── Fetch ──────────────────────────────────────────────────────────────────
  const fetchLogs = useCallback(async () => {
    setLoading(true);
    try {
      const { default: api } = await import('@/lib/api');
      const params = new URLSearchParams({ page: String(page) });
      if (search.trim())  params.set('search', search.trim());
      if (eventFilter)    params.set('event', eventFilter);
      const res = await api.get(`/superadmin/audit-logs?${params}`);
      setData(res.data);
    } catch (err: any) {
      showToast(err?.response?.data?.message ?? 'Failed to load audit logs.');
    } finally {
      setLoading(false);
    }
  }, [page, search, eventFilter]);

  useEffect(() => { fetchLogs(); }, [fetchLogs]);

  const logs       = data?.logs?.data ?? [];
  const lastPage   = data?.logs?.last_page ?? 1;
  const total      = data?.logs?.total ?? 0;
  const eventTypes = data?.event_types ?? [];

  return (
    <>
      {toast && (
        <div className="fixed top-6 right-6 z-50 px-5 py-3 rounded-xl text-sm font-semibold bg-rose-500/15 border border-rose-500/30 text-rose-300 shadow-2xl animate-in slide-in-from-top-4 duration-300">
          ❌ {toast}
        </div>
      )}

      <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-slate-300 to-slate-500">
              Audit Logs
            </h1>
            <p className="text-text-muted text-sm mt-1">
              Immutable record of all SuperAdmin actions. {total > 0 && <span className="text-white font-semibold">{total} total entries.</span>}
            </p>
          </div>
          <button
            id="refresh_audit_btn"
            onClick={fetchLogs}
            disabled={loading}
            className="shrink-0 px-4 py-2 rounded-xl text-sm font-semibold border border-border text-text-muted hover:text-white transition-colors flex items-center gap-2"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"
              className={loading ? 'animate-spin' : ''}>
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
            <svg className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
              id="audit_search_input"
              type="text"
              value={search}
              onChange={e => { setSearch(e.target.value); setPage(1); }}
              placeholder="Search by actor, action, or tenant…"
              className="w-full bg-surface border border-border rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder:text-text-muted/60 outline-none focus:border-slate-500/60 transition-colors"
            />
          </div>

          {/* Event type filter */}
          <select
            id="audit_event_filter"
            value={eventFilter}
            onChange={e => { setEventFilter(e.target.value); setPage(1); }}
            className="bg-surface border border-border rounded-xl px-4 py-2.5 text-sm text-white outline-none focus:border-slate-500/60 cursor-pointer transition-colors"
          >
            <option value="">All Events</option>
            {eventTypes.map(et => (
              <option key={et} value={et}>{eventMeta(et).icon} {eventMeta(et).label}</option>
            ))}
          </select>
        </div>

        {/* Log feed */}
        <div className="flex flex-col gap-3">
          {loading ? (
            <div className="flex items-center justify-center py-20 gap-3 text-text-muted">
              <span className="w-5 h-5 border-2 border-slate-500/30 border-t-slate-400 rounded-full animate-spin" />
              Loading audit trail…
            </div>
          ) : logs.length === 0 ? (
            <div className="flex flex-col items-center py-20 gap-4 text-text-muted">
              <span className="text-5xl opacity-30">📋</span>
              <div className="text-center">
                <p className="font-semibold text-white">No audit entries found</p>
                <p className="text-sm mt-1">Actions performed by SuperAdmins will appear here.</p>
              </div>
            </div>
          ) : (
            logs.map(entry => {
              const meta = eventMeta(entry.event);
              return (
                <div key={entry.id} className="bg-surface/30 border border-border/60 rounded-2xl p-4 hover:border-border transition-colors">
                  <div className="flex items-start gap-4">
                    {/* Event badge */}
                    <div className={`shrink-0 w-9 h-9 rounded-xl flex items-center justify-center text-base border ${meta.color}`}>
                      {meta.icon}
                    </div>

                    {/* Content */}
                    <div className="flex-1 min-w-0">
                      <div className="flex flex-wrap items-center gap-2 mb-1">
                        <span className={`px-2.5 py-0.5 rounded-lg text-xs font-bold border ${meta.color}`}>
                          {meta.label}
                        </span>
                        {entry.subject_type && (
                          <span className="px-2 py-0.5 rounded-lg text-xs font-medium bg-surface/60 text-text-muted border border-border">
                            {entry.subject_type} #{entry.subject_id}
                            {entry.subject_label && ` — ${entry.subject_label}`}
                          </span>
                        )}
                      </div>

                      <p className="text-sm text-white mt-1">{entry.description}</p>

                      <div className="flex flex-wrap items-center gap-4 mt-2 text-xs text-text-muted">
                        <span title={formatDate(entry.created_at)}>
                          🕐 {timeAgo(entry.created_at)} — {formatDate(entry.created_at)}
                        </span>
                        {entry.causer_name && (
                          <span>👤 {entry.causer_name}</span>
                        )}
                        {entry.ip_address && (
                          <span>🌐 {entry.ip_address}</span>
                        )}
                      </div>

                      <PropertiesExpander entry={entry} />
                    </div>
                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Pagination */}
        {lastPage > 1 && (
          <div className="flex items-center justify-between text-sm">
            <p className="text-text-muted">Page {page} of {lastPage} — {total} entries</p>
            <div className="flex items-center gap-2">
              <button
                id="audit_prev_btn"
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white disabled:opacity-40 transition-colors"
              >
                ← Prev
              </button>
              <button
                id="audit_next_btn"
                onClick={() => setPage(p => Math.min(lastPage, p + 1))}
                disabled={page === lastPage}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white disabled:opacity-40 transition-colors"
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
