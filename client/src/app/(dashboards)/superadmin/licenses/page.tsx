'use client';

import React, { useState, useEffect, useRef } from 'react';
import api from '@/lib/api';

function KeyCell({ licenseKey }: { licenseKey: string }) {
  const [copied, setCopied] = useState(false);
  const truncated = licenseKey.length > 24 ? `${licenseKey.slice(0, 12)}...${licenseKey.slice(-8)}` : licenseKey;
  const copy = () => {
    navigator.clipboard.writeText(licenseKey);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };
  return (
    <div className="flex items-center gap-2 group">
      <span className="font-mono text-xs text-amber-300 bg-amber-500/10 border border-amber-500/20 px-2 py-1 rounded-md" title={licenseKey}>{truncated}</span>
      <button onClick={copy} className="opacity-0 group-hover:opacity-100 transition-opacity text-text-muted hover:text-white" title="Copy full key">
        {copied
          ? <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          : <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        }
      </button>
    </div>
  );
}

function DeviceTooltip({ devices, limit }: { devices: any[], limit: number }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const activeCount = devices.length;
  const pct = limit > 0 ? (activeCount / limit) * 100 : 0;
  const color = pct >= 100 ? 'bg-rose-500' : pct >= 60 ? 'bg-amber-400' : 'bg-teal-400';

  useEffect(() => {
    const handler = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  return (
    <div className="relative" ref={ref}>
      <button onClick={() => setOpen(o => !o)} className="flex items-center gap-2 group">
        <div className="w-24 h-1.5 bg-white/10 rounded-full overflow-hidden">
          <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${Math.min(pct, 100)}%` }}/>
        </div>
        <span className={`text-xs font-bold ${pct >= 100 ? 'text-rose-400' : 'text-white'}`}>{activeCount} / {limit}</span>
        {devices.length > 0 && (
          <svg className="w-3 h-3 text-text-muted group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        )}
      </button>
      {open && devices.length > 0 && (
        <div className="absolute z-50 bottom-full mb-2 left-0 w-72 bg-surface border border-border rounded-xl shadow-2xl p-3 flex flex-col gap-2 animate-in fade-in zoom-in-95 duration-150">
          <p className="text-xs font-bold text-text-muted uppercase tracking-wider mb-1">Connected Devices</p>
          {devices.map((d: any) => (
            <div key={d.id} className="flex flex-col gap-0.5 bg-background/50 rounded-lg px-3 py-2 border border-border/50">
              <span className="font-mono text-[10px] text-amber-300 break-all">{d.device_fingerprint}</span>
              <span className="text-[10px] text-text-muted">Last sync: {d.last_synced_at ? new Date(d.last_synced_at).toLocaleString() : '—'}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default function SuperadminLicenses() {
  const [licenses, setLicenses] = useState<any[]>([]);
  const [tenants, setTenants]   = useState<any[]>([]);
  const [plans, setPlans]       = useState<any[]>([]);
  const [loading, setLoading]   = useState(true);
  const [showModal, setShowModal] = useState(false);
  
  // MDM Modal States
  const [showDeviceModal, setShowDeviceModal] = useState(false);
  const [selectedBusinessId, setSelectedBusinessId] = useState<number | null>(null);
  const [businessDevices, setBusinessDevices] = useState<any[]>([]);
  const [loadingDevices, setLoadingDevices] = useState(false);

  const [form, setForm]         = useState({ tenant_id: '', plan_id: '' });
  const [submitting, setSubmitting] = useState(false);
  const [generatedKey, setGeneratedKey] = useState<string | null>(null);
  const [toast, setToast]       = useState<{ msg: string; ok: boolean } | null>(null);

  const showToast = (msg: string, ok = true) => {
    setToast({ msg, ok });
    setTimeout(() => setToast(null), 3500);
  };

  useEffect(() => { fetchData(); }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [licRes, tenRes, planRes] = await Promise.all([
        api.get('/superadmin/licenses'),
        api.get('/superadmin/businesses'),
        api.get('/superadmin/plans'),
      ]);
      setLicenses(Array.isArray(licRes.data) ? licRes.data : []);
      setTenants(Array.isArray(tenRes.data?.data) ? tenRes.data.data : Array.isArray(tenRes.data) ? tenRes.data : []);
      setPlans(Array.isArray(planRes.data) ? planRes.data : []);
    } catch {
      setLicenses([]);
    } finally {
      setLoading(false);
    }
  };

  const handleGenerate = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const res = await api.post('/superadmin/licenses/generate', form);
      setGeneratedKey(res.data.license_key);
      fetchData();
    } catch (error: any) {
      showToast(error.response?.data?.message || 'Failed to generate license.', false);
    } finally {
      setSubmitting(false);
    }
  };

  const toggleStatus = async (id: number) => {
    try {
      const res = await api.put(`/superadmin/licenses/${id}/toggle-status`);
      const updatedStatus = res.data.business.is_active ? 'active' : 'suspended';
      setLicenses(licenses.map(lic => lic.id === id ? { ...lic, status: updatedStatus } : lic));
      showToast('License status updated.');
    } catch (error: any) {
      showToast(error.response?.data?.message || 'Failed to toggle status.', false);
    }
  };

  const fetchDevices = async (businessId: number) => {
    setLoadingDevices(true);
    setSelectedBusinessId(businessId);
    setShowDeviceModal(true);
    try {
      const res = await api.get(`/superadmin/businesses/${businessId}/devices`);
      setBusinessDevices(res.data);
    } catch (error: any) {
      showToast('Failed to fetch devices.', false);
    } finally {
      setLoadingDevices(false);
    }
  };

  const revokeDevice = async (deviceId: number) => {
    try {
      await api.patch(`/superadmin/devices/${deviceId}/revoke`);
      setBusinessDevices(prev => prev.map(d => d.device_id === deviceId ? { ...d, status: 'revoked' } : d));
      showToast('Device revoked & session purged.');
      fetchData(); // Refresh main table active counts
    } catch (error: any) {
      showToast('Failed to revoke device.', false);
    }
  };

  const activeLicenses    = licenses.filter((l: any) => l.status === 'active');
  const suspendedLicenses = licenses.filter((l: any) => l.status !== 'active');
  const totalDevices      = licenses.reduce((s: number, l: any) => s + (l.active_devices_count ?? 0), 0);

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">

      {/* Toast */}
      {toast && (
        <div className={`fixed top-6 right-6 z-50 px-5 py-3 rounded-xl shadow-2xl font-semibold flex items-center gap-3 border animate-in slide-in-from-top-4 duration-200
          ${toast.ok ? 'bg-teal-500/20 text-teal-300 border-teal-500/40' : 'bg-rose-500/20 text-rose-300 border-rose-500/40'}`}>
          {toast.ok ? '✓' : '✕'} {toast.msg}
        </div>
      )}

      {/* Header */}
      <div className="flex justify-between items-start gap-4">
        <div>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-amber-400 to-orange-500">
            License Key Manager
          </h1>
          <p className="text-text-muted mt-1 text-sm">Cryptographic node-locked serial keys with real-time device tracking.</p>
        </div>
        <button
          onClick={() => { setGeneratedKey(null); setForm({ tenant_id: '', plan_id: '' }); setShowModal(true); }}
          className="shrink-0 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white px-6 py-2.5 rounded-xl shadow-lg shadow-amber-500/25 font-bold transition-all active:scale-[0.98] flex items-center gap-2"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4"/></svg>
          Generate Key
        </button>
      </div>

      {/* Stats Row */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Total Licenses',     value: licenses.length,        color: 'text-white' },
          { label: 'Active',             value: activeLicenses.length,  color: 'text-teal-400' },
          { label: 'Suspended/Expired',  value: suspendedLicenses.length, color: 'text-rose-400' },
          { label: 'Active Devices',     value: totalDevices,           color: 'text-amber-400' },
        ].map(s => (
          <div key={s.label} className="glass-card rounded-xl border border-border p-5">
            <p className="text-text-muted text-xs uppercase tracking-wider font-semibold">{s.label}</p>
            <p className={`text-3xl font-black mt-1 ${s.color}`}>{s.value}</p>
          </div>
        ))}
      </div>

      {/* License Table */}
      <div className="glass-card rounded-2xl border border-border overflow-hidden shadow-xl">
        {loading ? (
          <div className="p-12 flex flex-col items-center gap-4 text-text-muted">
            <div className="w-8 h-8 border-2 border-amber-500/50 border-t-amber-500 rounded-full animate-spin"/>
            <p>Loading licenses...</p>
          </div>
        ) : licenses.length === 0 ? (
          <div className="p-12 text-center text-text-muted">
            <div className="w-16 h-16 bg-surface rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">🔑</div>
            <p className="font-semibold text-white">No license keys generated yet.</p>
            <p className="text-sm mt-1">Click "Generate Key" to provision a new license for a tenant.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-surface border-b border-border text-xs uppercase tracking-wider font-bold text-text-muted">
                <tr>
                  <th className="px-5 py-4">Tenant</th>
                  <th className="px-5 py-4">Plan</th>
                  <th className="px-5 py-4">License Key</th>
                  <th className="px-5 py-4">Device Usage</th>
                  <th className="px-5 py-4">Expiry</th>
                  <th className="px-5 py-4 text-center">Status</th>
                  <th className="px-5 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/50">
                {licenses.map((lic: any) => {
                  const isActive  = lic.status === 'active';
                  const isExpired = lic.expires_at && new Date(lic.expires_at) < new Date();
                  const effectiveStatus = isExpired ? 'expired' : lic.status;
                  const deviceLimit = lic.device_limit ?? lic.plan?.device_limit ?? 1;
                  const activeDevices = lic.active_devices_count ?? 0;
                  const connectedDevices = lic.device_activations ?? [];

                  return (
                    <tr key={lic.id} className="group hover:bg-white/[0.02] transition-colors">
                      {/* Tenant */}
                      <td className="px-5 py-4">
                        <div className="flex items-center gap-3">
                          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 border border-amber-500/20 flex items-center justify-center font-bold text-amber-400 text-sm shrink-0">
                            {(lic.tenant?.business_name || lic.tenant?.name || '?').charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <div className="font-semibold text-white">{lic.tenant?.business_name || lic.tenant?.name || `Tenant #${lic.tenant_id}`}</div>
                            <div className="text-[11px] text-text-muted">ID: {lic.tenant_id}</div>
                          </div>
                        </div>
                      </td>

                      {/* Plan */}
                      <td className="px-5 py-4">
                        <span className="text-xs font-semibold bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 px-2.5 py-1 rounded-full">
                          {lic.plan?.name || `Plan #${lic.plan_id}`}
                        </span>
                      </td>

                      {/* License Key */}
                      <td className="px-5 py-4">
                        <KeyCell licenseKey={lic.license_key} />
                      </td>

                      {/* Device Usage */}
                      <td className="px-5 py-4">
                        <DeviceTooltip devices={connectedDevices} limit={deviceLimit} />
                      </td>

                      {/* Expiry */}
                      <td className="px-5 py-4">
                        {lic.expires_at ? (
                          <div className={`text-xs font-medium ${isExpired ? 'text-rose-400' : 'text-text-muted'}`}>
                            {isExpired && <span className="mr-1">⚠️</span>}
                            {new Date(lic.expires_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}
                          </div>
                        ) : (
                          <span className="text-xs text-text-muted">—</span>
                        )}
                      </td>

                      {/* Status */}
                      <td className="px-5 py-4 text-center">
                        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border capitalize
                          ${effectiveStatus === 'active'   ? 'bg-teal-500/10 text-teal-400 border-teal-500/20' :
                            effectiveStatus === 'expired'  ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' :
                                                             'bg-warning/10 text-warning border-warning/20'}`}>
                          <span className={`w-1.5 h-1.5 rounded-full ${
                            effectiveStatus === 'active' ? 'bg-teal-400 animate-pulse' :
                            effectiveStatus === 'expired' ? 'bg-rose-400' : 'bg-warning'}`}/>
                          {effectiveStatus}
                        </span>
                      </td>

                      {/* Actions */}
                      <td className="px-5 py-4 text-right">
                        <div className="flex justify-end gap-2">
                          <button
                            onClick={() => fetchDevices(lic.id)}
                            className="text-xs font-bold px-3 py-1.5 rounded-lg border border-amber-500/30 text-amber-400 hover:bg-amber-500/10 transition-all"
                          >
                            View Devices
                          </button>
                          <button
                            onClick={() => toggleStatus(lic.id)}
                            className={`text-xs font-bold px-3 py-1.5 rounded-lg border transition-all
                              ${isActive
                                ? 'border-rose-500/30 text-rose-400 hover:bg-rose-500/10'
                                : 'border-teal-500/30 text-teal-400 hover:bg-teal-500/10'}`}
                          >
                            {isActive ? 'Suspend' : 'Reactivate'}
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Generate Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-surface border border-border w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl shadow-2xl p-6 relative animate-in zoom-in-95 duration-200">
            <button onClick={() => setShowModal(false)} className="absolute top-4 right-4 text-text-muted hover:text-white transition-colors">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div className="flex items-center gap-3 mb-6">
              <div className="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center text-amber-400">🔑</div>
              <h2 className="text-2xl font-bold text-white">Generate License Key</h2>
            </div>

            {generatedKey ? (
              <div className="flex flex-col gap-4 items-center p-6 bg-teal-500/10 border border-teal-500/30 rounded-xl text-center">
                <div className="w-12 h-12 rounded-full bg-teal-500/20 flex items-center justify-center text-2xl">✓</div>
                <div className="text-teal-400 font-bold text-lg">License Key Generated & Tenant Activated!</div>
                <div className="w-full font-mono text-sm text-white tracking-widest break-all bg-black/30 border border-border rounded-xl px-4 py-3">
                  {generatedKey}
                </div>
                <div className="flex gap-3">
                  <button
                    onClick={() => { navigator.clipboard.writeText(generatedKey); showToast('Key copied to clipboard!'); }}
                    className="bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 border border-teal-500/30 px-4 py-2 rounded-lg transition-colors flex items-center gap-2 font-semibold text-sm"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Copy to Clipboard
                  </button>
                  <button onClick={() => { setShowModal(false); setGeneratedKey(null); }} className="text-text-muted hover:text-white px-4 py-2 rounded-lg text-sm transition-colors">
                    Close
                  </button>
                </div>
              </div>
            ) : (
              <form onSubmit={handleGenerate} className="flex flex-col gap-5">
                <div className="flex flex-col sm:flex-row gap-4">
                  <div className="flex flex-col gap-1.5 flex-1">
                    <label className="text-sm font-semibold text-text-muted">Select Tenant *</label>
                    <select required value={form.tenant_id} onChange={e => setForm({ ...form, tenant_id: e.target.value })} className="bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-amber-500/50 focus:ring-2 focus:ring-amber-500/10 transition-all">
                      <option value="">— Choose Tenant —</option>
                      {tenants.map(t => (
                        <option key={t.id} value={t.id}>{t.business_name || t.name}</option>
                      ))}
                    </select>
                  </div>
                  <div className="flex flex-col gap-1.5 flex-1">
                    <label className="text-sm font-semibold text-text-muted">Select Plan *</label>
                    <select required value={form.plan_id} onChange={e => setForm({ ...form, plan_id: e.target.value })} className="bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-amber-500/50 focus:ring-2 focus:ring-amber-500/10 transition-all">
                      <option value="">— Choose Plan —</option>
                      {plans.map(p => (
                        <option key={p.id} value={p.id}>{p.name} (Limit: {p.device_limit ?? 1} device{(p.device_limit ?? 1) > 1 ? 's' : ''})</option>
                      ))}
                    </select>
                    <span className="text-xs text-text-muted">The device limit is read from the plan and embedded into the signed key.</span>
                  </div>
                </div>
                <div className="flex justify-end gap-3 pt-2">
                  <button type="button" onClick={() => setShowModal(false)} className="px-5 py-2.5 rounded-xl text-text-muted hover:text-white font-medium transition-colors">
                    Cancel
                  </button>
                  <button type="submit" disabled={submitting || !form.tenant_id || !form.plan_id} className="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg shadow-amber-500/25 disabled:opacity-50 transition-all flex items-center gap-2">
                    {submitting ? <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/> : null}
                    {submitting ? 'Generating...' : 'Generate & Activate'}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {/* MDM Device Modal */}
      {showDeviceModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-surface border border-border w-full max-w-4xl max-h-[90vh] overflow-hidden rounded-2xl shadow-2xl flex flex-col animate-in zoom-in-95 duration-200">
            <div className="flex justify-between items-center p-6 border-b border-border/50">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-indigo-500/20 flex items-center justify-center text-indigo-400">📱</div>
                <div>
                  <h2 className="text-xl font-bold text-white">Device Management</h2>
                  <p className="text-sm text-text-muted">Business #{selectedBusinessId} - View and revoke active devices.</p>
                </div>
              </div>
              <button onClick={() => setShowDeviceModal(false)} className="text-text-muted hover:text-white transition-colors">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
            
            <div className="flex-1 overflow-y-auto p-6 bg-background/30">
              {loadingDevices ? (
                <div className="flex flex-col items-center justify-center h-48 gap-4 text-text-muted">
                  <div className="w-8 h-8 border-2 border-indigo-500/50 border-t-indigo-500 rounded-full animate-spin"/>
                  <p>Loading devices...</p>
                </div>
              ) : businessDevices.length === 0 ? (
                <div className="flex flex-col items-center justify-center h-48 gap-3 text-text-muted text-center">
                  <div className="text-4xl opacity-50">📴</div>
                  <p className="font-semibold text-white">No active devices found.</p>
                  <p className="text-sm max-w-sm">This tenant has not activated any devices with their license key yet.</p>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  {businessDevices.map((d: any) => {
                    const isRevoked = d.status === 'revoked';
                    return (
                      <div key={d.device_id} className={`flex flex-col rounded-xl border p-4 transition-all ${isRevoked ? 'bg-rose-500/5 border-rose-500/20 opacity-75' : 'bg-surface border-border hover:border-indigo-500/30'}`}>
                        <div className="flex justify-between items-start mb-3">
                          <div>
                            <h3 className="font-bold text-white flex items-center gap-2">
                              {d.device_name || 'Unknown POS'}
                              {isRevoked && <span className="text-[10px] uppercase font-black bg-rose-500/20 text-rose-400 px-2 py-0.5 rounded-sm">Revoked</span>}
                            </h3>
                            <p className="text-xs text-text-muted">{d.os} • {d.ip_address}</p>
                          </div>
                          {!isRevoked && (
                            <button
                              onClick={() => {
                                if (confirm(`Are you sure you want to permanently revoke ${d.device_name || 'this device'}? The terminal will be instantly locked out.`)) {
                                  revokeDevice(d.device_id);
                                }
                              }}
                              className="shrink-0 text-xs font-bold text-rose-400 bg-rose-500/10 hover:bg-rose-500 hover:text-white px-3 py-1.5 rounded-lg transition-colors border border-rose-500/20"
                            >
                              Revoke
                            </button>
                          )}
                        </div>
                        <div className="bg-background/50 rounded-lg p-3 space-y-2 mt-auto border border-border/50">
                          <div>
                            <div className="text-[10px] font-bold text-text-muted uppercase tracking-widest mb-0.5">Hardware MAC/IMEI</div>
                            <div className="font-mono text-xs text-amber-300 break-all">{d.hardware_fingerprint}</div>
                          </div>
                          <div>
                            <div className="text-[10px] font-bold text-text-muted uppercase tracking-widest mb-0.5">Last Heartbeat</div>
                            <div className="text-xs text-white">{d.last_heartbeat ? new Date(d.last_heartbeat).toLocaleString() : 'Never'}</div>
                          </div>
                          <div>
                            <div className="text-[10px] font-bold text-text-muted uppercase tracking-widest mb-0.5">User Context</div>
                            <div className="text-xs text-white truncate" title={d.email}>{d.first_name} {d.last_name} ({d.email})</div>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
