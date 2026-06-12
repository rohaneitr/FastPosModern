'use client';

import React from 'react';
import type { Tenant, BillingFormState, CreateTenantFormState, TenantModule } from '../types';
import { AVAILABLE_MODULES } from '../types';

// ── Shared Modal Shell ─────────────────────────────────────────────────────

interface ModalShellProps {
  onClose: () => void;
  title: string;
  subtitle: string;
  children: React.ReactNode;
  maxWidth?: string;
}

function ModalShell({ onClose, title, subtitle, children, maxWidth = 'max-w-md' }: ModalShellProps) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
      <div className={`bg-surface border border-border w-full ${maxWidth} rounded-2xl shadow-2xl p-6 relative max-h-[90vh] overflow-y-auto custom-scrollbar`}>
        <button
          onClick={onClose}
          aria-label="Close modal"
          className="absolute top-4 right-4 text-text-muted hover:text-white transition-colors"
        >
          ✕
        </button>
        <h2 className="text-2xl font-bold text-white mb-2">{title}</h2>
        <p className="text-text-muted text-sm mb-6">{subtitle}</p>
        {children}
      </div>
    </div>
  );
}

// ── ManageModulesModal ─────────────────────────────────────────────────────

interface ManageModulesModalProps {
  activeModules: string[];
  onToggleModule: (modules: string[]) => void;
  onSubmit: (e: React.FormEvent) => void;
  onClose: () => void;
  submitting: boolean;
}

export function ManageModulesModal({
  activeModules,
  onToggleModule,
  onSubmit,
  onClose,
  submitting,
}: ManageModulesModalProps) {
  return (
    <ModalShell
      onClose={onClose}
      title="Manage SaaS Features"
      subtitle="Toggle premium modules for this tenant."
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <div className="space-y-3 max-h-[50vh] overflow-y-auto pr-2 custom-scrollbar">
          {AVAILABLE_MODULES.map((mod: TenantModule) => (
            <label
              key={mod.id}
              className="flex items-center gap-3 p-3 bg-background border border-border rounded-xl cursor-pointer hover:border-primary/50 transition-colors"
            >
              <input
                type="checkbox"
                className="w-5 h-5 accent-fuchsia-500 rounded bg-surface border-border"
                checked={activeModules.includes(mod.id)}
                onChange={(e) => {
                  onToggleModule(
                    e.target.checked
                      ? [...activeModules, mod.id]
                      : activeModules.filter(m => m !== mod.id)
                  );
                }}
              />
              <span className="font-semibold text-white">{mod.label}</span>
            </label>
          ))}
        </div>
        <div className="flex justify-end gap-3 mt-4">
          <button
            type="button"
            onClick={onClose}
            className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white font-medium transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="bg-gradient-to-r from-fuchsia-500 to-indigo-500 hover:from-fuchsia-400 hover:to-indigo-400 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg disabled:opacity-50 transition-all"
          >
            {submitting ? 'Saving...' : 'Save Features'}
          </button>
        </div>
      </form>
    </ModalShell>
  );
}

// ── BillingModal ───────────────────────────────────────────────────────────

interface BillingModalProps {
  billingForm: BillingFormState;
  onChangeBillingForm: (form: BillingFormState) => void;
  onRenew: () => void;
  onOverrideStatus: () => void;
  onClose: () => void;
  submitting: boolean;
}

