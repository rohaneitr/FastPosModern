'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function SuperadminPage() {
  const [businesses, setBusinesses] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  
  // Search and Filter State
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  
  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  
  // Toast Notification State
  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({message, type});
    setTimeout(() => setToast(null), 4000);
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
  }, [currentPage]);

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
    try {
      const res = await api.post(`/superadmin/businesses/${id}/toggle`);
      setBusinesses(prev => prev.map(b => b.id === id ? { ...b, is_active: res.data.is_active } : b));
      showToast(`Tenant status updated to ${res.data.is_active ? 'Active' : 'Suspended'}`, 'success');
    } catch (err) {
      showToast('Failed to toggle tenant status. Check permissions.', 'error');
      console.error(err);
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
      {/* Toast Notification */}
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10
          ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/50 backdrop-blur-md' : 'bg-danger/20 text-danger-300 border border-danger/50 backdrop-blur-md'}`}
        >
          {toast.type === 'success' ? (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
          ) : (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          )}
          {toast.message}
        </div>
      )}

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
          <button onClick={() => showToast('Tenant creation API not yet connected', 'error')} className="flex-1 md:flex-none bg-gradient-to-r from-fuchsia-500 to-indigo-500 hover:from-fuchsia-400 hover:to-indigo-400 text-white px-6 py-2.5 rounded-xl shadow-[0_0_20px_rgba(192,38,211,0.3)] font-bold transition-all flex items-center justify-center gap-2 transform hover:scale-105 active:scale-95">
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
                      <td className="px-6 py-4 text-center">
                        <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border
                          ${isActive 
                            ? 'bg-success/10 text-success border-success/20 shadow-[0_0_10px_rgba(16,185,129,0.1)]' 
                            : 'bg-danger/10 text-danger border-danger/20'
                          }`}
                        >
                          <span className={`w-1.5 h-1.5 rounded-full ${isActive ? 'bg-success animate-pulse' : 'bg-danger'}`}></span>
                          {isActive ? 'Active' : 'Suspended'}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-right">
                        <button 
                          onClick={() => handleToggle(b.id)}
                          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all transform hover:scale-105 active:scale-95 shadow-sm ${
                            isActive 
                              ? 'bg-surface hover:bg-danger/20 text-text-muted hover:text-danger-400 border border-border hover:border-danger/30' 
                              : 'bg-success/10 hover:bg-success/20 text-success-400 border border-success/30'
                          }`}
                        >
                          {isActive ? 'Suspend' : 'Activate Tenant'}
                        </button>
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
    </div>
  );
}
