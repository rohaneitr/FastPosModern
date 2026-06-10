'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import BulkMessageModal from '@/components/BulkMessageModal';
import toast from 'react-hot-toast';

export default function SuperadminPage() {
  const [businesses, setBusinesses] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  
  // Modules Modal State
  const [showModulesModal, setShowModulesModal] = useState(false);
  const [activeModules, setActiveModules] = useState<string[]>([]);
  const [selectedTenantId, setSelectedTenantId] = useState<number | null>(null);

  // Billing Modal State
  const [showBillingModal, setShowBillingModal] = useState(false);
  const [billingForm, setBillingForm] = useState({ duration: '1_month', status: 'Active' });
  
  // Create Tenant State
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [createForm, setCreateForm] = useState({ name: '', owner_email: '', password: '', plan_id: '', subdomain: '' });
  const [plans, setPlans] = useState<any[]>([]);

  // Bulk Messaging
  const [showBulkMessageModal, setShowBulkMessageModal] = useState(false);
  
  const [submitting, setSubmitting] = useState(false);
  const AVAILABLE_MODULES = [
    { id: 'core', label: 'Core POS & Inventory' },
    { id: 'crm', label: 'CRM & Loyalty' },
    { id: 'hr', label: 'HR Management' },
    { id: 'serial_tracking', label: 'Serial & IMEI Tracking' },
    { id: 'pharmacy', label: 'Pharmacy Vertical' },
    { id: 'restaurant', label: 'Restaurant Vertical' },
    { id: 'hardware_builder', label: 'PC/Hardware Builder' },
    { id: 'manufacturing', label: 'Manufacturing' }
  ];

  // Search and Filter State
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  
  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  
  const showToast = (message: string, type: 'success'|'error') => {
    if (type === 'success') toast.success(message);
    else toast.error(message);
  };

  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      setCurrentPage(1); // Reset to page 1 on new search
      fetchBusinesses();
    }, 500);
    return () => clearTimeout(delayDebounceFn);
  }, [searchTerm]);

  useEffect(() => {
    fetchBusinesses();
    fetchPlans();
  }, [currentPage]);

  const fetchPlans = async () => {
    try {
      const res = await api.get('/superadmin/plans');
      setPlans(res.data);
    } catch (e) {}
  };

  const fetchBusinesses = async () => {
    setLoading(true);
    try {
      const searchParam = searchTerm ? `&search=${encodeURIComponent(searchTerm)}` : '';
      const res = await api.get(`/superadmin/businesses?page=${currentPage}${searchParam}`);
      if (res.data) {
        setBusinesses(res.data.data || res.data);
        if (res.data.last_page) {
           setTotalPages(res.data.last_page);
           setTotalItems(res.data.total);
        }
      }
    } catch (err: any) {
      console.error("Failed to fetch businesses", err);
      if (err.response?.status === 401 || err.response?.status === 403) {
         window.location.href = '/login'; 
      }
    } finally {
      setLoading(false);
    }
  };

  const handleToggle = async (id: number) => {
    const backup = [...businesses];
    // Optimistic toggle
    const tenant = businesses.find(b => b.id === id);
    const newStatus = tenant ? !tenant.is_active : false;
    setBusinesses(prev => prev.map(b => b.id === id ? { ...b, is_active: newStatus } : b));
    
    try {
      const res = await api.post(`/superadmin/businesses/${id}/toggle`);
      setBusinesses(prev => prev.map(b => b.id === id ? { ...b, is_active: res.data.is_active } : b));
      showToast(`Tenant status updated to ${res.data.is_active ? 'Active' : 'Suspended'}`, 'success');
    } catch (err) {
      setBusinesses(backup); // Rollback
      showToast('Failed to toggle tenant status. Check permissions.', 'error');
      console.error(err);
    }
  };

  const handleOpenModules = (b: any) => {
    setSelectedTenantId(b.id);
    setActiveModules(b.active_modules || ['core']);
    setShowModulesModal(true);
  };

  const handleSaveModules = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post(`/superadmin/businesses/${selectedTenantId}/modules`, { active_modules: activeModules });
      showToast('Modules updated successfully', 'success');
      setShowModulesModal(false);
      fetchBusinesses();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to update modules', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const handleOpenBilling = (b: any) => {
    setSelectedTenantId(b.id);
    setBillingForm({ duration: '1_month', status: b.subscription_status_real || 'active' });
    setShowBillingModal(true);
  };

  const handleRenewSubscription = async () => {
    setSubmitting(true);
    try {
      const tenant = businesses.find(b => b.id === selectedTenantId);
      if (!tenant || !tenant.subscription_id) throw new Error("No subscription attached to this tenant.");
      await api.post(`/superadmin/subscriptions/${tenant.subscription_id}/renew`, { extension_period: billingForm.duration });
      showToast('Subscription renewed successfully', 'success');
      setShowBillingModal(false);
      fetchBusinesses();
    } catch (err: any) {
      showToast(err.response?.data?.message || err.message || 'Failed to renew subscription', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const handleOverrideStatus = async () => {
    setSubmitting(true);
    try {
      const tenant = businesses.find(b => b.id === selectedTenantId);
      if (!tenant || !tenant.subscription_id) throw new Error("No subscription attached to this tenant.");
      await api.patch(`/superadmin/subscriptions/${tenant.subscription_id}/status`, { status: billingForm.status.toLowerCase() });
      showToast('Subscription status overridden', 'success');
      setShowBillingModal(false);
      fetchBusinesses();
    } catch (err: any) {
      showToast(err.response?.data?.message || err.message || 'Failed to override status', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const handleCreateTenant = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post('/superadmin/businesses', createForm);
      showToast('Tenant created successfully', 'success');
      setShowCreateModal(false);
      setCreateForm({ name: '', owner_email: '', password: '', plan_id: '', subdomain: '' });
      fetchBusinesses();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to create tenant', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const handleImpersonate = async (b: any) => {
    try {
      showToast('Initiating God Mode...', 'success');
      const res = await api.post(`/superadmin/impersonate/${b.id}`);
      // Set up the impersonation state
      sessionStorage.setItem('fastpos_user', JSON.stringify(res.data.user));
      
      const domainPrefix = b.subdomain;
      const host = window.location.host;
      let newHost = host;
      
      // Simple parsing: if localhost:3000 -> tenant.localhost:3000
      // If superadmin.domain.com -> tenant.domain.com
      if (host.includes('localhost')) {
         newHost = `${domainPrefix}.localhost:3000`;
      } else {
         const parts = host.split('.');
         if (parts.length > 2) {
            parts[0] = domainPrefix;
            newHost = parts.join('.');
         } else {
            newHost = `${domainPrefix}.${host}`;
         }
      }
      
      window.location.href = `${window.location.protocol}//${newHost}/business`;
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to impersonate', 'error');
    }
  };

  const handleExport = async (id: number, name: string) => {
    try {
      showToast('Preparing backup...', 'success');
      const res = await api.get(`/superadmin/businesses/${id}/export`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `backup_${name.replace(/[^A-Za-z0-9]/g, '_')}.json`);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (err: any) {
      showToast('Failed to export tenant data', 'error');
    }
  };

  const handleGenerateLicense = async (b: any) => {
    if (!b.plan_id) {
        showToast('This tenant does not have a subscription plan attached.', 'error');
        return;
    }
    setSubmitting(true);
    try {
      showToast('Generating License Code...', 'success');
      const res = await api.post(`/superadmin/licenses/generate`, { tenant_id: b.id, plan_id: b.plan_id });
      // We can prompt it directly
      prompt('License Generated! Please copy this License Code and securely deliver it to the tenant:', res.data.license_key);
      fetchBusinesses();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to generate license', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Are you absolutely sure you want to PERMANENTLY delete this tenant and ALL associated data? This action cannot be undone.')) return;
    try {
      setSubmitting(true);
      await api.delete(`/superadmin/businesses/${id}`);
      showToast('Tenant permanently deleted', 'success');
      fetchBusinesses();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to delete tenant', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const filteredBusinesses = businesses.filter(b => {
    const matchesStatus = statusFilter === 'all' || 
                          (statusFilter === 'active' && Boolean(b.is_active)) || 
                          (statusFilter === 'suspended' && !Boolean(b.is_active));
    return matchesStatus;
  });

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-700 pb-12 relative">


      {/* Header Section */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-4xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-fuchsia-400 via-purple-400 to-indigo-500 tracking-tight">
            Tenant Management
          </h1>
          <p className="text-text-muted mt-2 text-sm max-w-xl leading-relaxed">
            Oversee all registered businesses, monitor subscription lifecycles, and control platform access seamlessly.
          </p>
        </div>
        <div className="flex gap-3 w-full md:w-auto">
          <button onClick={() => window.location.href = '/superadmin/subscriptions'} className="flex-1 md:flex-none bg-surface/50 hover:bg-surface text-white border border-border px-5 py-2.5 rounded-xl font-medium transition-all shadow-sm flex items-center justify-center gap-2 group">
            <svg className="w-4 h-4 text-text-muted group-hover:text-fuchsia-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
            Manage Packages
          </button>
          <button onClick={() => setShowBulkMessageModal(true)} className="flex-1 md:flex-none bg-primary/20 text-primary hover:bg-primary/30 border border-primary/30 px-5 py-2.5 rounded-xl font-medium transition-all shadow-sm flex items-center justify-center gap-2 group">
            <svg className="w-4 h-4 text-primary group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
            Send Bulk Message
          </button>
          <button onClick={() => setShowCreateModal(true)} className="flex-1 md:flex-none bg-fuchsia-600 hover:bg-fuchsia-500 text-white shadow-lg shadow-fuchsia-500/20 px-5 py-2.5 rounded-xl font-bold transition-all flex items-center justify-center gap-2 active:scale-95">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M12 4v16m8-8H4" /></svg>
            New Tenant
          </button>
        </div>
      </div>

      {/* Filters & Search */}
      <div className="glass-card rounded-2xl border border-border p-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div className="relative w-full sm:max-w-md">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg className="w-5 h-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          </div>
          <input 
            type="text" 
            placeholder="Search by business name, owner, or email..." 
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full bg-background border border-border rounded-xl pl-10 pr-4 py-2.5 text-white outline-none focus:border-fuchsia-500/50 focus:ring-2 focus:ring-fuchsia-500/20 transition-all placeholder:text-text-muted"
          />
        </div>
        <div className="flex bg-background border border-border rounded-xl p-1 w-full sm:w-auto">
          {['all', 'active', 'suspended'].map(status => (
            <button 
              key={status}
              onClick={() => setStatusFilter(status)}
              className={`flex-1 sm:px-6 py-1.5 rounded-lg text-sm font-semibold capitalize transition-all ${
                statusFilter === status 
                  ? 'bg-surface text-white shadow-sm' 
                  : 'text-text-muted hover:text-white hover:bg-surface/50'
              }`}
            >
              {status}
            </button>
          ))}
        </div>
      </div>

      {/* Table Section */}
      <div className="glass-card rounded-2xl overflow-hidden border border-border shadow-xl">
        <div className="overflow-x-auto">
          <table className="w-full text-left whitespace-nowrap">
            <thead className="bg-surface border-b border-border text-xs uppercase tracking-wider font-bold text-text-muted">
              <tr>
                <th className="px-6 py-4">Business Details</th>
                <th className="px-6 py-4">Owner Contact</th>
                <th className="px-6 py-4">Registered Date</th>
                <th className="px-6 py-4">Subscription</th>
                <th className="px-6 py-4">Quotas</th>
                <th className="px-6 py-4 text-center">Status</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border/50">
              {loading ? (
                Array.from({length: 5}).map((_, i) => (
                  <tr key={i} className="animate-pulse">
                    <td className="px-6 py-5"><div className="h-5 bg-surface rounded-md w-3/4 mb-2"></div><div className="h-3 bg-surface rounded-md w-1/2"></div></td>
                    <td className="px-6 py-5"><div className="h-4 bg-surface rounded-md w-full mb-2"></div><div className="h-3 bg-surface rounded-md w-2/3"></div></td>
                    <td className="px-6 py-5"><div className="h-4 bg-surface rounded-md w-24"></div></td>
                    <td className="px-6 py-5"><div className="h-4 bg-surface rounded-md w-20"></div></td>
                    <td className="px-6 py-5"><div className="h-4 bg-surface rounded-md w-full"></div></td>
                    <td className="px-6 py-5 text-center"><div className="h-6 bg-surface rounded-full w-20 mx-auto"></div></td>
                    <td className="px-6 py-5 text-right"><div className="h-8 bg-surface rounded-lg w-24 ml-auto"></div></td>
                  </tr>
                ))
              ) : filteredBusinesses.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-6 py-16 text-center">
                    <div className="flex flex-col items-center justify-center text-text-muted">
                      <svg className="w-16 h-16 mb-4 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                      <p className="text-lg font-medium text-white mb-1">No Tenants Found</p>
                      <p className="text-sm">We couldn't find any businesses matching your filters.</p>
                      {(searchTerm || statusFilter !== 'all') && (
                        <button onClick={() => {setSearchTerm(''); setStatusFilter('all');}} className="mt-4 text-fuchsia-400 hover:text-fuchsia-300 text-sm font-semibold underline underline-offset-4 transition-colors">
                          Clear all filters
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ) : (
                filteredBusinesses.map(b => {
                  const isActive = Boolean(b.is_active);
                  const isLifetime = !b.subscription_expires_at;
                  return (
                    <tr key={b.id} className={`group hover:bg-surface/30 transition-colors ${!isActive ? 'opacity-60 bg-background/50' : ''}`}>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-4">
                          <div className={`w-12 h-12 rounded-xl flex items-center justify-center font-bold text-xl shadow-inner
                            ${isActive ? 'bg-gradient-to-br from-indigo-500/20 to-purple-500/20 text-indigo-400 border border-indigo-500/20' : 'bg-surface text-text-muted border border-border'}`}
                          >
                            {b.business_name.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <div className="font-bold text-base text-white group-hover:text-fuchsia-400 transition-colors">{b.business_name}</div>
                            <div className="text-xs text-text-muted mt-0.5">ID: {b.id}</div>
                            {b.url && (
                              <div className="flex items-center gap-2 mt-1.5 bg-surface/50 border border-border/50 rounded-md px-2 py-1 w-fit">
                                <a href={b.url} target="_blank" rel="noreferrer" className="text-[10px] font-mono text-blue-400 hover:text-blue-300 hover:underline">{b.url}</a>
                                <button onClick={() => { navigator.clipboard.writeText(b.url); showToast('URL Copied', 'success'); }} className="text-text-muted hover:text-white" title="Copy URL">
                                  <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                </button>
                              </div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="font-medium text-gray-200">{b.owner_name}</div>
                        <div className="text-xs text-primary mt-0.5 flex items-center gap-1 hover:underline cursor-pointer">
                          <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                          {b.owner_email}
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-text-muted font-medium">
                        {new Date(b.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}
                      </td>
                      <td className="px-6 py-4">
                        {isLifetime ? (
                          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-500/10 text-blue-400 text-xs font-bold border border-blue-500/20">
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                            Lifetime
                          </span>
                        ) : (
                          <span className="text-sm font-medium text-text-muted">
                            {new Date(b.subscription_expires_at).toLocaleDateString()}
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex flex-col gap-2 w-32">
                          <div>
                            <div className="flex justify-between text-[10px] text-text-muted mb-1">
                              <span>Users</span>
                              <span>{b.users_count || 0} / {b.plan_max_users || 'âˆž'}</span>
                            </div>
                            <div className="h-1.5 w-full bg-surface rounded-full overflow-hidden">
                              <div className="h-full bg-fuchsia-500 rounded-full" style={{ width: `${Math.min(100, ((b.users_count || 0) / (b.plan_max_users || 1)) * 100)}%` }}></div>
                            </div>
                          </div>
                          <div>
                            <div className="flex justify-between text-[10px] text-text-muted mb-1">
                              <span>Locations</span>
                              <span>{b.locations_count || 0} / {b.plan_max_locations || 'âˆž'}</span>
                            </div>
                            <div className="h-1.5 w-full bg-surface rounded-full overflow-hidden">
                              <div className="h-full bg-sky-500 rounded-full" style={{ width: `${Math.min(100, ((b.locations_count || 0) / (b.plan_max_locations || 1)) * 100)}%` }}></div>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex flex-col gap-1.5 items-center w-28 mx-auto">
                          {/* 1. Tenant Status */}
                          <span className={`w-full inline-flex justify-center items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border
                            ${(b.status === 'active' || isActive) 
                              ? 'bg-success/10 text-success border-success/20' 
                              : 'bg-warning/10 text-warning border-warning/20'
                            }`}
                          >
                            <span className={`w-1 h-1 rounded-full ${(b.status === 'active' || isActive) ? 'bg-success' : 'bg-warning'}`}></span>
                            {(b.status === 'active' || isActive) ? 'Active' : (b.status || 'Suspended')}
                          </span>
                          
                          {/* 2. Subscription */}
                          <span className={`w-full inline-flex justify-center items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border
                            ${(b.subscription_expires_at && new Date(b.subscription_expires_at) > new Date()) || isLifetime
                              ? 'bg-blue-500/10 text-blue-400 border-blue-500/20' 
                              : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
                            }`}
                          >
                            {(b.subscription_expires_at && new Date(b.subscription_expires_at) > new Date()) || isLifetime ? 'Subscribed' : 'Expired'}
                          </span>

                          {/* 3. License */}
                          <span className={`w-full inline-flex justify-center items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border
                            ${b.license_key 
                              ? 'bg-teal-500/10 text-teal-400 border-teal-500/20' 
                              : 'bg-surface text-text-muted border-border'
                            }`}
                          >
                            {b.license_key ? 'Licensed' : 'Unlicensed'}
                          </span>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-right">
                        <div className="flex justify-end gap-1.5 flex-wrap max-w-[250px] ml-auto">
                          {b.subscription_status === 'pending_activation' && (
                            <button 
                              onClick={() => handleGenerateLicense(b)}
                              className="px-3 py-1.5 bg-gradient-to-r from-fuchsia-600 to-indigo-600 hover:from-fuchsia-500 hover:to-indigo-500 text-white rounded-lg text-xs font-bold transition-all shadow-md w-full mb-1 flex items-center justify-center gap-2"
                              title="Generate License"
                            >
                              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                              Generate License
                            </button>
                          )}
                          <button 
                            onClick={() => handleImpersonate(b)}
                            className="px-3 py-1.5 bg-gradient-to-r from-red-500/10 to-orange-500/10 hover:from-red-500/20 hover:to-orange-500/20 text-red-400 border border-red-500/30 rounded-lg text-xs font-bold transition-all shadow-sm"
                            title="Impersonate"
                          >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                          </button>
                          <button 
                            onClick={() => handleExport(b.id, b.business_name)}
                            className="px-3 py-1.5 bg-surface hover:bg-surface/80 text-emerald-400 border border-emerald-500/30 rounded-lg text-xs font-bold transition-all shadow-sm"
                            title="Export Backup"
                          >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                          </button>
                          <button 
                            onClick={() => handleOpenBilling(b)}
                            className="px-3 py-1.5 bg-surface hover:bg-surface/80 text-blue-400 border border-blue-500/30 rounded-lg text-xs font-bold transition-all shadow-sm"
                            title="Billing"
                          >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                          </button>
                          <button 
                            onClick={() => handleOpenModules(b)}
                            className="px-3 py-1.5 bg-surface hover:bg-surface/80 text-fuchsia-400 border border-fuchsia-500/30 rounded-lg text-xs font-bold transition-all shadow-sm"
                            title="Features"
                          >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                          </button>
                          <button 
                            onClick={() => handleToggle(b.id)}
                            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm ${
                              isActive 
                                ? 'bg-surface hover:bg-yellow-500/20 text-text-muted hover:text-yellow-400 border border-border hover:border-yellow-500/30' 
                                : 'bg-success/10 hover:bg-success/20 text-success-400 border border-success/30'
                            }`}
                            title={isActive ? "Suspend" : "Activate"}
                          >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={isActive ? "M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" : "M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"} /></svg>
                          </button>
                          <button 
                            onClick={() => handleDelete(b.id)}
                            className="px-3 py-1.5 bg-danger/10 hover:bg-danger/20 text-danger-400 border border-danger/30 rounded-lg text-xs font-bold transition-all shadow-sm"
                            title="Hard Delete"
                          >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
        
        {/* Pagination Footer */}
        {totalPages > 1 && !loading && (
          <div className="bg-surface/50 border-t border-border px-6 py-4 flex items-center justify-between">
            <span className="text-sm text-text-muted font-medium">
              Showing page <span className="text-white">{currentPage}</span> of <span className="text-white">{totalPages}</span> 
              <span className="mx-2 opacity-50">|</span> 
              Total records: <span className="text-white">{totalItems}</span>
            </span>
            <div className="flex gap-2">
              <button 
                disabled={currentPage === 1}
                onClick={() => setCurrentPage(p => p - 1)}
                className="px-4 py-2 bg-background border border-border rounded-lg text-sm font-medium hover:bg-surface disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                Previous
              </button>
              <button 
                disabled={currentPage === totalPages}
                onClick={() => setCurrentPage(p => p + 1)}
                className="px-4 py-2 bg-background border border-border rounded-lg text-sm font-medium hover:bg-surface disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Manage Modules Modal */}
      {showModulesModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-surface border border-border w-full max-w-md rounded-2xl shadow-2xl p-6 relative">
            <button onClick={() => setShowModulesModal(false)} className="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            <h2 className="text-2xl font-bold text-white mb-2">Manage SaaS Features</h2>
            <p className="text-text-muted text-sm mb-6">Toggle premium modules for this tenant.</p>
            
            <form onSubmit={handleSaveModules} className="flex flex-col gap-4">
              <div className="space-y-3 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                {AVAILABLE_MODULES.map(mod => (
                  <label key={mod.id} className="flex items-center gap-3 p-3 bg-background border border-border rounded-xl cursor-pointer hover:border-primary/50 transition-colors">
                    <input 
                      type="checkbox" 
                      className="w-5 h-5 accent-fuchsia-500 rounded bg-surface border-border" 
                      checked={activeModules.includes(mod.id)}
                      onChange={(e) => {
                        if (e.target.checked) setActiveModules([...activeModules, mod.id]);
                        else setActiveModules(activeModules.filter(m => m !== mod.id));
                      }}
                    />
                    <span className="font-semibold text-white">{mod.label}</span>
                  </label>
                ))}
              </div>
              <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={() => setShowModulesModal(false)} className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white font-medium">Cancel</button>
                <button type="submit" disabled={submitting} className="bg-gradient-to-r from-fuchsia-500 to-indigo-500 hover:from-fuchsia-400 hover:to-indigo-400 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg disabled:opacity-50">
                  {submitting ? 'Saving...' : 'Save Features'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Billing & Subscription Modal */}
      {showBillingModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-surface border border-border w-full max-w-md rounded-2xl shadow-2xl p-6 relative">
            <button onClick={() => setShowBillingModal(false)} className="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            <h2 className="text-2xl font-bold text-white mb-2">Subscription & Billing</h2>
            <p className="text-text-muted text-sm mb-6">Manage billing lifecycle for this tenant.</p>
            
            <div className="flex flex-col gap-6">
              {/* Renew Section */}
              <div className="bg-background border border-border p-4 rounded-xl flex flex-col gap-3">
                <h3 className="font-bold text-white text-sm">Renew Subscription</h3>
                <div className="flex gap-2">
                  <select 
                    value={billingForm.duration}
                    onChange={(e) => setBillingForm({...billingForm, duration: e.target.value})}
                    className="flex-1 bg-surface border border-border rounded-lg px-3 py-2 text-white outline-none focus:border-blue-500/50"
                  >
                    <option value="1_month">+1 Month</option>
                    <option value="1_year">+1 Year</option>
                  </select>
                  <button 
                    onClick={handleRenewSubscription} disabled={submitting}
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
                    onChange={(e) => setBillingForm({...billingForm, status: e.target.value})}
                    className="flex-1 bg-surface border border-border rounded-lg px-3 py-2 text-white outline-none focus:border-orange-500/50"
                  >
                    <option value="active">Active</option>
                    <option value="past_due">Past Due</option>
                    <option value="suspended">Suspended</option>
                    <option value="canceled">Canceled</option>
                  </select>
                  <button 
                    onClick={handleOverrideStatus} disabled={submitting}
                    className="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-bold transition-colors disabled:opacity-50"
                  >
                    Override
                  </button>
                </div>
              </div>
            </div>
            
            <div className="flex justify-end mt-6">
              <button onClick={() => setShowBillingModal(false)} className="px-5 py-2 rounded-lg bg-surface hover:bg-surface/80 text-white font-medium">Close</button>
            </div>
          </div>
        </div>
      )}

      {/* Create Tenant Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-surface border border-border w-full max-w-md rounded-2xl shadow-2xl p-6 relative max-h-[90vh] overflow-y-auto custom-scrollbar">
            <button onClick={() => setShowCreateModal(false)} className="absolute top-4 right-4 text-text-muted hover:text-white">✕</button>
            <h2 className="text-2xl font-bold text-white mb-2">Create New Tenant</h2>
            <p className="text-text-muted text-sm mb-6">Provision a new SaaS tenant account.</p>
            
            <form onSubmit={handleCreateTenant} className="flex flex-col gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Business Name *</label>
                <input required value={createForm.name} onChange={e => setCreateForm({...createForm, name: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary/50" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Owner Email *</label>
                <input required type="email" value={createForm.owner_email} onChange={e => setCreateForm({...createForm, owner_email: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary/50" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Temporary Password *</label>
                <input required type="password" value={createForm.password} onChange={e => setCreateForm({...createForm, password: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary/50" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Subdomain (Optional)</label>
                <input value={createForm.subdomain} onChange={e => setCreateForm({...createForm, subdomain: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary/50" placeholder="mybusiness" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Select Subscription Plan *</label>
                <select required value={createForm.plan_id} onChange={e => setCreateForm({...createForm, plan_id: e.target.value})} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary/50">
                  <option value="">Select a plan</option>
                  {plans.map(p => <option key={p.id} value={p.id}>{p.name} - {p.price}/{p.interval}</option>)}
                </select>
              </div>

              <div className="flex justify-end gap-3 mt-4">
                <button type="button" onClick={() => setShowCreateModal(false)} className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white font-medium">Cancel</button>
                <button type="submit" disabled={submitting} className="bg-gradient-to-r from-fuchsia-500 to-indigo-500 hover:from-fuchsia-400 hover:to-indigo-400 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg disabled:opacity-50">
                  {submitting ? 'Creating...' : 'Create Tenant'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Bulk Message Modal */}
      <BulkMessageModal 
        isOpen={showBulkMessageModal}
        onClose={() => setShowBulkMessageModal(false)}
        users={businesses.filter(b => b.owner_id).map(b => ({
          id: b.owner_id,
          name: `${b.name} (Admin)`,
          email: 'Tenant Admin'
        }))}
      />
    </div>
  );
}
