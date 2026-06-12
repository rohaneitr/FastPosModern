"use client";

/**
 * Customers Page — Phase 4 Refactored (Task 4.3-B)
 *
 * BEFORE: 348 lines (God Component)
 *   - Zod schema + types inline
 *   - URL-synced search + pagination state inline
 *   - SWR customers + form + 3 action handlers inline
 *   - CustomerTable (skeleton + empty + rows + action buttons) inline
 *   - CreateCustomerModal (8-field form + validation) inline
 *   - BUG: URL.revokeObjectURL() missing in exportCSV → memory leak
 *
 * AFTER: ~55 lines (pure composition) + BUG FIXED in useCustomers
 *   - useCustomers()          → all state + SWR + form + 3 actions
 *   - <CustomerTable>         → skeleton + empty + typed rows + delete spinner
 *   - <CreateCustomerModal>   → 8-field form, purely presentational
 *
 * @author  Antigravity AI Agent — Phase 4, Task 4.3-B
 * @version 2026-06-12
 */

import React from "react";
import { Users, Search, Download, Plus, Loader2 } from "lucide-react";
import { useCustomers } from "@/features/crm/hooks/useCustomers";
import { CustomerTable } from "@/features/crm/components/CustomerTable";
import { CreateCustomerModal } from "@/features/crm/components/CreateCustomerModal";

export default function CustomersPage() {
  const c = useCustomers();

  return (
    <div className="p-6 max-w-7xl mx-auto text-slate-900">

      {/* ── Header ──────────────────────────────────────────────────────── */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <Users className="w-6 h-6 text-indigo-600" />
            Customers
          </h1>
          <p className="text-slate-500 text-sm mt-1">Manage your business customers and contacts.</p>
        </div>

        <div className="flex items-center gap-3 w-full md:w-auto">
          {/* Search */}
          <div className="relative flex-1 md:w-64">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              id="customer-search"
              type="text"
              placeholder="Search by name, mobile..."
              value={c.searchTerm}
              onChange={e => c.setSearchTerm(e.target.value)}
              className="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
            />
          </div>

          {/* Export CSV */}
          <button
            onClick={c.exportCSV}
            disabled={c.isExporting}
            className="flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors disabled:opacity-50"
          >
            {c.isExporting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Download className="w-4 h-4" />}
            <span className="hidden sm:inline">{c.isExporting ? 'Processing...' : 'Export'}</span>
          </button>

          {/* Add Customer */}
          <button
            onClick={() => c.setIsModalOpen(true)}
            className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors"
          >
            <Plus className="w-4 h-4" />
            <span className="hidden sm:inline">Add Customer</span>
          </button>
        </div>
      </div>

      {/* ── Customer Table ───────────────────────────────────────────────── */}
      <CustomerTable
        customers={c.customers}
        isLoading={c.isLoading}
        deletingId={c.deletingId}
        onDelete={c.handleDelete}
      />

      {/* ── Create Customer Modal ────────────────────────────────────────── */}
      {c.isModalOpen && (
        <CreateCustomerModal
          form={c.form}
          onSubmit={c.onSubmit}
          onClose={c.closeModal}
        />
      )}
    </div>
  );
}
