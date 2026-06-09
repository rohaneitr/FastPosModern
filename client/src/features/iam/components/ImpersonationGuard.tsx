'use client';

import React, { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

export default function ImpersonationGuard() {
  const [isImpersonating, setIsImpersonating] = useState(false);
  const [tenantName, setTenantName] = useState('');
  const router = useRouter();

  useEffect(() => {
    const superadminToken = localStorage.getItem('fastpos_superadmin_token');
    const impersonatedTenant = localStorage.getItem('fastpos_impersonated_tenant');
    if (superadminToken) {
      setIsImpersonating(true);
      setTenantName(impersonatedTenant || 'Tenant');
    }
  }, []);

  const handleReturn = () => {
    const superadminToken = localStorage.getItem('fastpos_superadmin_token');
    if (superadminToken) {
      localStorage.setItem('fastpos_token', superadminToken);
      localStorage.removeItem('fastpos_superadmin_token');
      localStorage.removeItem('fastpos_impersonated_tenant');
      
      // Optionally fetch new user context or force reload
      window.location.href = '/superadmin/tenants';
    }
  };

  if (!isImpersonating) return null;

  return (
    <div className="fixed top-0 left-0 w-full z-[9999] bg-gradient-to-r from-rose-600 to-red-600 shadow-[0_0_20px_rgba(225,29,72,0.5)] border-b-4 border-rose-800 text-white px-4 py-2 flex items-center justify-center gap-4 animate-in slide-in-from-top">
      <svg className="w-5 h-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <span className="font-bold text-sm tracking-wide">
        SUPER ADMIN OVERRIDE ACTIVE: You are currently impersonating <span className="underline decoration-2 underline-offset-4">{tenantName}</span>. All actions taken are recorded.
      </span>
      <button 
        onClick={handleReturn}
        className="ml-4 flex items-center gap-2 bg-black/30 hover:bg-black/50 transition-colors px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest border border-white/20"
      >
        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
        Return to Super Admin
      </button>
    </div>
  );
}
