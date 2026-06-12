'use client';

import React from 'react';
import type { Tenant, StatusFilter } from '../types';
import { TenantStatusBadges } from './TenantStatusBadges';

// ── Skeleton Row ───────────────────────────────────────────────────────────

function TenantTableSkeleton() {
  return (
    <>
      {Array.from({ length: 5 }).map((_, i) => (
        <tr key={i} className="animate-pulse">
          <td className="px-6 py-5"><div className="h-5 bg-surface rounded-md w-3/4 mb-2" /><div className="h-3 bg-surface rounded-md w-1/2" /></td>
          <td className="px-6 py-5"><div className="h-4 bg-surface rounded-md w-full mb-2" /><div className="h-3 bg-surface rounded-md w-2/3" /></td>
          <td className="px-6 py-5"><div className="h-4 bg-surface rounded-md w-24" /></td>
          <td className="px-6 py-5"><div className="h-4 bg-surface rounded-md w-20" /></td>
          <td className="px-6 py-5"><div className="h-4 bg-surface rounded-md w-full" /></td>
          <td className="px-6 py-5 text-center"><div className="h-6 bg-surface rounded-full w-20 mx-auto" /></td>
          <td className="px-6 py-5 text-right"><div className="h-8 bg-surface rounded-lg w-24 ml-auto" /></td>
        </tr>
      ))}
    </>
  );
}

// ── Empty State ────────────────────────────────────────────────────────────

interface TenantTableEmptyProps {
  hasFilters: boolean;
  onClearFilters: () => void;
}

function TenantTableEmpty({ hasFilters, onClearFilters }: TenantTableEmptyProps) {
  return (
    <tr>
      <td colSpan={7} className="px-6 py-16 text-center">
        <div className="flex flex-col items-center justify-center text-text-muted">
          <svg className="w-16 h-16 mb-4 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
          <p className="text-lg font-medium text-white mb-1">No Tenants Found</p>
          <p className="text-sm">We couldn&apos;t find any businesses matching your filters.</p>
          {hasFilters && (
            <button
              onClick={onClearFilters}
              className="mt-4 text-fuchsia-400 hover:text-fuchsia-300 text-sm font-semibold underline underline-offset-4 transition-colors"
            >
              Clear all filters
            </button>
          )}
        </div>
      </td>
    </tr>
  );
}

// ── Quota Progress Bar ─────────────────────────────────────────────────────

interface QuotaBarProps {
  label: string;
  used: number;
  max: number | null;
  color: string;
}

