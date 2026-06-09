'use client';

import React from 'react';

export default function MaintenancePage() {
  return (
    <div className="min-h-screen bg-background flex flex-col items-center justify-center p-6 relative overflow-hidden">
      {/* Background Effects */}
      <div className="absolute inset-0 z-0">
        <div className="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-fuchsia-600/10 rounded-full blur-[120px] mix-blend-screen animate-pulse" />
        <div className="absolute bottom-1/4 right-1/4 w-[600px] h-[600px] bg-orange-600/10 rounded-full blur-[150px] mix-blend-screen animate-pulse delay-1000" />
      </div>

      <div className="z-10 text-center max-w-2xl animate-in fade-in slide-in-from-bottom-8 duration-1000">
        <div className="flex justify-center mb-8">
          <div className="relative">
            <div className="absolute inset-0 bg-orange-500 blur-xl opacity-50 animate-pulse rounded-full" />
            <div className="w-24 h-24 bg-surface border-2 border-orange-500/50 rounded-2xl flex items-center justify-center relative z-10 shadow-[0_0_30px_rgba(249,115,22,0.3)]">
              <svg className="w-12 h-12 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
          </div>
        </div>

        <h1 className="text-5xl font-extrabold text-white mb-4 tracking-tight drop-shadow-lg">
          System Under <span className="bg-clip-text text-transparent bg-gradient-to-r from-orange-400 to-amber-500">Maintenance</span>
        </h1>
        
        <p className="text-lg text-text-muted mb-8 leading-relaxed">
          FastPOS is currently undergoing scheduled platform upgrades to improve performance and security. All systems will be back online shortly. Thank you for your patience!
        </p>

        <div className="flex flex-col sm:flex-row gap-4 justify-center">
          <button 
            onClick={() => window.location.reload()} 
            className="px-8 py-3 rounded-xl bg-surface hover:bg-surface/80 border border-border text-white font-bold shadow-lg transition-all transform hover:scale-105 active:scale-95 flex items-center justify-center gap-2"
          >
            <svg className="w-5 h-5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Retry Connection
          </button>
          <a 
            href="mailto:support@fastpos.com"
            className="px-8 py-3 rounded-xl bg-orange-500/10 hover:bg-orange-500/20 border border-orange-500/30 text-orange-400 font-bold shadow-[0_0_15px_rgba(249,115,22,0.1)] transition-all flex items-center justify-center gap-2"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            Contact Support
          </a>
        </div>
      </div>
    </div>
  );
}
