'use client';

import React from 'react';
import { Users, Edit, Trash2, Loader2 } from 'lucide-react';
import type { Customer } from '../types';

function CustomerTableSkeleton() {
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

function CustomerTableEmpty() {
  return (
    <tr>
      <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
        <div className="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
          <Users className="w-6 h-6 text-slate-400" />
        </div>
        <p className="font-medium text-slate-800">No customers found.</p>
      </td>
    </tr>
  );
}

interface CustomerTableProps {
  customers:  Customer[];
  isLoading:  boolean;
  deletingId: number | null;
  onDelete:   (id: number) => void;
}

/**
 * CustomerTable — Extracted from customers/page.tsx L183–244.
 * Skeleton, empty state, and typed rows with per-row archive spinner.
 */
export function CustomerTable({ customers, isLoading, deletingId, onDelete }: CustomerTableProps) {
  return (
    <div className="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider">
              <th className="px-6 py-4 font-semibold">Name</th>
              <th className="px-6 py-4 font-semibold">Mobile</th>
              <th className="px-6 py-4 font-semibold">Email</th>
              <th className="px-6 py-4 font-semibold">City</th>
              <th className="px-6 py-4 font-semibold text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {isLoading ? (
              <CustomerTableSkeleton />
            ) : customers.length === 0 ? (
              <CustomerTableEmpty />
            ) : (
              customers.map(item => (
                <tr key={item.id} className="hover:bg-slate-50/50 transition-colors">
                  <td className="px-6 py-4 text-sm font-medium text-slate-900">{item.name}</td>
                  <td className="px-6 py-4 text-sm text-slate-500">{item.mobile ?? '—'}</td>
                  <td className="px-6 py-4 text-sm text-slate-500">{item.email  ?? '—'}</td>
                  <td className="px-6 py-4 text-sm text-slate-500">{item.city   ?? '—'}</td>
                  <td className="px-6 py-4 text-sm text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        className="p-1.5 text-slate-400 hover:text-indigo-600 rounded transition-colors"
                        title="Edit"
                      >
                        <Edit className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => onDelete(item.id)}
                        disabled={deletingId === item.id}
                        className="p-1.5 text-slate-400 hover:text-rose-600 rounded transition-colors disabled:opacity-50"
                        title="Archive"
                      >
                        {deletingId === item.id
                          ? <Loader2 className="w-4 h-4 animate-spin" />
                          : <Trash2 className="w-4 h-4" />
                        }
                      </button>
                    </div>
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
