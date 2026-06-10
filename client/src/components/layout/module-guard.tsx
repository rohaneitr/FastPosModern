'use client';

import React from 'react';
import { useEntitlements } from '@/hooks/useEntitlements';
import { ShieldAlert, Lock, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ModuleGuardProps {
  moduleSlug: string | string[];
  children: React.ReactNode;
}

export function ModuleGuard({ moduleSlug, children }: ModuleGuardProps) {
  const { hasModule } = useEntitlements();
  
  const slugs = Array.isArray(moduleSlug) ? moduleSlug : [moduleSlug];
  const isAllowed = slugs.some(slug => hasModule(slug));

  if (isAllowed) {
    return <>{children}</>;
  }

  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] p-8 animate-in fade-in zoom-in duration-500">
      <div className="glass-card max-w-lg w-full p-8 rounded-2xl border border-rose-500/20 text-center relative overflow-hidden">
        {/* Decorative background glow */}
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-rose-500/10 blur-[80px] rounded-full pointer-events-none" />

        <div className="relative z-10 flex flex-col items-center">
          <div className="w-20 h-20 rounded-2xl bg-rose-500/10 flex items-center justify-center mb-6 border border-rose-500/20">
            <Lock className="w-10 h-10 text-rose-400" />
          </div>

          <h1 className="text-2xl font-black text-white mb-2">Feature Locked</h1>
          
          <p className="text-text-muted mb-8 leading-relaxed">
            The <strong className="text-white">{(slugs[0] || '').replace('_', ' ').toUpperCase()}</strong> module is not included in your current subscription plan. Upgrade your plan to unlock this enterprise capability and streamline your operations.
          </p>

          <div className="flex gap-4 w-full justify-center">
            <Button variant="secondary" onClick={() => window.history.back()}>
              Go Back
            </Button>
            <Button className="bg-gradient-to-r from-emerald-500 to-emerald-400 hover:from-emerald-600 hover:to-emerald-500 text-white shadow-lg shadow-emerald-500/20 border-0 group">
              Upgrade Plan
              <ArrowRight className="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" />
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
