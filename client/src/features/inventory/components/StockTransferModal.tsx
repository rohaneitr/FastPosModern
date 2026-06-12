'use client';

import React from 'react';
import { ArrowRightLeft, Loader2, X } from 'lucide-react';
import { UseFormReturn } from 'react-hook-form';
import clsx from 'clsx';
import type { TransferFormValues, ProductStock, Location } from '../types';

interface StockTransferModalProps {
  form:      UseFormReturn<TransferFormValues>;
  products:  ProductStock[];
  locations: Location[];
  onSubmit:  (data: TransferFormValues) => void;
  onClose:   () => void;
}

/**
 * StockTransferModal — Extracted from inventory/page.tsx L221–350.
 *
 * Typed with UseFormReturn — no prop-drilling of individual register/errors.
 * The parent passes the full form object so this component stays purely
 * presentational: zero business logic, zero API calls.
 *
 * ZERO TRUST: same-branch guard enforced at Zod schema level (not here).
 */
export function StockTransferModal({
  form,
  products,
  locations,
  onSubmit,
  onClose,
}: StockTransferModalProps) {
  const { register, handleSubmit, formState: { errors, isSubmitting } } = form;

  const inputBase  = 'w-full px-3 py-2 bg-white border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500';
  const inputOk    = 'border-slate-300';
  const inputError = 'border-rose-500 focus:ring-rose-500';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col">

        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-slate-100">
          <h2 className="text-lg font-bold text-slate-800 flex items-center gap-2">
            <ArrowRightLeft className="w-5 h-5 text-indigo-600" />
            Transfer Stock
          </h2>
          <button
            onClick={onClose}
            aria-label="Close transfer modal"
            className="text-slate-400 hover:text-slate-600 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit(onSubmit)} className="p-5 flex flex-col gap-4">

          {/* Product */}
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Select Product</label>
            <select
              {...register('product_id', { valueAsNumber: true })}
              className={clsx(inputBase, errors.product_id ? inputError : inputOk)}
            >
              <option value={0}>— Choose a Product —</option>
              {products.map(p => (
                <option key={p.id} value={p.id}>{p.name} ({p.sku})</option>
              ))}
            </select>
            {errors.product_id && (
              <span className="text-xs font-semibold text-rose-500">{errors.product_id.message}</span>
            )}
          </div>

          {/* Source Branch */}
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Source Branch</label>
            <select
              {...register('from_location_id', { valueAsNumber: true })}
              className={clsx(inputBase, errors.from_location_id ? inputError : inputOk)}
            >
              <option value={0}>— From Location —</option>
              {locations.map(l => (
                <option key={l.id} value={l.id}>{l.name}</option>
              ))}
            </select>
            {errors.from_location_id && (
              <span className="text-xs font-semibold text-rose-500">{errors.from_location_id.message}</span>
            )}
          </div>

          {/* Destination Branch */}
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Destination Branch</label>
            <select
              {...register('to_location_id', { valueAsNumber: true })}
              className={clsx(inputBase, errors.to_location_id ? inputError : inputOk)}
            >
              <option value={0}>— To Location —</option>
              {locations.map(l => (
                <option key={l.id} value={l.id}>{l.name}</option>
              ))}
            </select>
            {errors.to_location_id && (
              <span className="text-xs font-semibold text-rose-500">{errors.to_location_id.message}</span>
            )}
          </div>

          {/* Quantity */}
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Quantity</label>
            <input
              type="number"
              step="0.0001"
              {...register('quantity', { valueAsNumber: true })}
              placeholder="e.g. 50"
              className={clsx(inputBase, errors.quantity ? inputError : inputOk)}
            />
            {errors.quantity && (
              <span className="text-xs font-semibold text-rose-500">{errors.quantity.message}</span>
            )}
          </div>

          {/* Note */}
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Note (Optional)</label>
            <input
              type="text"
              {...register('note')}
              placeholder="Reason for transfer"
              className={clsx(inputBase, inputOk)}
            />
          </div>

          {/* Actions */}
          <div className="mt-4 flex justify-end gap-3 pt-4 border-t border-slate-100">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg font-medium text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSubmitting && <Loader2 className="w-4 h-4 animate-spin" />}
              {isSubmitting ? 'Processing...' : 'Execute Transfer'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
