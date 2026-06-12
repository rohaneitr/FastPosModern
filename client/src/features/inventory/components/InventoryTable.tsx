'use client';

import React from 'react';
import { Package, AlertTriangle } from 'lucide-react';
import type { ProductStock } from '../types';
import { LOW_STOCK_THRESHOLD } from '../types';

// ── Skeleton ───────────────────────────────────────────────────────────────

function InventoryTableSkeleton() {
  return (
    <>
      {Array.from({ length: 5 }).map((_, i) => (
        <tr key={i} className="animate-pulse">
          <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-3/4" /></td>
          <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-1/2" /></td>
          <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-2/3" /></td>
          <td className="px-6 py-4"><div className="h-4 bg-slate-200 rounded w-1/2" /></td>
          <td className="px-6 py-4 flex justify-end"><div className="h-4 bg-slate-200 rounded w-12" /></td>
        </tr>
      ))}
    </>
  );
}

// ── Empty State ────────────────────────────────────────────────────────────

function InventoryTableEmpty() {
  return (
    <tr>
      <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
        <div className="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
          <Package className="w-6 h-6 text-slate-400" />
        </div>
        <p className="font-medium text-slate-800">No inventory found.</p>
      </td>
    </tr>
  );
}

// ── Stock Badge ────────────────────────────────────────────────────────────

function StockBadge({ qty }: { qty: number }) {
  if (qty <= LOW_STOCK_THRESHOLD) {
    return (
      <div className="flex items-center justify-end gap-1.5 text-rose-500 font-bold bg-rose-50 px-2.5 py-1 rounded-md inline-flex border border-rose-100">
        <AlertTriangle className="w-3.5 h-3.5" />
        {qty}
      </div>
    );
  }
  return (
    <span className="text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-md inline-flex border border-emerald-100">
      {qty}
    </span>
  );
}

// ── InventoryTable ─────────────────────────────────────────────────────────

interface InventoryTableProps {
  products:  ProductStock[];
  isLoading: boolean;
}

/**
 * InventoryTable — Extracted from inventory/page.tsx L150–219.
 * Handles skeleton, empty state, and data rows with stock badge.
 * Typed with ProductStock — zero 'any'.
 */
export function InventoryTable({ products, isLoading }: InventoryTableProps) {
  return (
    <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider">
              <th className="px-6 py-4 font-semibold">Product Name</th>
              <th className="px-6 py-4 font-semibold">SKU</th>
              <th className="px-6 py-4 font-semibold">Category</th>
              <th className="px-6 py-4 font-semibold">Branch</th>
              <th className="px-6 py-4 font-semibold text-right">Available Stock</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {isLoading ? (
              <InventoryTableSkeleton />
            ) : products.length === 0 ? (
              <InventoryTableEmpty />
            ) : (
              products.map(item => (
                <tr
                  key={`${item.id}-${item.location_id}`}
                  className="hover:bg-slate-50/50 transition-colors"
                >
                  <td className="px-6 py-4 text-sm font-medium text-slate-900">{item.name}</td>
                  <td className="px-6 py-4 text-sm text-slate-500 font-mono">{item.sku}</td>
                  <td className="px-6 py-4 text-sm text-slate-500">{item.category?.name ?? '—'}</td>
                  <td className="px-6 py-4 text-sm text-slate-500">{item.location_name}</td>
                  <td className="px-6 py-4 text-sm text-right font-medium">
                    <StockBadge qty={item.stock_quantity} />
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
