'use client';

import React from 'react';
import { useRouter } from 'next/navigation';

export default function SubscriptionExpiredPage() {
  const router = useRouter();

  return (
    <div className="flex flex-col items-center justify-center min-h-[80vh] animate-in fade-in zoom-in duration-500 text-center">
      <div className="bg-rose-500/10 border border-rose-500/30 rounded-full p-8 mb-6 shadow-[0_0_50px_rgba(244,63,94,0.2)]">
        <span className="text-7xl">🚫</span>
      </div>
      
      <h1 className="text-4xl md:text-5xl font-black text-white mb-4 tracking-tight">
        Access <span className="text-rose-500">Locked</span>
      </h1>
      
      <p className="text-xl text-text-muted max-w-2xl mx-auto mb-8 font-medium">
        Your subscription has expired. Please contact <span className="text-white font-bold">Fast Computer & Technology Support</span> to renew your license.
      </p>

      <div className="flex gap-4">
        <button 
          onClick={() => window.location.href = 'mailto:support@fastcomputer.com'}
          className="bg-rose-600 hover:bg-rose-700 text-white px-8 py-3 rounded-xl font-bold transition-all shadow-lg shadow-rose-600/30 flex items-center gap-2"
        >
          ✉️ Contact Support
        </button>
        <button 
          onClick={() => router.push('/user/logout')}
          className="bg-surface hover:bg-white/5 border border-border text-white px-8 py-3 rounded-xl font-bold transition-all"
        >
          Logout
        </button>
      </div>
    </div>
  );
}