function QuotaBar({ label, used, max, color }: QuotaBarProps) {
  const pct = max ? Math.min(100, (used / max) * 100) : 0;
  return (
    <div>
      <div className="flex justify-between text-[10px] text-text-muted mb-1">
        <span>{label}</span>
        <span>{used} / {max ?? '∞'}</span>
      </div>
      <div className="h-1.5 w-full bg-surface rounded-full overflow-hidden">
        <div className={`h-full ${color} rounded-full`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}

// ── Action Button ──────────────────────────────────────────────────────────

interface ActionButtonProps {
  onClick: () => void;
  title: string;
  colorClass: string;
  icon: React.ReactNode;
  fullWidth?: boolean;
}

function ActionButton({ onClick, title, colorClass, icon, fullWidth }: ActionButtonProps) {
  return (
    <button
      onClick={onClick}
      title={title}
      className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm ${colorClass} ${fullWidth ? 'w-full flex items-center justify-center gap-2 mb-1' : ''}`}
    >
      {icon}
    </button>
  );
}

// ── TenantTable Row ────────────────────────────────────────────────────────

interface TenantTableRowProps {
  tenant: Tenant;
  onToggle: (id: number) => void;
  onImpersonate: (b: Tenant) => void;
  onExport: (id: number, name: string) => void;
  onOpenBilling: (b: Tenant) => void;
  onOpenModules: (b: Tenant) => void;
  onGenerateLicense: (b: Tenant) => void;
  onDelete: (id: number) => void;
  onCopyUrl: (url: string) => void;
}

function TenantTableRow({
  tenant: b,
  onToggle,
  onImpersonate,
  onExport,
  onOpenBilling,
  onOpenModules,
  onGenerateLicense,
  onDelete,
  onCopyUrl,
}: TenantTableRowProps) {
  const isActive   = Boolean(b.is_active);
  const isLifetime = !b.subscription_expires_at;

  return (
    <tr className={`group hover:bg-surface/30 transition-colors ${!isActive ? 'opacity-60 bg-background/50' : ''}`}>
      {/* Business Details */}
      <td className="px-6 py-4">
        <div className="flex items-center gap-4">
          <div className={`w-12 h-12 rounded-xl flex items-center justify-center font-bold text-xl shadow-inner ${
            isActive
              ? 'bg-gradient-to-br from-indigo-500/20 to-purple-500/20 text-indigo-400 border border-indigo-500/20'
              : 'bg-surface text-text-muted border border-border'
          }`}>
            {b.business_name.charAt(0).toUpperCase()}
          </div>
          <div>
            <div className="font-bold text-base text-white group-hover:text-fuchsia-400 transition-colors">
              {b.business_name}
            </div>
            <div className="text-xs text-text-muted mt-0.5">ID: {b.id}</div>
            {b.url && (
              <div className="flex items-center gap-2 mt-1.5 bg-surface/50 border border-border/50 rounded-md px-2 py-1 w-fit">
                <a href={b.url} target="_blank" rel="noreferrer" className="text-[10px] font-mono text-blue-400 hover:text-blue-300 hover:underline">
                  {b.url}
                </a>
                <button
                  onClick={() => onCopyUrl(b.url!)}
                  className="text-text-muted hover:text-white"
                  title="Copy URL"
                >
                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                  </svg>
                </button>
              </div>
            )}
          </div>
        </div>
      </td>

      {/* Owner Contact */}
      <td className="px-6 py-4">
        <div className="font-medium text-gray-200">{b.owner_name}</div>
        <div className="text-xs text-primary mt-0.5 flex items-center gap-1 cursor-pointer hover:underline">
          <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          {b.owner_email}
        </div>
      </td>

      {/* Registered Date */}
      <td className="px-6 py-4 text-sm text-text-muted font-medium">
        {new Date(b.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}
      </td>

      {/* Subscription */}
      <td className="px-6 py-4">
        {isLifetime ? (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-500/10 text-blue-400 text-xs font-bold border border-blue-500/20">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
            </svg>
            Lifetime
          </span>
        ) : (
          <span className="text-sm font-medium text-text-muted">
            {new Date(b.subscription_expires_at!).toLocaleDateString()}
          </span>
        )}
      </td>

      {/* Quotas */}
      <td className="px-6 py-4">
        <div className="flex flex-col gap-2 w-32">
          <QuotaBar label="Users"     used={b.users_count || 0}     max={b.plan_max_users}     color="bg-fuchsia-500" />
          <QuotaBar label="Locations" used={b.locations_count || 0} max={b.plan_max_locations} color="bg-sky-500" />
        </div>
      </td>

      {/* Status Badges */}
      <td className="px-6 py-4">
        <TenantStatusBadges tenant={b} />
      </td>

      {/* Actions */}
      <td className="px-6 py-4 text-right">
        <div className="flex justify-end gap-1.5 flex-wrap max-w-[250px] ml-auto">
          {b.subscription_status === 'pending_activation' && (
            <button
              onClick={() => onGenerateLicense(b)}
              className="px-3 py-1.5 bg-gradient-to-r from-fuchsia-600 to-indigo-600 hover:from-fuchsia-500 hover:to-indigo-500 text-white rounded-lg text-xs font-bold transition-all shadow-md w-full mb-1 flex items-center justify-center gap-2"
              title="Generate License"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
              </svg>
              Generate License
            </button>
          )}

          {/* Impersonate */}
          <button onClick={() => onImpersonate(b)} title="Impersonate" className="px-3 py-1.5 bg-gradient-to-r from-red-500/10 to-orange-500/10 hover:from-red-500/20 hover:to-orange-500/20 text-red-400 border border-red-500/30 rounded-lg text-xs font-bold transition-all">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
          </button>

          {/* Export */}
          <button onClick={() => onExport(b.id, b.business_name)} title="Export Backup" className="px-3 py-1.5 bg-surface hover:bg-surface/80 text-emerald-400 border border-emerald-500/30 rounded-lg text-xs font-bold transition-all">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
          </button>

          {/* Billing */}
          <button onClick={() => onOpenBilling(b)} title="Billing" className="px-3 py-1.5 bg-surface hover:bg-surface/80 text-blue-400 border border-blue-500/30 rounded-lg text-xs font-bold transition-all">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
          </button>

          {/* Modules */}
          <button onClick={() => onOpenModules(b)} title="Features" className="px-3 py-1.5 bg-surface hover:bg-surface/80 text-fuchsia-400 border border-fuchsia-500/30 rounded-lg text-xs font-bold transition-all">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
          </button>

          {/* Toggle */}
          <button
            onClick={() => onToggle(b.id)}
            title={isActive ? 'Suspend' : 'Activate'}
            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
              isActive
                ? 'bg-surface hover:bg-yellow-500/20 text-text-muted hover:text-yellow-400 border border-border hover:border-yellow-500/30'
                : 'bg-success/10 hover:bg-success/20 text-success-400 border border-success/30'
            }`}
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={isActive ? 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z' : 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z'} />
            </svg>
          </button>

          {/* Delete */}
          <button onClick={() => onDelete(b.id)} title="Hard Delete" className="px-3 py-1.5 bg-danger/10 hover:bg-danger/20 text-danger-400 border border-danger/30 rounded-lg text-xs font-bold transition-all">
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          </button>
        </div>
      </td>
    </tr>
  );
}

// ── TenantTable ────────────────────────────────────────────────────────────

interface TenantTableProps {
  tenants: Tenant[];
  isLoading: boolean;
  hasFilters: boolean;
  totalPages: number;
  totalItems: number;
  currentPage: number;
  onPageChange: (page: number) => void;
  onClearFilters: () => void;
  onCopyUrl: (url: string) => void;
  onToggle: (id: number) => void;
  onImpersonate: (b: Tenant) => void;
  onExport: (id: number, name: string) => void;
  onOpenBilling: (b: Tenant) => void;
  onOpenModules: (b: Tenant) => void;
  onGenerateLicense: (b: Tenant) => void;
  onDelete: (id: number) => void;
}

export function TenantTable({
  tenants,
  isLoading,
  hasFilters,
  totalPages,
  totalItems,
  currentPage,
  onPageChange,
  onClearFilters,
  onCopyUrl,
  ...rowProps
}: TenantTableProps) {
  return (
    <div className="glass-card rounded-2xl overflow-hidden border border-border shadow-xl">
      <div className="overflow-x-auto">
        <table className="w-full text-left whitespace-nowrap">
          <thead className="bg-surface border-b border-border text-xs uppercase tracking-wider font-bold text-text-muted">
            <tr>
              <th className="px-6 py-4">Business Details</th>
              <th className="px-6 py-4">Owner Contact</th>
              <th className="px-6 py-4">Registered Date</th>
              <th className="px-6 py-4">Subscription</th>
              <th className="px-6 py-4">Quotas</th>
              <th className="px-6 py-4 text-center">Status</th>
              <th className="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border/50">
            {isLoading ? (
              <TenantTableSkeleton />
            ) : tenants.length === 0 ? (
              <TenantTableEmpty hasFilters={hasFilters} onClearFilters={onClearFilters} />
            ) : (
              tenants.map(b => (
                <TenantTableRow
                  key={b.id}
                  tenant={b}
                  onCopyUrl={onCopyUrl}
                  {...rowProps}
                />
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination Footer */}
      {totalPages > 1 && !isLoading && (
        <div className="bg-surface/50 border-t border-border px-6 py-4 flex items-center justify-between">
          <span className="text-sm text-text-muted font-medium">
            Showing page <span className="text-white">{currentPage}</span> of{' '}
            <span className="text-white">{totalPages}</span>
            <span className="mx-2 opacity-50">|</span>
            Total: <span className="text-white">{totalItems}</span>
          </span>
          <div className="flex gap-2">
            <button
              disabled={currentPage === 1}
              onClick={() => onPageChange(currentPage - 1)}
              className="px-4 py-2 bg-background border border-border rounded-lg text-sm font-medium hover:bg-surface disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Previous
            </button>
            <button
              disabled={currentPage === totalPages}
              onClick={() => onPageChange(currentPage + 1)}
              className="px-4 py-2 bg-background border border-border rounded-lg text-sm font-medium hover:bg-surface disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
