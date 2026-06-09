'use client';

import React, { useState } from 'react';
import api from '@/lib/api';

export default function RegisterPage() {
  const [form, setForm] = useState({
    business_name: '',
    domain_prefix: '',
    admin_name: '',
    email: '',
    password: '',
  });
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [successData, setSuccessData] = useState<{domain_prefix: string} | null>(null);

  const handleDomainChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    // Alphanumeric only
    const val = e.target.value.replace(/[^a-z0-9-]/gi, '').toLowerCase();
    setForm({ ...form, domain_prefix: val });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    
    try {
      await api.post('/register/self', form);
      setSuccessData({ domain_prefix: form.domain_prefix });
    } catch (err: any) {
      if (err.response?.status === 422) {
        // format validation errors
        const messages = Object.values(err.response.data.errors || {}).flat().join(' ');
        setError(messages || err.response.data.message || 'Validation failed');
      } else {
        setError(err.response?.data?.message || 'Failed to register.');
      }
    } finally {
      setLoading(false);
    }
  };

  const visitLogin = () => {
    if (!successData) return;
    const host = window.location.host;
    let newHost = host;
    if (host.includes('localhost')) {
       newHost = `${successData.domain_prefix}.localhost:3000`;
    } else {
       const parts = host.split('.');
       if (parts.length > 2) {
          parts[0] = successData.domain_prefix;
          newHost = parts.join('.');
       } else {
          newHost = `${successData.domain_prefix}.${host}`;
       }
    }
    window.location.href = `${window.location.protocol}//${newHost}/login`;
  };

  return (
    <div className="min-h-screen bg-background flex items-center justify-center relative overflow-hidden">
      <div className="absolute top-[-30%] left-[-15%] w-[60%] h-[60%] rounded-full blur-[180px] pointer-events-none transition-colors duration-1000 bg-primary/10" />
      <div className="absolute bottom-[-25%] right-[-10%] w-[50%] h-[50%] rounded-full blur-[160px] pointer-events-none transition-colors duration-1000 bg-primary/20" />

      <div className="w-full max-w-[500px] mx-4 relative z-10 py-12">
        <div className="text-center mb-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
          <div className="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-xl bg-gradient-to-br from-primary to-black">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          </div>
          <h1 className="text-3xl font-extrabold text-foreground tracking-tight">Create your Workspace</h1>
          <p className="text-text-muted text-sm mt-1.5">Set up your business and start managing with FastPOS</p>
        </div>

        <div className="glass-card p-8 rounded-3xl shadow-2xl border border-border/50 backdrop-blur-xl animate-in zoom-in-95 duration-500">
          {error && (
             <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-xl mb-6">
               {error}
             </div>
          )}

          {successData ? (
            <div className="text-center animate-in zoom-in duration-500">
              <div className="w-16 h-16 bg-success/20 text-success rounded-full flex items-center justify-center mx-auto mb-4">
                <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" /></svg>
              </div>
              <h2 className="text-2xl font-bold text-foreground mb-2">Registration Successful!</h2>
              <p className="text-text-muted text-sm mb-8">
                Your workspace is ready. We've sent a verification email to your inbox.
              </p>
              <button 
                onClick={visitLogin}
                className="w-full bg-primary hover:bg-primary/90 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg text-sm"
              >
                Go to my Workspace Login
              </button>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="flex flex-col gap-5">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div className="flex flex-col gap-1.5 sm:col-span-2">
                  <label className="text-sm font-medium text-text-muted">Business Name</label>
                  <input required value={form.business_name} onChange={e => setForm({...form, business_name: e.target.value})} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary/50" placeholder="Acme Corp" />
                </div>
                
                <div className="flex flex-col gap-1.5 sm:col-span-2">
                  <label className="text-sm font-medium text-text-muted">Workspace URL prefix</label>
                  <div className="flex items-center">
                    <input required value={form.domain_prefix} onChange={handleDomainChange} className="w-full bg-background border border-border rounded-l-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary/50" placeholder="acme" />
                    <div className="bg-surface border border-l-0 border-border rounded-r-xl px-4 py-3 text-text-muted text-sm font-mono whitespace-nowrap">
                      .fastpos.com
                    </div>
                  </div>
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Admin Full Name</label>
                  <input required value={form.admin_name} onChange={e => setForm({...form, admin_name: e.target.value})} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary/50" placeholder="John Doe" />
                </div>

                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">Email Address</label>
                  <input required type="email" value={form.email} onChange={e => setForm({...form, email: e.target.value})} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary/50" placeholder="john@example.com" />
                </div>

                <div className="flex flex-col gap-1.5 sm:col-span-2">
                  <label className="text-sm font-medium text-text-muted">Password</label>
                  <input required type="password" value={form.password} onChange={e => setForm({...form, password: e.target.value})} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary/50" placeholder="Create a secure password" />
                </div>
              </div>

              <button type="submit" disabled={loading} className="w-full mt-2 bg-primary hover:bg-primary/90 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg text-sm flex justify-center items-center gap-2 transform active:scale-95 disabled:opacity-50">
                {loading ? 'Creating workspace...' : 'Register Business'}
              </button>
            </form>
          )}

          <div className="mt-6 text-center">
             <button type="button" onClick={() => window.location.href = '/login'} className="text-sm text-text-muted hover:text-foreground transition-colors">
               Already have an account? Sign In
             </button>
          </div>
        </div>
      </div>
    </div>
  );
}
