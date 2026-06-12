'use client';

import React from 'react';
import { Banknote, Loader2 } from 'lucide-react';
import { UseFormReturn } from 'react-hook-form';
import clsx from 'clsx';

// ── Open Register Modal ────────────────────────────────────────────────────

interface OpenRegisterModalProps {
  form: UseFormReturn<{ opening_balance: number }>;
  onSubmit: (data: { opening_balance: number }) => void;
}

export function OpenRegisterModal({ form, onSubmit }: OpenRegisterModalProps) {
  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-md p-4">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col p-6 text-center">
        <div className="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4">
          <Banknote className="w-8 h-8" />
        </div>
        <h2 className="text-xl font-bold text-slate-800 mb-2">Open Register</h2>
        <p className="text-sm text-slate-500 mb-6">
          You must open your cash register for this location to process sales.
        </p>

        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 text-left">
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Opening Cash Float ($)</label>
            <input
              type="number"
              step="0.01"
              disabled={form.formState.isSubmitting}
              {...form.register('opening_balance')}
              className={clsx(
                'w-full px-4 py-3 bg-slate-50 border rounded-xl text-lg font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50',
                form.formState.errors.opening_balance ? 'border-rose-500' : 'border-slate-300'
              )}
              placeholder="0.00"
            />
            {form.formState.errors.opening_balance && (
              <span className="text-xs font-semibold text-rose-500">
                {form.formState.errors.opening_balance.message}
              </span>
            )}
          </div>
          <button
            type="submit"
            disabled={form.formState.isSubmitting}
            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-xl font-bold text-base transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2 mt-2"
          >
            {form.formState.isSubmitting
              ? <><Loader2 className="w-4 h-4 animate-spin" />Processing...</>
              : <>Start Shift</>
            }
          </button>
        </form>
      </div>
    </div>
  );
}

// ── Close Register Modal ───────────────────────────────────────────────────

interface CloseRegisterModalProps {
  form: UseFormReturn<{ closing_balance_counted: number }>;
  onSubmit: (data: { closing_balance_counted: number }) => void;
  onCancel: () => void;
}

export function CloseRegisterModal({ form, onSubmit, onCancel }: CloseRegisterModalProps) {
  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-md p-4">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col p-6 text-center">
        <h2 className="text-xl font-bold text-slate-800 mb-2">End of Day Verification</h2>
        <p className="text-sm text-slate-500 mb-6">
          Physically count the cash in your drawer. (Blind Count)
        </p>

        <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4 text-left">
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-semibold text-slate-700">Counted Cash Amount ($)</label>
            <input
              type="number"
              step="0.01"
              disabled={form.formState.isSubmitting}
              {...form.register('closing_balance_counted')}
              className={clsx(
                'w-full px-4 py-3 bg-slate-50 border rounded-xl text-lg font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50',
                form.formState.errors.closing_balance_counted ? 'border-rose-500' : 'border-slate-300'
              )}
              placeholder="0.00"
            />
            {form.formState.errors.closing_balance_counted && (
              <span className="text-xs font-semibold text-rose-500">
                {form.formState.errors.closing_balance_counted.message}
              </span>
            )}
          </div>

          <div className="flex gap-3 mt-4">
            <button
              type="button"
              onClick={onCancel}
              className="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-3 rounded-xl font-bold transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={form.formState.isSubmitting}
              className="flex-1 bg-rose-600 hover:bg-rose-700 text-white px-4 py-3 rounded-xl font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2"
            >
              {form.formState.isSubmitting
                ? <><Loader2 className="w-4 h-4 animate-spin" />Processing...</>
                : <>Close Register</>
              }
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
