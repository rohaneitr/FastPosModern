"use client";

/**
 * Inventory Master Page — Phase 4 Refactored (Task 4.3-A)
 *
 * BEFORE: 355 lines (God Component)
 *   - Zod schema + types inline
 *   - URL-synced search + pagination state inline
 *   - SWR (inventory + locations) inline
 *   - react-hook-form setup inline
 *   - onTransferSubmit action inline
 *   - InventoryTable (skeleton + empty + rows + badge) inline
 *   - StockTransferModal (5 form fields + validation display) inline
 *
 * AFTER: ~50 lines (pure composition)
 *   - useInventory()         → all state + SWR + form + action
 *   - <InventoryTable>       → skeleton + empty + data rows + StockBadge
 *   - <StockTransferModal>   → 5-field zod form, purely presentational
 *
 * @author  Antigravity AI Agent — Phase 4, Task 4.3-A
 * @version 2026-06-12
 */

import React from "react";
import { Package, ArrowRightLeft, Search } from "lucide-react";
import { useInventory } from "@/features/inventory/hooks/useInventory";
import { InventoryTable } from "@/features/inventory/components/InventoryTable";
import { StockTransferModal } from "@/features/inventory/components/StockTransferModal";

export default function InventoryPage() {
  const inv = useInventory();

  return (
    <div className="p-6 max-w-7xl mx-auto text-slate-900">

      {/* ── Header ──────────────────────────────────────────────────────── */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <Package className="w-6 h-6 text-indigo-600" />
            Inventory Master
          </h1>
          <p className="text-slate-500 text-sm mt-1">Manage stock across all locations.</p>
        </div>

        <div className="flex items-center gap-3 w-full md:w-auto">
          {/* Search */}
          <div className="relative flex-1 md:w-64">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              id="inventory-search"
              type="text"
              placeholder="Search SKU or Name..."
              value={inv.searchTerm}
              onChange={e => inv.setSearchTerm(e.target.value)}
              className="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm"
            />
          </div>

          {/* Stock Transfer trigger */}
          <button
            onClick={() => inv.setIsTransferModalOpen(true)}
            className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors"
          >
            <ArrowRightLeft className="w-4 h-4" />
            Stock Transfer
          </button>
        </div>
      </div>

      {/* ── Inventory Table ──────────────────────────────────────────────── */}
      <InventoryTable
        products={inv.products}
        isLoading={inv.isLoading}
      />

      {/* ── Stock Transfer Modal ─────────────────────────────────────────── */}
      {inv.isTransferModalOpen && (
        <StockTransferModal
          form={inv.form}
          products={inv.products}
          locations={inv.locations}
          onSubmit={inv.onTransferSubmit}
          onClose={inv.closeTransferModal}
        />
      )}
    </div>
  );
}
