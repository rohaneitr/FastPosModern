'use client';

import React, { useState } from 'react';
import { DeviceState } from '../hooks/use-device-heartbeat';

interface DeviceLockedOverlayProps {
  deviceState: DeviceState;
  onManualActivate: (key: string) => Promise<void>;
  onForceActivate: () => Promise<void>;
  isActivating: boolean;
  onCancelLimit: () => void;
}

export function DeviceLockedOverlay({
  deviceState,
  onManualActivate,
  onForceActivate,
  isActivating,
  onCancelLimit
}: DeviceLockedOverlayProps) {
  const [manualKey, setManualKey] = useState('');

  if (!deviceState.locked) return null;

  return (
    <div className="absolute inset-0 z-50 bg-background/90 backdrop-blur-md flex items-center justify-center rounded-xl border border-border">
      <div className="bg-surface border border-rose-500/30 p-8 rounded-2xl w-full max-w-md shadow-2xl text-center">
        <span className="text-6xl mb-4 block">🚫</span>
        <h2 className="text-2xl font-bold text-white mb-2">Device Locked</h2>
        <p className="text-rose-400 font-medium mb-6">{deviceState.reason}</p>
        
        {deviceState.isLimitReached ? (
          <>
            <p className="text-text-muted text-sm mb-6">
              Device Limit Reached. Do you want to disconnect previous devices and activate this one?
            </p>
            <div className="flex gap-3 mt-2">
               <button 
                 onClick={onCancelLimit} 
                 className="flex-1 bg-surface border border-border hover:bg-white/5 text-white font-bold py-3 rounded-xl transition-all"
               >
                 Cancel
               </button>
               <button 
                 onClick={onForceActivate} 
                 disabled={isActivating} 
                 className="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 rounded-xl shadow-lg transition-all flex justify-center items-center gap-2"
               >
                 {isActivating && <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/>}
                 Force Activate Here
               </button>
            </div>
          </>
        ) : (
          <>
            <p className="text-text-muted text-sm mb-6">
              Please contact your business administrator to resolve this issue or manage device activations in settings.
            </p>
            
            <div className="border-t border-border pt-6 mt-2">
              <p className="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Manual Activation</p>
              <form 
                onSubmit={(e) => { e.preventDefault(); onManualActivate(manualKey); }} 
                className="flex flex-col gap-3"
              >
                <input 
                  type="text" 
                  value={manualKey} 
                  onChange={e => setManualKey(e.target.value)} 
                  placeholder="Enter License Key..." 
                  className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white text-center font-mono text-sm outline-none focus:border-primary transition-colors"
                  required 
                />
                <button 
                  type="submit" 
                  disabled={isActivating || !manualKey} 
                  className="w-full bg-primary hover:bg-primary-hover text-white font-bold py-3 rounded-xl shadow-lg disabled:opacity-50 transition-all flex justify-center items-center gap-2"
                >
                  {isActivating && <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/>}
                  Activate Device
                </button>
              </form>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
