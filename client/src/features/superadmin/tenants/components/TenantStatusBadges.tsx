'use client';

import React from 'react';
import type { Tenant } from '../types';

interface TenantStatusBadgesProps {
  tenant: Tenant;
}

/**
 * TenantStatusBadges — Three stacked status pills
 * 1. Tenant active/suspended
 * 2. Subscription valid/expired
 * 3. License present/missing
 */
export function TenantStatusBadges({ tenant: b }: TenantStatusBadgesProps) {
  const isActive   = Boolean(b.is_active);
  const isLifetime = !b.subscription_expires_at;
  const isSubValid = (b.subscription_expires_at && new Date(b.subscription_expires_at) > new Date()) || isLifetime;

  return (
    <div className="flex flex-col gap-1.5 items-center w-28 mx-auto">
      {/* Tenant Status */}
      <span className={`w-full inline-flex justify-center items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border ${
        (b.status === 'active' || isActive)
          ? 'bg-success/10 text-success border-success/20'
          : 'bg-warning/10 text-warning border-warning/20'
      }`}>
        <span className={`w-1 h-1 rounded-full ${(b.status === 'active' || isActive) ? 'bg-success' : 'bg-warning'}`} />
        {(b.status === 'active' || isActive) ? 'Active' : (b.status || 'Suspended')}
      </span>

      {/* Subscription Status */}
      <span className={`w-full inline-flex justify-center items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border ${
        isSubValid
          ? 'bg-blue-500/10 text-blue-400 border-blue-500/20'
          : 'bg-rose-500/10 text-rose-400 border-rose-500/20'
      }`}>
        {isSubValid ? 'Subscribed' : 'Expired'}
      </span>

      {/* License Status */}
      <span className={`w-full inline-flex justify-center items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border ${
        b.license_key
          ? 'bg-teal-500/10 text-teal-400 border-teal-500/20'
          : 'bg-surface text-text-muted border-border'
      }`}>
        {b.license_key ? 'Licensed' : 'Unlicensed'}
      </span>
    </div>
  );
}