export function BillingModal({
  billingForm,
  onChangeBillingForm,
  onRenew,
  onOverrideStatus,
  onClose,
  submitting,
}: BillingModalProps) {
  return (
    <ModalShell
      onClose={onClose}
      title="Subscription & Billing"
      subtitle="Manage billing lifecycle for this tenant."
    >
      <div className="flex flex-col gap-6">
        {/* Renew Section */}
        <div className="bg-background border border-border p-4 rounded-xl flex flex-col gap-3">
          <h3 className="font-bold text-white text-sm">Renew Subscription</h3>
          <div className="flex gap-2">
            <select
              value={billingForm.duration}
              onChange={e => onChangeBillingForm({ ...billingForm, duration: e.target.value as BillingFormState['duration'] })}
              className="flex-1 bg-surface border border-border rounded-lg px-3 py-2 text-white outline-none focus:border-blue-500/50"
            >
              <option value="1_month">+1 Month</option>
              <option value="1_year">+1 Year</option>
            </select>
            <button
              onClick={onRenew}
              disabled={submitting}
              className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold transition-colors disabled:opacity-50"
            >
              Renew
            </button>
          </div>
        </div>

        {/* Status Override Section */}
        <div className="bg-background border border-border p-4 rounded-xl flex flex-col gap-3">
          <h3 className="font-bold text-white text-sm">Override Status</h3>
          <div className="flex gap-2">
            <select
              value={billingForm.status}
              onChange={e => onChangeBillingForm({ ...billingForm, status: e.target.value as BillingFormState['status'] })}
              className="flex-1 bg-surface border border-border rounded-lg px-3 py-2 text-white outline-none focus:border-orange-500/50"
            >
              <option value="active">Active</option>
              <option value="past_due">Past Due</option>
              <option value="suspended">Suspended</option>
              <option value="canceled">Canceled</option>
            </select>
            <button
              onClick={onOverrideStatus}
              disabled={submitting}
              className="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-bold transition-colors disabled:opacity-50"
            >
              Override
            </button>
          </div>
        </div>
      </div>
      <div className="flex justify-end mt-6">
        <button
          onClick={onClose}
          className="px-5 py-2 rounded-lg bg-surface hover:bg-surface/80 text-white font-medium transition-colors"
        >
          Close
        </button>
      </div>
    </ModalShell>
  );
}

// ── CreateTenantModal ──────────────────────────────────────────────────────

interface Plan {
  id: number;
  name: string;
  price: string;
  interval: string;
}

interface CreateTenantModalProps {
  form: CreateTenantFormState;
  onChange: (form: CreateTenantFormState) => void;
  onSubmit: (e: React.FormEvent) => void;
  onClose: () => void;
  plans: Plan[];
  submitting: boolean;
}

export function CreateTenantModal({
  form,
  onChange,
  onSubmit,
  onClose,
  plans,
  submitting,
}: CreateTenantModalProps) {
  const inputClass = "bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20 transition-all w-full";
  const labelClass = "text-sm font-medium text-text-muted";

  return (
    <ModalShell
      onClose={onClose}
      title="Create New Tenant"
      subtitle="Provision a new SaaS tenant account."
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <div className="flex flex-col gap-1.5">
          <label className={labelClass}>Business Name *</label>
          <input
            required
            value={form.name}
            onChange={e => onChange({ ...form, name: e.target.value })}
            className={inputClass}
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <label className={labelClass}>Owner Email *</label>
          <input
            required
            type="email"
            value={form.owner_email}
            onChange={e => onChange({ ...form, owner_email: e.target.value })}
            className={inputClass}
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <label className={labelClass}>Temporary Password *</label>
          <input
            required
            type="password"
            value={form.password}
            onChange={e => onChange({ ...form, password: e.target.value })}
            className={inputClass}
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <label className={labelClass}>Subdomain (Optional)</label>
          <input
            value={form.subdomain}
            onChange={e => onChange({ ...form, subdomain: e.target.value })}
            className={inputClass}
            placeholder="mybusiness"
          />
        </div>
        <div className="flex flex-col gap-1.5">
          <label className={labelClass}>Select Subscription Plan *</label>
          <select
            required
            value={form.plan_id}
            onChange={e => onChange({ ...form, plan_id: e.target.value })}
            className={inputClass}
          >
            <option value="">Select a plan</option>
            {plans.map(p => (
              <option key={p.id} value={p.id}>
                {p.name} — {p.price}/{p.interval}
              </option>
            ))}
          </select>
        </div>
        <div className="flex justify-end gap-3 mt-4">
          <button
            type="button"
            onClick={onClose}
            className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white font-medium transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="bg-gradient-to-r from-fuchsia-500 to-indigo-500 hover:from-fuchsia-400 hover:to-indigo-400 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg disabled:opacity-50 transition-all"
          >
            {submitting ? 'Creating...' : 'Create Tenant'}
          </button>
        </div>
      </form>
    </ModalShell>
  );
}
