'use client';

import React, { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';

export default function LicenseActivationPage() {
  const router = useRouter();
  const [licenseKey, setLicenseKey] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const [tenantName, setTenantName] = useState('');

  useEffect(() => {
    // Attempt to grab tenant context
    const userJson = localStorage.getItem('fastpos_user') || sessionStorage.getItem('fastpos_user');
    if (userJson) {
      try {
        const user = JSON.parse(userJson);
        setTenantName(user?.business?.name || 'Your Business');
      } catch (e) {}
    }
  }, []);

  const handleActivate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!licenseKey.trim()) {
      setError('Please enter a valid License Key');
      return;
    }

    setLoading(true);
    setError('');

    try {
      const res = await api.post('/tenant/activate', { license_key: licenseKey.trim() });
      setSuccess(true);
      setTimeout(() => {
        router.push('/business'); // Redirect to POS/Dashboard after success
      }, 2000);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Invalid License Key. Please try again or contact support.');
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-6 text-white relative overflow-hidden">
        <div className="absolute inset-0 bg-emerald-500/10 blur-[150px] pointer-events-none" />
        <div className="max-w-md w-full glass-card border border-emerald-500/50 p-10 rounded-[2rem] shadow-[0_0_50px_rgba(16,185,129,0.2)] flex flex-col items-center text-center animate-in zoom-in duration-500 z-10">
          <div className="w-24 h-24 bg-emerald-500/20 rounded-full flex items-center justify-center mb-6">
            <svg className="w-12 h-12 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" /></svg>
          </div>
          <h2 className="text-3xl font-black text-white mb-3">Tenant Activated!</h2>
          <p className="text-emerald-100/70 font-medium">Your license has been successfully verified. Welcome to FastPOS.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-6 text-white relative overflow-hidden">
      {/* Background decorations */}
      <div className="absolute top-[-10%] right-[-5%] w-[500px] h-[500px] bg-indigo-500/15 rounded-full blur-[150px] pointer-events-none" />
      <div className="absolute bottom-[-10%] left-[-5%] w-[500px] h-[500px] bg-cyan-500/15 rounded-full blur-[150px] pointer-events-none" />

      <div className="max-w-md w-full glass-card backdrop-blur-2xl border border-white/10 p-8 rounded-[2rem] shadow-2xl z-10 animate-in slide-in-from-bottom-8 duration-700">
        <div className="text-center mb-10">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-tr from-indigo-500/20 to-cyan-500/20 border border-white/5 mb-6 shadow-inner">
            <span className="text-4xl">🔐</span>
          </div>
          <h1 className="text-3xl font-black tracking-tight mb-3 bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 to-cyan-400">
            Activate License
          </h1>
          <p className="text-text-muted text-sm px-2">
            Welcome, <span className="font-bold text-white">{tenantName}</span>! Please paste your tenant License Key to unlock the platform.
          </p>
        </div>

        <form onSubmit={handleActivate} className="flex flex-col gap-6">
          <div className="space-y-2">
            <label className="text-sm font-bold tracking-wide text-text-muted/80 uppercase ml-1">License Key</label>
            <input 
              type="text" 
              placeholder="Paste License Key here..." 
              value={licenseKey}
              onChange={(e) => setLicenseKey(e.target.value)}
              className="w-full bg-black/40 border border-white/10 rounded-2xl px-5 py-4 text-white font-mono tracking-widest text-center outline-none focus:border-indigo-500/50 focus:ring-4 focus:ring-indigo-500/10 transition-all placeholder:text-white/20"
            />
          </div>

          {error && (
            <div className="bg-rose-500/10 border border-rose-500/20 text-rose-400 px-5 py-4 rounded-2xl text-sm font-bold flex items-start gap-3 animate-in fade-in">
              <svg className="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <span>{error}</span>
            </div>
          )}

          <button 
            type="submit" 
            disabled={loading || !licenseKey.trim()}
            className="w-full bg-gradient-to-r from-indigo-500 to-cyan-500 hover:from-indigo-400 hover:to-cyan-400 text-white font-black py-4 px-6 rounded-2xl shadow-[0_0_30px_rgba(99,102,241,0.3)] transition-all transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3 mt-2"
          >
            {loading ? (
              <span className="flex items-center gap-2">
                <svg className="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Verifying...
              </span>
            ) : (
              'Activate Tenant'
            )}
          </button>
        </form>
      </div>
    </div>
  );
}
