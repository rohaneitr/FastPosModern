'use client';

import React from 'react';
import { Users, Loader2, X } from 'lucide-react';
import { UseFormReturn } from 'react-hook-form';
import clsx from 'clsx';
import type { CustomerFormValues } from '../types';

interface CreateCustomerModalProps {
  form:     UseFormReturn<CustomerFormValues>;
  onSubmit: (data: CustomerFormValues) => void;
  onClose:  () => void;
}

/**
 * CreateCustomerModal — Extracted from customers/page.tsx L246–343.
 *
 * Purely presentational: receives typed UseFormReturn, calls onSubmit/onClose.
 * Zero business logic, zero API calls, zero state.
 * 8 fields: first_name, last_name, mobile, email, city, state, country + hidden type.
 */
export function CreateCustomerModal({ form, onSubmit, onClose }: CreateCustomerModalProps) {
  const { register, handleSubmit, formState: { errors, isSubmitting } } = form;

  const fieldBase  = 'w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500';
  const fieldOk    = 'border-slate-300';
  const fieldError = 'border-rose-500';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col">

        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-slate-100">
          <h2 className="text-lg font-bold text-slate-800 flex items-center gap-2">
            <Users className="w-5 h-5 text-indigo-600" />
            Add New Customer
          </h2>
          <button
            onClick={onClose}
            aria-label="Close customer modal"
            className="text-slate-400 hover:text-slate-600 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form
          onSubmit={handleSubmit(onSubmit)}
          className="p-5 flex flex-col gap-4 overflow-y-auto max-h-[70vh] custom-scrollbar"
        >
          {/* Hidden type field */}
          <input type="hidden" {...register('type')} value="customer" />

          {/* Row 1: First + Last */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-slate-700">
                First Name <span className="text-rose-500">*</span>
              </label>
              <input
                {...register('first_name')}
                className={clsx(fieldBase, errors.first_name ? fieldError : fieldOk)}
              />
              {errors.first_name && (
                <span className="text-xs text-rose-500 font-semibold">{errors.first_name.message}</span>
              )}
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-slate-700">Last Name</label>
              <input
                {...register('last_name')}
                className={clsx(fieldBase, fieldOk)}
              />
            </div>
          </div>

          {/* Row 2: Mobile + Email */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-slate-700">Mobile Phone</label>
              <input
                {...register('mobile')}
                placeholder="+1234567890"
                className={clsx(fieldBase, errors.mobile ? fieldError : fieldOk)}
              />
              {errors.mobile && (
                <span className="text-xs text-rose-500 font-semibold">{errors.mobile.message}</span>
              )}
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-slate-700">Email Address</label>
              <input
                type="email"
                {...register('email')}
                className={clsx(fieldBase, errors.email ? fieldError : fieldOk)}
              />
              {errors.email && (
                <span className="text-xs text-rose-500 font-semibold">{errors.email.message}</span>
              )}
            </div>
          </div>

          {/* Row 3: City + State + Country */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-slate-700">City</label>
              <input {...register('city')}    className={clsx(fieldBase, fieldOk)} />
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-slate-700">State</label>
              <input {...register('state')}   className={clsx(fieldBase, fieldOk)} />
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-semibold text-slate-700">Country</label>
              <input {...register('country')} className={clsx(fieldBase, fieldOk)} />
            </div>
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
              {isSubmitting ? 'Processing...' : 'Save Customer'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
