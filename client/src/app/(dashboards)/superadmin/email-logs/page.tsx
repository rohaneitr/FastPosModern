'use client';

import React, { useState, useEffect, useCallback } from 'react';
import toast from 'react-hot-toast';

// ─── Types ────────────────────────────────────────────────────────────────────

interface EmailEntry {
  id: number;
  to_email: string;
  subject: string;
  status: 'sent' | 'failed' | 'queued';
  error_message: string | null;
  mailable_class: string | null;
  sent_at: string | null;
  created_at: string;
}

interface Stats {
  total: number;
  sent: number;
  failed: number;
  queued: number;
  last_24h: number;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const STATUS_STYLE: Record<string, string> = {
  sent:   'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  failed: 'bg-rose-500/10 text-rose-400 border-rose-500/20',
  queued: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
};

const STATUS_ICON: Record<string, string> = {
  sent:   '✅',
  failed: '❌',
  queued: '⏳',
};

function shortClass(cls: string | null): string {
  if (!cls) return '—';
  if (cls === 'smtp_test') return 'Test Email';
  const parts = cls.split('\\');
  return parts[parts.length - 1] ?? cls;
}

function timeAgo(iso: string): string {
  const diff  = Date.now() - new Date(iso).getTime();
  const mins  = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days  = Math.floor(diff / 86400000);
  if (mins  < 1)  return 'just now';
  if (hours < 1)  return `${mins}m ago`;
  if (days  < 1)  return `${hours}h ago`;
  return `${days}d ago`;
}

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

// ─── Stat Card ────────────────────────────────────────────────────────────────

function StatCard({ label, value, color }: { label: string; value: number; color: string }) {
  return (
    <div className={`bg-surface/30 border rounded-2xl p-5 flex flex-col gap-1 ${color}`}>
      <p className="text-3xl font-extrabold text-white">{value.toLocaleString()}</p>
      <p className="text-sm font-medium text-text-muted">{label}</p>
    </div>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function EmailLogsPage() {
  const [logs,      setLogs]      = useState<EmailEntry[]>([]);
  const [stats,     setStats]     = useState<Stats | null>(null);
  const [loading,   setLoading]   = useState(true);
  const [search,    setSearch]    = useState('');
  const [status,    setStatus]    = useState('');
  const [page,      setPage]      = useState(1);
  const [lastPage,  setLastPage]  = useState(1);
  const [total,     setTotal]     = useState(0);
  const [expanded,  setExpanded]  = useState<number | null>(null);
  const showToast = (msg: string) => { toast.error(msg); };

  // ── Fetch stats ───────────────────────────────────────────────────────────
  useEffect(() => {
    const load = async () => {
      try {
        const { default: api } = await import('@/lib/api');
        const res = await api.get('/superadmin/email-logs/stats');
        setStats(res.data);
      } catch { /* silent */ }
    };
    load();
  }, []);

  // ── Fetch logs ────────────────────────────────────────────────────────────
  const fetchLogs = useCallback(async () => {
    if (logs.length === 0) setLoading(true);
    try {
      const { default: api } = await import('@/lib/api');
      const params = new URLSearchParams({ page: String(page) });
      if (search.trim()) params.set('search', search.trim());
      if (status)        params.set('status', status);
      const res = await api.get(`/superadmin/email-logs?${params}`);
      setLogs(res.data.logs?.data ?? []);
      setLastPage(res.data.logs?.last_page ?? 1);
      setTotal(res.data.logs?.total ?? 0);
    } catch (err: any) {
      showToast(err?.response?.data?.message ?? 'Failed to load email logs.');
    } finally {
      setLoading(false);
    }
  }, [page, search, status]);

  useEffect(() => { fetchLogs(); }, [fetchLogs]);

  return (


      <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">

        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-sky-400 to-blue-600">
              Email Audit Logs
            </h1>
            <p className="text-text-muted text-sm mt-1">
              Real-time record of all outbound platform emails.
              {total > 0 && <> <span className="text-white font-semibold">{total}</span> total entries.</>}
            </p>
          </div>
          <button
            id="refresh_email_logs_btn"
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

        {/* Stats row */}
        {stats && (
          <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <StatCard label="Total Emails"    value={stats.total}    color="border-border" />
            <StatCard label="Sent"            value={stats.sent}     color="border-emerald-500/20" />
            <StatCard label="Failed"          value={stats.failed}   color="border-rose-500/20" />
            <StatCard label="Queued"          value={stats.queued}   color="border-amber-500/20" />
            <StatCard label="Last 24 Hours"   value={stats.last_24h} color="border-sky-500/20" />
          </div>
        )}

        {/* Filters */}
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1">
            <svg className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
              id="email_log_search"
              type="text"
              value={search}
              onChange={e => { setSearch(e.target.value); setPage(1); }}
              placeholder="Search by email address or subject…"
              className="w-full bg-surface border border-border rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder:text-text-muted/60 outline-none focus:border-sky-500/50 transition-colors"
            />
          </div>
          <select
            id="email_status_filter"
            value={status}
            onChange={e => { setStatus(e.target.value); setPage(1); }}
            className="bg-surface border border-border rounded-xl px-4 py-2.5 text-sm text-white outline-none focus:border-sky-500/50 cursor-pointer transition-colors"
          >
            <option value="">All Statuses</option>
            <option value="sent">✅ Sent</option>
            <option value="failed">❌ Failed</option>
            <option value="queued">⏳ Queued</option>
          </select>
        </div>

        {/* Table */}
        <div className="bg-surface/20 border border-border rounded-2xl overflow-hidden">
          <div className="overflow-x-auto">
            <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">to email</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">subject</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">status</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">error message</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">mailable class</th>
              <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {loading && logs.length === 0 ? (
      [...Array(5)].map((_, i) => (
        <tr key={`skel-${i}`} className="animate-pulse bg-surface/10 border-b border-border/50">
          <td className="px-6 py-5"><div className="h-4 bg-surface rounded w-32"></div></td>
          <td className="px-6 py-5"><div className="h-4 bg-surface rounded w-48"></div></td>
          <td className="px-6 py-5"><div className="h-4 bg-surface rounded w-20"></div></td>
          <td className="px-6 py-5"><div className="h-4 bg-surface rounded w-3/4"></div></td>
          <td className="px-6 py-5"><div className="h-4 bg-surface rounded w-32"></div></td>
          <td className="px-6 py-5 text-right"><div className="h-4 bg-surface rounded w-16 ml-auto"></div></td>
        </tr>
      ))
    ) : logs.length > 0 ? (
      logs.map((item: any, index: number) => (
      <React.Fragment key={item.id || index}>
        <tr className="hover:bg-surface/30 transition-colors group">
          <td className="px-6 py-4 text-white font-medium">{item.to_email}</td>
          <td className="px-6 py-4 text-white font-medium">{item.subject}</td>
          <td className="px-6 py-4 text-white font-medium">{item.status}</td>
          <td className="px-6 py-4 text-white font-medium">{item.error_message}</td>
          <td className="px-6 py-4 text-white font-medium">{item.mailable_class}</td>
          <td className="px-6 py-4 text-right">
            <button onClick={() => setExpanded(expanded === item.id ? null : item.id)} className="text-sky-500 hover:text-sky-400 font-medium text-sm transition-colors">
              {expanded === item.id ? 'Hide' : 'View'}
            </button>
          </td>
        </tr>
        {expanded === item.id && (
          <tr>
            <td colSpan={6} className="px-6 py-4 bg-surface/50 border-t border-border">
              <pre className="text-xs text-text-muted overflow-x-auto">
                {JSON.stringify(item, null, 2)}
              </pre>
            </td>
          </tr>
        )}
      </React.Fragment>
      ))
    ) : (
      <tr>
        <td colSpan={6} className="px-6 py-16 text-center">
          <div className="flex flex-col items-center justify-center text-text-muted">
            <svg className="w-16 h-16 mb-4 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76" /></svg>
            <p className="text-lg font-medium text-white mb-1">No Email Logs Found</p>
            <p className="text-sm">We couldn't find any email transmission records.</p>
          </div>
        </td>
      </tr>
    )}
  </tbody>
</table>
</div>
          </div>
        </div>

        {/* Pagination */}
        {lastPage > 1 && (
          <div className="flex items-center justify-between text-sm">
            <p className="text-text-muted">Page {page} of {lastPage}</p>
            <div className="flex gap-2">
              <button id="email_prev" onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white disabled:opacity-40 transition-colors">
                ← Prev
              </button>
              <button id="email_next" onClick={() => setPage(p => Math.min(lastPage, p + 1))} disabled={page === lastPage}
                className="px-3 py-1.5 rounded-lg border border-border text-text-muted hover:text-white disabled:opacity-40 transition-colors">
                Next →
              </button>
            </div>
          </div>
        )}
      </div>
  );
}
