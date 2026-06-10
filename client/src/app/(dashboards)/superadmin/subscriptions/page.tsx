'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useCurrency } from '@/lib/currency';
import toast from 'react-hot-toast';
import useSWR from 'swr';

const fetcher = (url: string) => api.get(url).then(res => Array.isArray(res.data) ? res.data : []);

// MODULE_CATEGORIES will be populated dynamically
type ModuleCategory = {
  title: string;
  icon: string;
  color: string;
  border: string;
  badge: string;
  modules: any[];
};

const PLAN_TYPES = [
  { value: 'online_web', label: 'Online Web', icon: '🌐' },
  { value: 'hybrid_offline_sync', label: 'Hybrid Offline', icon: '🔄' },
  { value: 'mobile_native', label: 'Mobile Native', icon: '📱' },
];

const defaultForm = { name: '', price: '0', interval: 'month', max_users: '1', max_locations: '1', plan_type: 'online_web', device_limit: '1', employee_limit: '1', enabled_modules: ['pos', 'inventory'] as string[] };

export default function SuperadminSubscriptions() {
  const { format, currentCurrency, convert } = useCurrency();
  const { data: swrPlans, mutate: mutatePlans, isLoading: loading } = useSWR('/superadmin/plans', fetcher);
  const plans = swrPlans || [];
  const [showModal, setShowModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [editingPlanId, setEditingPlanId] = useState<number | null>(null);
  const [form, setForm] = useState({...defaultForm});
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const [moduleCategories, setModuleCategories] = useState<ModuleCategory[]>([]);

  useEffect(() => { 
    fetchModules();
  }, []);

  const fetchModules = async () => {
    try {
      const res = await api.get('/superadmin/system-modules');
      const rawModules = Array.isArray(res.data) ? res.data : [];
      
      const cats: Record<string, ModuleCategory> = {};
      const colors = [
        { color: 'from-rose-500/20 to-rose-500/5', border: 'border-rose-500/30', badge: 'bg-rose-500/20 text-rose-400', icon: '🛒' },
        { color: 'from-blue-500/20 to-blue-500/5', border: 'border-blue-500/30', badge: 'bg-blue-500/20 text-blue-400', icon: '📊' },
        { color: 'from-violet-500/20 to-violet-500/5', border: 'border-violet-500/30', badge: 'bg-violet-500/20 text-violet-400', icon: '⚙️' },
        { color: 'from-amber-500/20 to-amber-500/5', border: 'border-amber-500/30', badge: 'bg-amber-500/20 text-amber-400', icon: '🔌' },
        { color: 'from-emerald-500/20 to-emerald-500/5', border: 'border-emerald-500/30', badge: 'bg-emerald-500/20 text-emerald-400', icon: '📦' },
        { color: 'from-cyan-500/20 to-cyan-500/5', border: 'border-cyan-500/30', badge: 'bg-cyan-500/20 text-cyan-400', icon: '👥' },
      ];

      rawModules.forEach(m => {
        const catName = m.category || 'Other';
        if (!cats[catName]) {
          const colorTheme = colors[Object.keys(cats).length % colors.length];
          cats[catName] = { 
            title: catName, 
            icon: colorTheme.icon, 
            color: colorTheme.color, 
            border: colorTheme.border, 
            badge: colorTheme.badge, 
            modules: [] 
          };
        }
        cats[catName].modules.push({ id: m.slug || m.name, label: m.name, desc: m.description || '' });
      });

      setModuleCategories(Object.values(cats).filter(c => c.modules.length > 0));
    } catch (e) {
      toast.error('Failed to fetch modules');
    }
  };



  const handleEdit = (plan: any) => {
    let parsedModules = ['pos', 'inventory'];
    if (Array.isArray(plan.enabled_modules)) parsedModules = plan.enabled_modules;
    else if (typeof plan.enabled_modules === 'string') { try { parsedModules = JSON.parse(plan.enabled_modules); } catch(e) {} }
    else if (Array.isArray(plan.features)) parsedModules = plan.features;
    setForm({ name: plan.name, price: plan.price.toString(), interval: plan.interval || 'month', max_users: plan.max_users?.toString() || '1', max_locations: plan.max_locations?.toString() || '1', plan_type: plan.plan_type || 'online_web', device_limit: plan.device_limit?.toString() || '1', employee_limit: plan.employee_limit?.toString() || '1', enabled_modules: parsedModules });
    setEditingPlanId(plan.id);
    setShowModal(true);
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this plan? Blocked if active tenants exist.')) return;
    const backup = [...plans];
    mutatePlans(plans.filter((p: any) => p.id !== id), false); // Optimistic UI
    try { 
      await api.delete(`/superadmin/plans/${id}`); 
      mutatePlans();
      toast.success('Plan deleted successfully'); 
    } catch (error: any) { 
      mutatePlans(backup, false); // Rollback
      toast.error(error.response?.data?.message || 'Failed to delete'); 
    }
  };

  const toggleModule = (modId: string) => {
    setForm(f => ({ ...f, enabled_modules: f.enabled_modules.includes(modId) ? f.enabled_modules.filter(m => m !== modId) : [...f.enabled_modules, modId] }));
  };

  const selectAllInCategory = (modules: {id: string}[]) => {
    const ids = modules.map(m => m.id);
    const allSelected = ids.every(id => form.enabled_modules.includes(id));
    if (allSelected) {
      setForm(f => ({ ...f, enabled_modules: f.enabled_modules.filter(m => !ids.includes(m)) }));
    } else {
      setForm(f => ({ ...f, enabled_modules: [...new Set([...f.enabled_modules, ...ids])] }));
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setErrors({});
    
    // Client-side quick check
    if (!form.name.trim()) {
      setErrors({ name: ['Plan name is required'] });
      setSubmitting(false);
      return;
    }
    if (parseFloat(form.price) < 0) {
      setErrors({ price: ['Price cannot be negative'] });
      setSubmitting(false);
      return;
    }

    try {
      const payload = { ...form, price: parseFloat(form.price) || 0, max_users: parseInt(form.max_users, 10) || parseInt(form.employee_limit, 10) || 1, max_locations: parseInt(form.max_locations, 10) || 1, plan_type: form.plan_type, device_limit: parseInt(form.device_limit, 10) || 1, employee_limit: parseInt(form.employee_limit, 10) || 1 };
      const cfg = { withCredentials: true, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': `Bearer ${localStorage.getItem('fastpos_token') || ''}` } };
      if (editingPlanId) { await api.put(`/superadmin/plans/${editingPlanId}`, payload, cfg); toast.success('Plan updated successfully'); }
      else { await api.post('/superadmin/plans', payload, cfg); toast.success('Plan created successfully'); }
      setShowModal(false); setEditingPlanId(null); setForm({...defaultForm}); mutatePlans();
    } catch (error: any) {

      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
        toast.error('Please correct the highlighted form errors');
      } else {
        toast.error('Failed to save plan: ' + (error.response?.data?.message || error.message || 'Validation error'));
      }
    } finally { setSubmitting(false); }
  };

  const getModuleLabels = (plan: any) => {
    let mods: string[] = [];
    if (Array.isArray(plan.enabled_modules)) mods = plan.enabled_modules;
    else if (typeof plan.enabled_modules === 'string') { try { mods = JSON.parse(plan.enabled_modules); } catch {} }
    const allMods = moduleCategories.flatMap(c => c.modules);
    return mods.map(id => allMods.find(m => m.id === id)?.label || id).slice(0, 4);
  };

  const inputCls = "bg-background/80 border border-white/[0.08] rounded-xl px-4 py-2.5 text-white outline-none focus:border-rose-500/50 focus:ring-1 focus:ring-rose-500/20 transition-all text-sm placeholder:text-white/20";
  const labelCls = "text-xs font-semibold text-white/50 uppercase tracking-wider";

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-rose-400 to-orange-500">Subscriptions & Billing</h1>
          <p className="text-white/40 mt-1 text-sm">Manage SaaS packages, pricing tiers, and active subscriptions.</p>
        </div>
        <button onClick={() => { setEditingPlanId(null); setForm({...defaultForm}); setShowModal(true); }} className="bg-gradient-to-r from-rose-500 to-orange-600 hover:from-rose-600 hover:to-orange-700 text-white px-6 py-2.5 rounded-xl shadow-lg shadow-rose-500/25 font-bold transition-all active:scale-[0.98] flex items-center gap-2">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Create Plan
        </button>
      </div>

      {/* Plans Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        {loading ? (
          <div className="col-span-3 flex justify-center p-16"><div className="w-8 h-8 border-2 border-rose-500/30 border-t-rose-500 rounded-full animate-spin" /></div>
        ) : plans.length === 0 ? (
          <div className="col-span-3 rounded-2xl border border-dashed border-white/10 p-16 text-center">
            <div className="text-4xl mb-3">📋</div>
            <p className="text-white/40 text-sm">No plans configured yet. Create your first plan above.</p>
          </div>
        ) : (
          plans.map((plan: any) => {
            const mods = getModuleLabels(plan);
            return (
              <div key={plan.id} className="group relative bg-gradient-to-br from-white/[0.06] to-white/[0.02] rounded-2xl border border-white/[0.08] p-6 flex flex-col gap-4 hover:border-rose-500/40 transition-all duration-300 hover:shadow-xl hover:shadow-rose-500/5">
                <div className="flex justify-between items-start">
                  <div>
                    <h3 className="text-xl font-bold text-white">{plan.name}</h3>
                    <span className="text-xs text-white/30 capitalize">{plan.plan_type?.replace(/_/g, ' ')}</span>
                  </div>
                  <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${plan.is_active !== false ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/15 text-red-400 border border-red-500/20'}`}>
                    {plan.is_active !== false ? '● Active' : '● Inactive'}
                  </span>
                </div>
                <div className="flex items-baseline gap-1">
                  <span className="text-3xl font-black text-white">{format(convert(parseFloat(plan.price), 'BDT', currentCurrency.code), currentCurrency.code)}</span>
                  <span className="text-white/30 text-sm">/{plan.interval}</span>
                </div>
                <div className="border-t border-white/[0.06] pt-3 grid grid-cols-3 gap-2 text-center">
                  <div><div className="text-lg font-bold text-white">{plan.employee_limit >= 999 ? '∞' : plan.employee_limit}</div><div className="text-[10px] text-white/30 uppercase">Seats</div></div>
                  <div><div className="text-lg font-bold text-white">{plan.device_limit >= 999 ? '∞' : plan.device_limit}</div><div className="text-[10px] text-white/30 uppercase">Devices</div></div>
                  <div><div className="text-lg font-bold text-white">{plan.max_locations >= 999 ? '∞' : plan.max_locations}</div><div className="text-[10px] text-white/30 uppercase">Locations</div></div>
                </div>
                {mods.length > 0 && (
                  <div className="flex flex-wrap gap-1.5">
                    {mods.map((m, i) => <span key={i} className="px-2 py-0.5 rounded-md bg-white/[0.05] text-[10px] text-white/50 border border-white/[0.06]">{m}</span>)}
                    {(Array.isArray(plan.enabled_modules) ? plan.enabled_modules.length : 0) > 4 && <span className="px-2 py-0.5 rounded-md bg-rose-500/10 text-[10px] text-rose-400">+{(Array.isArray(plan.enabled_modules) ? plan.enabled_modules.length : 0) - 4} more</span>}
                  </div>
                )}
                <div className="flex gap-2 border-t border-white/[0.06] pt-3 mt-auto">
                  <button onClick={() => handleEdit(plan)} className="flex-1 text-xs bg-white/[0.05] hover:bg-white/[0.1] text-white py-2 rounded-lg transition-colors font-medium">Edit</button>
                  <button onClick={() => handleDelete(plan.id)} className="text-xs bg-red-500/10 hover:bg-red-500/20 text-red-400 px-4 py-2 rounded-lg transition-colors font-medium">Delete</button>
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* ===== LANDSCAPE MODAL ===== */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md animate-in fade-in duration-200" onClick={() => setShowModal(false)}>
          <div className="bg-[#0f1117] border border-white/[0.08] w-full max-w-[1100px] rounded-2xl shadow-2xl shadow-black/50 relative max-h-[92vh] overflow-hidden flex flex-col" onClick={e => e.stopPropagation()}>
            {/* Modal Header */}
            <div className="flex items-center justify-between px-8 py-5 border-b border-white/[0.06] bg-gradient-to-r from-rose-500/[0.06] to-transparent shrink-0">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-orange-600 flex items-center justify-center text-lg shadow-lg shadow-rose-500/25">{editingPlanId ? '✏️' : '✨'}</div>
                <div>
                  <h2 className="text-xl font-bold text-white">{editingPlanId ? 'Edit Plan' : 'Create New Plan'}</h2>
                  <p className="text-xs text-white/30">Configure pricing, limits, and module access</p>
                </div>
              </div>
              <button onClick={() => setShowModal(false)} className="w-8 h-8 rounded-lg bg-white/[0.05] hover:bg-white/[0.1] flex items-center justify-center text-white/40 hover:text-white transition-colors">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>

            {/* Modal Body */}
            <form onSubmit={handleSubmit} className="flex flex-1 overflow-hidden">
              {/* LEFT PANEL — Plan Details */}
              <div className="w-[420px] shrink-0 p-6 overflow-y-auto custom-scrollbar border-r border-white/[0.06] flex flex-col gap-5">
                <div className="flex flex-col gap-1.5">
                  <label className={labelCls}>Plan Name</label>
                  <input required value={form.name} onChange={e => {setForm({...form, name: e.target.value}); setErrors(prev => ({...prev, name: []}))}} className={`${inputCls} ${errors.name ? 'border-red-500/50 focus:border-red-500 focus:ring-red-500/20' : ''}`} placeholder="e.g. Professional" />
                  {errors.name && <span className="text-[10px] text-red-400 font-medium animate-in fade-in">{errors.name[0]}</span>}
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="flex flex-col gap-1.5">
                    <label className={labelCls}>Price ({currentCurrency.code})</label>
                    <input type="number" step="0.01" required value={form.price} onChange={e => {setForm({...form, price: e.target.value}); setErrors(prev => ({...prev, price: []}))}} className={`${inputCls} ${errors.price ? 'border-red-500/50' : ''}`} />
                    {errors.price && <span className="text-[10px] text-red-400 font-medium">{errors.price[0]}</span>}
                  </div>
                  <div className="flex flex-col gap-1.5">
                    <label className={labelCls}>Billing Cycle</label>
                    <select value={form.interval} onChange={e => setForm({...form, interval: e.target.value})} className={inputCls}>
                      <option value="month">Monthly</option>
                      <option value="year">Yearly</option>
                    </select>
                  </div>
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className={labelCls}>Deployment Architecture</label>
                  <div className="grid grid-cols-3 gap-2">
                    {PLAN_TYPES.map(pt => (
                      <button key={pt.value} type="button" onClick={() => setForm({...form, plan_type: pt.value})}
                        className={`flex flex-col items-center gap-1 py-3 px-2 rounded-xl border text-xs font-medium transition-all ${form.plan_type === pt.value ? 'border-rose-500/50 bg-rose-500/10 text-rose-400 shadow-lg shadow-rose-500/10' : 'border-white/[0.06] bg-white/[0.02] text-white/40 hover:bg-white/[0.05]'}`}>
                        <span className="text-lg">{pt.icon}</span>
                        <span>{pt.label}</span>
                      </button>
                    ))}
                  </div>
                </div>

                <div className="p-4 rounded-xl bg-gradient-to-br from-white/[0.04] to-transparent border border-white/[0.06]">
                  <label className={`${labelCls} mb-3 block`}>Resource Limits</label>
                  <div className="grid grid-cols-2 gap-3">
                    {[
                      { label: 'Employee Seats', key: 'employee_limit' as const, icon: '👤' },
                      { label: 'Max Devices', key: 'device_limit' as const, icon: '💻' },
                      { label: 'Max Users', key: 'max_users' as const, icon: '👥' },
                      { label: 'Max Locations', key: 'max_locations' as const, icon: '📍' },
                    ].map(field => (
                      <div key={field.key} className="flex items-center gap-2 bg-background/50 rounded-lg px-3 py-2 border border-white/[0.04]">
                        <span className="text-sm">{field.icon}</span>
                        <div className="flex-1 min-w-0">
                          <div className="text-[10px] text-white/30 truncate">{field.label}</div>
                          <input type="number" min="1" value={form[field.key]} onChange={e => setForm({...form, [field.key]: e.target.value})} className="w-full bg-transparent text-white text-sm font-bold outline-none" />
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {/* RIGHT PANEL — Modules */}
              <div className="flex-1 flex flex-col overflow-hidden">
                <div className="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between shrink-0">
                  <div>
                    <h3 className="text-sm font-bold text-white">Enabled Modules</h3>
                    <p className="text-[11px] text-white/30">{form.enabled_modules.length} of 12 modules selected</p>
                  </div>
                  <div className="flex gap-2">
                    <button type="button" onClick={() => setForm(f => ({...f, enabled_modules: moduleCategories.flatMap(c => c.modules.map(m => m.id))}))} className="text-[10px] px-3 py-1.5 rounded-lg bg-white/[0.05] text-white/50 hover:text-white hover:bg-white/[0.1] transition-colors font-medium uppercase tracking-wider">Select All</button>
                    <button type="button" onClick={() => setForm(f => ({...f, enabled_modules: []}))} className="text-[10px] px-3 py-1.5 rounded-lg bg-white/[0.05] text-white/50 hover:text-white hover:bg-white/[0.1] transition-colors font-medium uppercase tracking-wider">Clear</button>
                  </div>
                </div>
                <div className="flex-1 overflow-y-auto custom-scrollbar p-6">
                  <div className="grid grid-cols-2 gap-4">
                    {moduleCategories.map((cat, idx) => {
                      const allSelected = cat.modules.length > 0 && cat.modules.every(m => form.enabled_modules.includes(m.id));
                      const someSelected = cat.modules.some(m => form.enabled_modules.includes(m.id));
                      return (
                        <div key={idx} className={`rounded-xl border ${cat.border} bg-gradient-to-br ${cat.color} p-4 flex flex-col gap-3 transition-all`}>
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                              <span className="text-lg">{cat.icon}</span>
                              <span className="text-xs font-bold text-white/80">{cat.title}</span>
                            </div>
                            <button type="button" onClick={() => selectAllInCategory(cat.modules)}
                               className={`text-[9px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider transition-all ${allSelected ? cat.badge : 'bg-white/[0.05] text-white/30 hover:text-white/60'}`}>
                               {allSelected ? 'All ✓' : someSelected ? 'Partial' : 'None'}
                            </button>
                          </div>
                          <div className="flex flex-col gap-1.5">
                            {cat.modules.map(mod => {
                              const checked = form.enabled_modules.includes(mod.id);
                              return (
                                <label key={mod.id} onClick={() => toggleModule(mod.id)}
                                  className={`flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer transition-all ${checked ? 'bg-white/[0.08] border border-white/[0.1]' : 'bg-transparent border border-transparent hover:bg-white/[0.04]'}`}>
                                  <div className={`w-4 h-4 rounded flex items-center justify-center shrink-0 transition-all ${checked ? 'bg-rose-500 shadow-md shadow-rose-500/30' : 'border border-white/20 bg-white/[0.03]'}`}>
                                    {checked && <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12" /></svg>}
                                  </div>
                                  <div className="min-w-0">
                                    <div className={`text-xs font-semibold transition-colors ${checked ? 'text-white' : 'text-white/50'}`}>{mod.label}</div>
                                    <div className="text-[10px] text-white/25 truncate">{mod.desc}</div>
                                  </div>
                                </label>
                              );
                            })}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>

                {/* Footer */}
                <div className="px-6 py-4 border-t border-white/[0.06] flex justify-between items-center shrink-0 bg-[#0a0b0f]">
                  <div className="flex items-center gap-4">
                    <div className="flex -space-x-1">
                      {form.enabled_modules.slice(0, 5).map((m, i) => <div key={i} className="w-5 h-5 rounded-full bg-gradient-to-br from-rose-500/40 to-orange-500/40 border-2 border-[#0a0b0f] flex items-center justify-center text-[7px] text-white/70 font-bold">{m.slice(0,2).toUpperCase()}</div>)}
                    </div>
                    <span className="text-xs text-white/30">{form.enabled_modules.length} module{form.enabled_modules.length !== 1 ? 's' : ''} enabled</span>
                  </div>
                  <div className="flex gap-3">
                    <button type="button" onClick={() => setShowModal(false)} className="px-5 py-2.5 rounded-xl text-white/40 hover:text-white font-medium text-sm transition-colors">Cancel</button>
                    <button type="submit" disabled={submitting} className="bg-gradient-to-r from-rose-500 to-orange-600 hover:from-rose-600 hover:to-orange-700 text-white px-8 py-2.5 rounded-xl font-bold shadow-lg shadow-rose-500/25 disabled:opacity-50 transition-all active:scale-[0.98] text-sm">
                      {submitting ? '⏳ Saving...' : editingPlanId ? '💾 Save Changes' : '🚀 Create Plan'}
                    </button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
