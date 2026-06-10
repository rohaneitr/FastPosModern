'use client';

import React from 'react';
import { useBackgroundSync } from '@/features/pos/hooks/use-background-sync';
import { WifiOff, RefreshCw, CheckCircle2 } from 'lucide-react';

export function ConnectivityStatus() {
  const { isOnline, isSyncing, pendingCount } = useBackgroundSync();

  if (isOnline && pendingCount === 0 && !isSyncing) {
    // Optionally return nothing or a small green dot
    return null;
  }

  return (
    <div className={`fixed top-4 left-1/2 -translate-x-1/2 z-50 px-4 py-2 rounded-full font-bold text-sm shadow-lg flex items-center gap-2 transition-all duration-300 ${
      !isOnline 
        ? 'bg-amber-500/90 text-white shadow-amber-500/20' 
        : isSyncing 
          ? 'bg-blue-500/90 text-white shadow-blue-500/20 animate-pulse'
          : 'bg-emerald-500/90 text-white shadow-emerald-500/20'
    }`}>
      {!isOnline && (
        <>
          <WifiOff className="w-4 h-4" />
          <span>Offline Mode ({pendingCount} pending)</span>
        </>
      )}
      
      {isOnline && isSyncing && (
        <>
          <RefreshCw className="w-4 h-4 animate-spin" />
          <span>Syncing Transactions ({pendingCount})...</span>
        </>
      )}

      {isOnline && !isSyncing && pendingCount > 0 && (
        <>
          <RefreshCw className="w-4 h-4" />
          <span>{pendingCount} Pending Syncs</span>
        </>
      )}
    </div>
  );
}
