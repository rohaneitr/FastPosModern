'use client';

import React, { useState, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import api from '@/lib/api';

function AcceptInviteForm() {
  const searchParams = useSearchParams();
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    password: '',
  });
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  
  // Extract query parameters
  const email = searchParams.get('email') || '';
  const role = searchParams.get('role') || '';

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    
    try {
      // Reconstruct the original query parameters to send to the backend
      // so it can validate the cryptographic signature correctly
      const query = new URLSearchParams(searchParams.toString());
      
      await api.post(`/register/accept-invite?${query.toString()}`, form);
      setSuccess(true);
      
      // Redirect to login after a brief delay
      setTimeout(() => {
         window.location.href = '/login';
      }, 2000);
      
    } catch (err: any) {
      if (err.response?.status === 422) {
        const messages = Object.values(err.response.data.errors || {}).flat().join(' ');
        setError(messages || err.response.data.message || 'Validation failed');
      } else {
        setError(err.response?.data?.message || 'Failed to accept invitation. The link may be invalid or expired.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="w-full max-w-[450px] mx-4 relative z-10 py-12">
      <div className="text-center mb-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
        <div className="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-xl bg-gradient-to-br from-primary to-black">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5c-1.1 0-2 .9-2 2v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
        </div>
        <h1 className="text-3xl font-extrabold text-foreground tracking-tight">Accept Invitation</h1>
        <p className="text-text-muted text-sm mt-1.5">You've been invited to join as <span className="font-semibold text-primary">{role || 'Staff'}</span></p>
      </div>

      <div className="glass-card p-8 rounded-3xl shadow-2xl border border-border/50 backdrop-blur-xl animate-in zoom-in-95 duration-500">
        {error && (
           <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-xl mb-6">
             {error}
           </div>
        )}

        {success ? (
          <div className="text-center animate-in zoom-in duration-500">
            <div className="w-16 h-16 bg-success/20 text-success rounded-full flex items-center justify-center mx-auto mb-4">
              <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" /></svg>
            </div>
            <h2 className="text-2xl font-bold text-foreground mb-2">Account Created!</h2>
            <p className="text-text-muted text-sm mb-4">
              Your account has been successfully set up. Redirecting to login...
            </p>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="flex flex-col gap-5">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-text-muted">Email Address</label>
              <input disabled value={email} className="w-full bg-surface border border-border rounded-xl px-4 py-3 text-text-muted text-sm outline-none cursor-not-allowed" />
            </div>
            
            <div className="grid grid-cols-2 gap-5">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">First Name</label>
                <input required value={form.first_name} onChange={e => setForm({...form, first_name: e.target.value})} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary/50" placeholder="Jane" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Last Name</label>
                <input value={form.last_name} onChange={e => setForm({...form, last_name: e.target.value})} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary/50" placeholder="Doe" />
              </div>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-text-muted">Create Password</label>
              <input required type="password" value={form.password} onChange={e => setForm({...form, password: e.target.value})} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary/50" placeholder="Minimum 8 characters" />
            </div>

            <button type="submit" disabled={loading || !email} className="w-full mt-2 bg-primary hover:bg-primary/90 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg text-sm flex justify-center items-center gap-2 transform active:scale-95 disabled:opacity-50">
              {loading ? 'Setting up account...' : 'Complete Setup'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}

export default function AcceptInvitePage() {
  return (
    <div className="min-h-screen bg-background flex items-center justify-center relative overflow-hidden">
      <div className="absolute top-[-30%] left-[-15%] w-[60%] h-[60%] rounded-full blur-[180px] pointer-events-none transition-colors duration-1000 bg-primary/10" />
      <div className="absolute bottom-[-25%] right-[-10%] w-[50%] h-[50%] rounded-full blur-[160px] pointer-events-none transition-colors duration-1000 bg-primary/20" />
      <Suspense fallback={<div className="text-white z-10">Loading Invitation...</div>}>
        <AcceptInviteForm />
      </Suspense>
    </div>
  );
}
