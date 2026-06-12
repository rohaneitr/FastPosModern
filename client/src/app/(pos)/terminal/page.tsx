"use client";

/**
 * POS Terminal Page — Phase 4 Refactored
 *
 * BEFORE: 572 lines (God Component)
 *   - 210L state/effects/sync-engine/actions inline
 *   - Product grid + skeleton + error + empty states inline (~100L)
 *   - Cart panel + quantity controls + stock alerts inline (~120L)
 *   - Open Register modal inline (~45L)
 *   - Close Register modal inline (~50L)
 *
 * AFTER: ~55 lines (pure composition)
 *   - usePOSTerminal()       → all state, sync engine, SWR, actions
 *   - <ProductGrid>          → product tile grid + skeleton + states
 *   - <CartPanel>            → cart items + quantity controls + checkout
 *   - <OpenRegisterModal>    → shift-start gatekeeper
 *   - <CloseRegisterModal>   → blind-count EOD verification
 *
 * @author  Antigravity AI Agent — Phase 4, Task 4.2
 * @version 2026-06-12
 */

import React from "react";
import { Box, Wifi, WifiOff, AlertCircle, Search, Loader2 } from "lucide-react";
import clsx from "clsx";
import { usePOSTerminal } from "@/features/pos/hooks/usePOSTerminal";
import { ProductGrid } from "@/features/pos/components/ProductGrid";
import { CartPanel } from "@/features/pos/components/CartPanel";
import { OpenRegisterModal, CloseRegisterModal } from "@/features/pos/components/RegisterModals";

export default function POSPage() {
  const pos = usePOSTerminal();

  return (
    <div className="flex flex-col md:flex-row h-screen w-full bg-slate-50 overflow-hidden text-slate-900">

      {/* ── LEFT PANEL: PRODUCT GRID ──────────────────────────────────── */}
      <div className="flex-1 flex flex-col h-[50vh] md:h-full border-b md:border-b-0 md:border-r border-slate-200 bg-white z-10">

        {/* Header Bar */}
        <div className="flex items-center justify-between p-4 border-b border-slate-100 shadow-sm">
          <div className="flex items-center gap-2">
            <Box className="w-6 h-6 text-indigo-600" />
            <h1 className="text-xl font-bold tracking-tight text-slate-800">Terminal</h1>
            <div className={clsx(
              "flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ml-4 transition-colors",
              pos.isOnline ? "bg-emerald-100 text-emerald-700" : "bg-rose-100 text-rose-700"
            )}>
              {pos.isOnline ? <Wifi className="w-3.5 h-3.5" /> : <WifiOff className="w-3.5 h-3.5" />}
              {pos.isOnline ? "Online" : "Offline Mode"}
            </div>
          </div>

          <div className="flex items-center gap-3">
            {pos.unsyncedCount > 0 && (
              <div className="flex items-center gap-1.5 bg-amber-50 text-amber-700 px-3 py-1.5 rounded-lg text-sm font-semibold border border-amber-200">
                <AlertCircle className="w-4 h-4" />
                {pos.unsyncedCount} Pending
                {pos.isSyncing && <Loader2 className="w-3 h-3 animate-spin ml-1" />}
              </div>
            )}
            <button
              onClick={() => pos.setIsCloseModalOpen(true)}
              disabled={!pos.isRegisterOpen}
              className="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Close Register
            </button>
            <div className="relative w-64">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                id="pos-search"
                type="text"
                placeholder="Search products..."
                value={pos.searchTerm}
                onChange={e => pos.setSearchTerm(e.target.value)}
                className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm"
              />
            </div>
          </div>
        </div>

        {/* Product Grid Area */}
        <div className="flex-1 p-6 overflow-y-auto">
          <ProductGrid
            products={pos.products}
            searchTerm={pos.searchTerm}
            isLoading={pos.isCatalogLoading}
            hasError={!!pos.catalogError}
            onAddItem={pos.addItem}
          />
        </div>
      </div>

      {/* ── RIGHT PANEL: CART ─────────────────────────────────────────── */}
      <CartPanel
        items={pos.items}
        isOnline={pos.isOnline}
        isCheckingOut={pos.isCheckingOut}
        getCartTotal={pos.getCartTotal}
        onUpdateQuantity={pos.updateQuantity}
        onRemoveItem={pos.removeItem}
        onCheckout={pos.handleCheckout}
      />

      {/* ── MODALS ────────────────────────────────────────────────────── */}
      {!pos.isLoadingRegister && !pos.isRegisterOpen && (
        <OpenRegisterModal
          form={pos.openForm}
          onSubmit={pos.handleOpenRegister}
        />
      )}

      {pos.isCloseModalOpen && (
        <CloseRegisterModal
          form={pos.closeForm}
          onSubmit={pos.handleCloseRegister}
          onCancel={() => { pos.setIsCloseModalOpen(false); pos.closeForm.reset(); }}
        />
      )}
    </div>
  );
}
