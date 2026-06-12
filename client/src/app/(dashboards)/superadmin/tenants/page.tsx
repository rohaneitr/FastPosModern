'use client';

/**
 * Tenant Management Page — Phase 4 Refactored
 *
 * BEFORE: 731 lines (God Component)
 *   - 268L state + data fetching + 9 action handlers inline
 *   - 3 full modal UIs inline (ModulesModal ~80L, BillingModal ~60L, CreateModal ~45L)
 *   - Table with all sub-elements inline (~240L)
 *
 * AFTER: ~70 lines (pure composition)
 *   - useTenants()           → all state, data, actions
 *   - <TenantTable>          → table + skeleton + empty state + pagination
 *   - <ManageModulesModal>   → feature toggle modal
 *   - <BillingModal>         → renew/override billing modal
 *   - <CreateTenantModal>    → new tenant provisioning modal
 *   - <BulkMessageModal>     → existing global component (unchanged)
 *
 * @author  Antigravity AI Agent — Phase 4, Task 4.1
 * @version 2026-06-12
 */

import React from 'react';
import BulkMessageModal from '@/components/BulkMessageModal';
import toast from 'react-hot-toast';
import { useTenants } from '@/features/superadmin/tenants/hooks/useTenants';
import { TenantTable } from '@/features/superadmin/tenants/components/TenantTable';
import {
  ManageModulesModal,
  BillingModal,
  CreateTenantModal,
} from '@/features/superadmin/tenants/components/TenantModals';
import type { StatusFilter } from '@/features/superadmin/tenants/types';

export default function SuperadminTenantsPage() {
  const t = useTenants();

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-700 pb-12 relative">

      {/* ── Header ──────────────────────────────────────────────────────── */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-4xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-fuchsia-400 via-purple-400 to-indigo-500 tracking-tight">
            Tenant Management
          </h1>
          <p className="text-text-muted mt-2 text-sm max-w-xl leading-relaxed">
            Oversee all registered businesses, monitor subscription lifecycles, and control platform access seamlessly.
          </p>
        </div>
        <div className="flex gap-3 w-full md:w-auto">
          <button
            onClick={() => window.location.href = '/superadmin/subscriptions'}
            className="flex-1 md:flex-none bg-surface/50 hover:bg-surface text-white border border-border px-5 py-2.5 rounded-xl font-medium transition-all shadow-sm flex items-center justify-center gap-2 group"
          >
            <svg className="w-4 h-4 text-text-muted group-hover:text-fuchsia-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            Manage Packages
          </button>
          <button
            onClick={() => t.setShowBulkMessageModal(true)}
            className="flex-1 md:flex-none bg-primary/20 text-primary hover:bg-primary/30 border border-primary/30 px-5 py-2.5 rounded-xl font-medium transition-all shadow-sm flex items-center justify-center gap-2"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            Send Bulk Message
          </button>
          <button
            onClick={() => t.setShowCreateModal(true)}
            className="flex-1 md:flex-none bg-fuchsia-600 hover:bg-fuchsia-500 text-white shadow-lg shadow-fuchsia-500/20 px-5 py-2.5 rounded-xl font-bold transition-all flex items-center justify-center gap-2 active:scale-95"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" />
            </svg>
            New Tenant
          </button>
        </div>
      </div>

      {/* ── Search & Filter Bar ──────────────────────────────────────────── */}
      <div className="glass-card rounded-2xl border border-border p-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div className="relative w-full sm:max-w-md">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg className="w-5 h-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            type="text"
            id="tenant-search"
            placeholder="Search by business name, owner, or email..."
            value={t.searchTerm}
            onChange={e => t.setSearchTerm(e.target.value)}
            className="w-full bg-background border border-border rounded-xl pl-10 pr-4 py-2.5 text-white outline-none focus:border-fuchsia-500/50 focus:ring-2 focus:ring-fuchsia-500/20 transition-all placeholder:text-text-muted"
          />
        </div>
        <div className="flex bg-background border border-border rounded-xl p-1 w-full sm:w-auto">
          {(['all', 'active', 'suspended'] as StatusFilter[]).map(status => (
            <button
              key={status}
              onClick={() => t.setStatusFilter(status)}
              className={`flex-1 sm:px-6 py-1.5 rounded-lg text-sm font-semibold capitalize transition-all ${
                t.statusFilter === status
                  ? 'bg-surface text-white shadow-sm'
                  : 'text-text-muted hover:text-white hover:bg-surface/50'
              }`}
            >
              {status}
            </button>
          ))}
        </div>
      </div>

      {/* ── Tenant Table ─────────────────────────────────────────────────── */}
      <TenantTable
        tenants={t.tenants}
        isLoading={t.isLoading}
        hasFilters={!!(t.searchTerm || t.statusFilter !== 'all')}
        totalPages={t.totalPages}
        totalItems={t.totalItems}
        currentPage={t.currentPage}
        onPageChange={t.setCurrentPage}
        onClearFilters={t.clearFilters}
        onCopyUrl={url => { navigator.clipboard.writeText(url); toast.success('URL Copied'); }}
        onToggle={t.handleToggle}
        onImpersonate={t.handleImpersonate}
        onExport={t.handleExport}
        onOpenBilling={t.handleOpenBilling}
        onOpenModules={t.handleOpenModules}
        onGenerateLicense={t.handleGenerateLicense}
        onDelete={t.handleDelete}
      />

      {/* ── Modals ───────────────────────────────────────────────────────── */}
      {t.showModulesModal && (
        <ManageModulesModal
          activeModules={t.activeModules}
          onToggleModule={t.setActiveModules}
          onSubmit={t.handleSaveModules}
          onClose={() => t.setShowModulesModal(false)}
          submitting={t.submitting}
        />
      )}

      {t.showBillingModal && (
        <BillingModal
          billingForm={t.billingForm}
          onChangeBillingForm={t.setBillingForm}
          onRenew={t.handleRenewSubscription}
          onOverrideStatus={t.handleOverrideStatus}
          onClose={() => t.setShowBillingModal(false)}
          submitting={t.submitting}
        />
      )}

      {t.showCreateModal && (
        <CreateTenantModal
          form={t.createForm}
          onChange={t.setCreateForm}
          onSubmit={t.handleCreateTenant}
          onClose={() => t.setShowCreateModal(false)}
          plans={t.plans}
          submitting={t.submitting}
        />
      )}

      <BulkMessageModal
        isOpen={t.showBulkMessageModal}
        onClose={() => t.setShowBulkMessageModal(false)}
        users={t.bulkMessageUsers}
      />
    </div>
  );
}
