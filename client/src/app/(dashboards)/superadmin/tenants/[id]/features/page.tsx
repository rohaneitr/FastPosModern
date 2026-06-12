'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Module {
  key: string;
  label: string;
  description: string;
  enabled: boolean;
}

interface FeaturesResponse {
  business_id: number;
  business_name: string;
  modules: Module[];
}

// ─── Module icon map ─────────────────────────────────────────────────────────

const MODULE_ICONS: Record<string, string> = {
  pos:              '🛒',
  inventory:        '📦',
  inventory_sync:   '🔄',
  advanced_hr:      '👥',
  crm:              '🤝',
  accounting:       '📊',
  multi_location:   '📍',
  mobile_api:       '📱',
  offline_sync:     '📡',
  advanced_reports: '📈',
  api_access:       '🔌',
  whitelabel:       '🎨',
};

const MODULE_RISK: Record<string, 'low' | 'medium' | 'high'> = {
  pos:              'low',
  inventory:        'low',
  inventory_sync:   'medium',
  advanced_hr:      'medium',
  crm:              'low',
  accounting:       'medium',
  multi_location:   'medium',
  mobile_api:       'high',
  offline_sync:     'high',
  advanced_reports: 'low',
  api_access:       'high',
  whitelabel:       'low',
};

const RISK_BADGE: Record<string, string> = {
  low:    'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  medium: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
  high:   'bg-rose-500/10 text-rose-400 border-rose-500/20',
};

// ─── Toggle Switch ────────────────────────────────────────────────────────────

