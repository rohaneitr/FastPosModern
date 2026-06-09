'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { cn } from '@/lib/utils';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Lock, LogOut, KeyRound, CreditCard } from 'lucide-react';

interface LicenseGateProps {
  onActivate: (key: string) => Promise<void>;
  onLogout: () => void;
}

/**
 * License pending activation overlay.
 * Extracted from the business layout — renders when business.status === 'pending_activation'.
 */
export function LicenseGate({ onActivate, onLogout }: LicenseGateProps) {
  const [licenseKey, setLicenseKey] = useState('');
  const [isActivating, setIsActivating] = useState(false);

  const handleSubmit = async () => {
    if (!licenseKey.trim()) return alert('Please enter a valid license key.');
    setIsActivating(true);
    try {
      await onActivate(licenseKey.trim());
    } finally {
      setIsActivating(false);
    }
  };

  return (
    <div className="absolute inset-0 z-50 flex items-center justify-center bg-background/95 backdrop-blur-sm p-4">
      <div className="max-w-md w-full bg-surface border border-rose-500/30 rounded-2xl p-8 text-center shadow-2xl relative overflow-hidden">
        <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-rose-500 to-orange-500" />

        <div className="w-16 h-16 bg-rose-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
          <Lock className="w-7 h-7 text-rose-400" />
        </div>

        <h2 className="text-2xl font-black text-white mb-2">License Pending Activation</h2>
        <p className="text-text-muted text-sm mb-6">
          Your tenant license is currently inactive. Please activate your subscription or enter a
          valid license key to unlock the FastPOS platform.
        </p>

        <div className="flex flex-col gap-3">
          <input
            type="text"
            value={licenseKey}
            onChange={(e) => setLicenseKey(e.target.value)}
            placeholder="Enter License Key"
            className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-500 transition-colors text-center font-mono"
          />

          <Button
            onClick={handleSubmit}
            loading={isActivating}
            disabled={!licenseKey}
            className="w-full shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:shadow-[0_0_30px_rgba(16,185,129,0.5)]"
            icon={<KeyRound className="w-4 h-4" />}
          >
            Activate License
          </Button>

          <div className="flex items-center gap-4 my-2">
            <div className="h-px bg-border flex-1" />
            <span className="text-text-muted text-xs font-bold uppercase">or</span>
            <div className="h-px bg-border flex-1" />
          </div>

          <Link
            href="/business/billing"
            className="inline-flex items-center justify-center gap-2 w-full bg-surface border border-border hover:bg-white/5 text-white font-bold py-3 px-6 rounded-xl transition-all"
          >
            <CreditCard className="w-4 h-4" />
            View Subscription Plans
          </Link>

          <button
            onClick={onLogout}
            className="text-text-muted text-sm mt-2 hover:text-white transition-colors flex items-center justify-center gap-1.5"
          >
            <LogOut className="w-3.5 h-3.5" />
            Sign Out
          </button>
        </div>
      </div>
    </div>
  );
}