function ToggleSwitch({ enabled, onChange, disabled }: { enabled: boolean; onChange: (v: boolean) => void; disabled?: boolean }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={enabled}
      onClick={() => !disabled && onChange(!enabled)}
      disabled={disabled}
      className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 ${
        enabled
          ? 'bg-violet-600 border-violet-600'
          : 'bg-surface border-border'
      } ${disabled ? 'opacity-50 cursor-not-allowed' : 'hover:opacity-90'}`}
    >
      <span
        className={`pointer-events-none inline-block h-4 w-4 mt-0.5 rounded-full bg-white shadow-lg transform transition-transform duration-200 ${
          enabled ? 'translate-x-5' : 'translate-x-0.5'
        }`}
      />
    </button>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function TenantFeaturesPage() {
  const params   = useParams<{ id: string }>();
  const router   = useRouter();
  const tenantId = params.id;

  const [data, setData]         = useState<FeaturesResponse | null>(null);
  const [modules, setModules]   = useState<Module[]>([]);
  const [loading, setLoading]   = useState(true);
  const [saving, setSaving]     = useState(false);
  const [dirty, setDirty]       = useState(false);
  const [toast, setToast]       = useState<{ type: 'success' | 'error'; msg: string } | null>(null);

  const showToast = (type: 'success' | 'error', msg: string) => {
    setToast({ type, msg });
    setTimeout(() => setToast(null), 4000);
  };

  // ── Fetch ──────────────────────────────────────────────────────────────────
  const fetchFeatures = useCallback(async () => {
    setLoading(true);
    try {
      const { default: api } = await import('@/lib/api');
      const res = await api.get(`/superadmin/businesses/${tenantId}/features`);
      setData(res.data);
      setModules(res.data.modules);
      setDirty(false);
    } catch (err: any) {
      showToast('error', err?.response?.data?.message ?? 'Failed to load modules.');
    } finally {
      setLoading(false);
    }
  }, [tenantId]);

  useEffect(() => { fetchFeatures(); }, [fetchFeatures]);

  // ── Toggle local state ─────────────────────────────────────────────────────
  const handleToggle = (key: string, value: boolean) => {
    setModules(prev => prev.map(m => m.key === key ? { ...m, enabled: value } : m));
    setDirty(true);
  };

  const handleEnableAll  = () => { setModules(prev => prev.map(m => ({ ...m, enabled: true  }))); setDirty(true); };
  const handleDisableAll = () => { setModules(prev => prev.map(m => ({ ...m, enabled: false }))); setDirty(true); };

  // ── Save ───────────────────────────────────────────────────────────────────
  const handleSave = async () => {
    setSaving(true);
    try {
      const { default: api } = await import('@/lib/api');
      const payload = Object.fromEntries(modules.map(m => [m.key, m.enabled]));
      await api.put(`/superadmin/businesses/${tenantId}/features`, { modules: payload });
      showToast('success', 'Module flags saved successfully.');
      setDirty(false);
    } catch (err: any) {
      showToast('error', err?.response?.data?.message ?? 'Save failed.');
    } finally {
      setSaving(false);
    }
  };

  const enabledCount  = modules.filter(m => m.enabled).length;
  const totalCount    = modules.length;

  // ─────────────────────────────────────────────────────────────────────────
  return (
    <>
      {/* Toast */}
      {toast && (
        <div className={`fixed top-6 right-6 z-50 flex items-center gap-3 px-5 py-3 rounded-xl text-sm font-semibold shadow-2xl border animate-in slide-in-from-top-4 duration-300 ${
          toast.type === 'success' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/15 border-rose-500/30 text-rose-300'
        }`}>
          {toast.type === 'success' ? '✅' : '❌'} {toast.msg}
        </div>
      )}

      <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12 max-w-4xl">
        {/* Header */}
        <div className="flex items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <Link href="/superadmin/tenants" className="text-text-muted hover:text-white transition-colors text-sm">
                ← Back to Tenants
              </Link>
            </div>
            <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-violet-400 to-fuchsia-500">
              Module Toggler
            </h1>
            <p className="text-text-muted text-sm mt-1">
              {loading ? 'Loading…' : (
                <><span className="text-white font-semibold">{data?.business_name}</span> — {enabledCount}/{totalCount} modules enabled</>
              )}
            </p>
          </div>

          {/* Action bar */}
          <div className="flex items-center gap-2 shrink-0">
            <button
              id="enable_all_btn"
              onClick={handleEnableAll}
              disabled={loading || saving}
              className="px-3 py-2 rounded-xl text-xs font-bold border border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/10 transition-colors disabled:opacity-40"
            >
              Enable All
            </button>
            <button
              id="disable_all_btn"
              onClick={handleDisableAll}
              disabled={loading || saving}
              className="px-3 py-2 rounded-xl text-xs font-bold border border-rose-500/30 text-rose-400 hover:bg-rose-500/10 transition-colors disabled:opacity-40"
            >
              Disable All
            </button>
            <button
              id="save_modules_btn"
              onClick={handleSave}
              disabled={!dirty || saving || loading}
              className="px-5 py-2 rounded-xl text-sm font-bold bg-violet-600 hover:bg-violet-700 text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2"
            >
              {saving ? <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : '💾'}
              Save Changes
            </button>
          </div>
        </div>

        {/* Unsaved banner */}
        {dirty && (
          <div className="flex items-center gap-3 px-4 py-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm font-semibold animate-in slide-in-from-top-2 duration-200">
            ⚠️ You have unsaved changes. Click "Save Changes" to apply them.
          </div>
        )}

        {/* Progress bar */}
        {!loading && (
          <div className="bg-surface/30 border border-border rounded-2xl p-5">
            <div className="flex items-center justify-between mb-2 text-sm">
              <span className="text-text-muted font-medium">Module Coverage</span>
              <span className="text-white font-bold">{Math.round((enabledCount / totalCount) * 100)}%</span>
            </div>
            <div className="h-2 bg-surface rounded-full overflow-hidden">
              <div
                className="h-full bg-gradient-to-r from-violet-500 to-fuchsia-500 rounded-full transition-all duration-500"
                style={{ width: `${(enabledCount / totalCount) * 100}%` }}
              />
            </div>
          </div>
        )}

        {/* Module grid */}
        {loading ? (
          <div className="flex items-center justify-center py-20 gap-3 text-text-muted">
            <span className="w-5 h-5 border-2 border-violet-500/30 border-t-violet-400 rounded-full animate-spin" />
            Loading modules…
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-3">
            {modules.map(mod => {
              const risk = MODULE_RISK[mod.key] ?? 'low';
              return (
                <div
                  key={mod.key}
                  className={`flex items-center gap-4 p-4 rounded-2xl border transition-all duration-200 ${
                    mod.enabled
                      ? 'bg-violet-500/5 border-violet-500/20'
                      : 'bg-surface/20 border-border/60 opacity-70'
                  }`}
                >
                  {/* Icon */}
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-xl shrink-0 ${
                    mod.enabled ? 'bg-violet-500/15' : 'bg-surface/50'
                  }`}>
                    {MODULE_ICONS[mod.key] ?? '🔧'}
                  </div>

                  {/* Info */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <p className="font-semibold text-white">{mod.label}</p>
                      <span className={`px-2 py-0.5 rounded text-xs font-bold border capitalize ${RISK_BADGE[risk]}`}>
                        {risk} impact
                      </span>
                    </div>
                    <p className="text-xs text-text-muted mt-0.5">{mod.description}</p>
                  </div>

                  {/* Status text */}
                  <span className={`text-xs font-bold w-16 text-right shrink-0 ${mod.enabled ? 'text-violet-400' : 'text-text-muted'}`}>
                    {mod.enabled ? 'Enabled' : 'Disabled'}
                  </span>

                  {/* Toggle */}
                  <ToggleSwitch
                    enabled={mod.enabled}
                    onChange={(v) => handleToggle(mod.key, v)}
                    disabled={saving}
                  />
                </div>
              );
            })}
          </div>
        )}
      </div>
    </>
  );
}
