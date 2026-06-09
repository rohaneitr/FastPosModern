'use client';

import React, { useState, useEffect } from 'react';
import { useRouter, useParams } from 'next/navigation';
import api from '@/lib/api';

export default function ForgotPasswordPage() {
  const router = useRouter();
  const params = useParams();
  const domain = params.domain as string;

  const [step, setStep] = useState(1);
  const [email, setEmail] = useState('');
  const [otp, setOtp] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [tempToken, setTempToken] = useState('');
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  
  const [tenantBranding, setTenantBranding] = useState<{name: string, logo: string | null, color: string | null} | null>(null);

  useEffect(() => {
    if (domain) {
      api.get(`/tenant/resolve/${domain}`)
        .then(res => {
           const t = res.data.tenant;
           setTenantBranding({
             name: t.name,
             logo: t.branding?.logo_url || null,
             color: t.branding?.primary_color || null
           });
        })
        .catch(err => {
           console.error("Failed to load tenant branding", err);
        });
    }
  }, [domain]);

  const handleSendOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim()) return setError('Please enter your email.');
    setLoading(true); setError(''); setMessage('');
    try {
      const res = await api.post('/password/forgot', { email: email.trim() });
      setMessage(res.data.message || 'OTP sent successfully.');
      setStep(2);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to send reset link.');
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!otp.trim()) return setError('Please enter the OTP.');
    setLoading(true); setError(''); setMessage('');
    try {
      const res = await api.post('/password/verify-otp', { email: email.trim(), otp: otp.trim() });
      setTempToken(res.data.temp_token);
      setMessage(res.data.message || 'OTP verified.');
      setStep(3);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Invalid OTP.');
    } finally {
      setLoading(false);
    }
  };

  const handleResetPassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!password.trim() || password !== confirmPassword) {
      return setError('Passwords do not match or are empty.');
    }
    setLoading(true); setError(''); setMessage('');
    try {
      const res = await api.post('/password/reset', { 
        email: email.trim(), 
        token: tempToken, 
        password, 
        password_confirmation: confirmPassword 
      });
      setMessage(res.data.message || 'Password reset successfully.');
      setTimeout(() => {
        router.push(`/${domain}/login`);
      }, 2000);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to reset password.');
    } finally {
      setLoading(false);
    }
  };

  const primaryColor = tenantBranding?.color || '#6366f1';

  return (
    <div className="min-h-screen bg-background flex items-center justify-center relative overflow-hidden" style={{ '--tenant-primary': primaryColor } as React.CSSProperties}>
      <div 
        className="absolute top-[-30%] left-[-15%] w-[60%] h-[60%] rounded-full blur-[180px] pointer-events-none transition-colors duration-1000" 
        style={{ backgroundColor: `${primaryColor}15` }}
      />
      <div 
        className="absolute bottom-[-25%] right-[-10%] w-[50%] h-[50%] rounded-full blur-[160px] pointer-events-none transition-colors duration-1000" 
        style={{ backgroundColor: `${primaryColor}20` }}
      />

      <div className="w-full max-w-[420px] mx-4 relative z-10">
        <div className="text-center mb-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
          {tenantBranding?.logo ? (
            <img src={tenantBranding.logo} alt={tenantBranding.name} className="h-16 mx-auto object-contain mb-4" />
          ) : (
            <div className="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-xl" style={{ background: `linear-gradient(135deg, ${primaryColor}, #000000)` }}>
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          )}
          <h1 className="text-3xl font-extrabold text-foreground tracking-tight">Forgot Password</h1>
          <p className="text-text-muted text-sm mt-1.5">Reset your access securely</p>
        </div>

        <div className="glass-card p-7 rounded-2xl shadow-2xl border border-border/50 backdrop-blur-xl animate-in zoom-in-95 duration-500">
          {error && (
             <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-xl mb-5 flex items-start gap-3"><span>{error}</span></div>
          )}
          {message && (
             <div className="bg-success/10 border border-success/30 text-success text-sm px-4 py-3 rounded-xl mb-5 flex items-start gap-3"><span>{message}</span></div>
          )}

          {step === 1 && (
            <form onSubmit={handleSendOtp} className="flex flex-col gap-5">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Email Address</label>
                <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:ring-1" style={{ borderColor: 'var(--border)', outlineColor: primaryColor }} disabled={loading} placeholder="name@example.com" />
              </div>
              <button type="submit" disabled={loading} className="w-full mt-2 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg text-sm flex justify-center items-center gap-2 transform active:scale-95" style={{ backgroundColor: primaryColor, boxShadow: `0 4px 14px 0 ${primaryColor}40` }}>
                {loading ? 'Sending...' : 'Send OTP'}
              </button>
            </form>
          )}

          {step === 2 && (
            <form onSubmit={handleVerifyOtp} className="flex flex-col gap-5">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">6-Digit OTP</label>
                <p className="text-xs text-text-muted mb-1">We sent a code to your email.</p>
                <input type="text" required value={otp} onChange={(e) => setOtp(e.target.value)} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:ring-1 text-center tracking-[0.5em] font-mono text-lg" style={{ borderColor: 'var(--border)', outlineColor: primaryColor }} disabled={loading} maxLength={6} placeholder="000000" />
              </div>
              <button type="submit" disabled={loading} className="w-full mt-2 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg text-sm flex justify-center items-center gap-2 transform active:scale-95" style={{ backgroundColor: primaryColor, boxShadow: `0 4px 14px 0 ${primaryColor}40` }}>
                {loading ? 'Verifying...' : 'Verify OTP'}
              </button>
            </form>
          )}

          {step === 3 && (
            <form onSubmit={handleResetPassword} className="flex flex-col gap-5">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">New Password</label>
                <input type="password" required value={password} onChange={(e) => setPassword(e.target.value)} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:ring-1" style={{ borderColor: 'var(--border)', outlineColor: primaryColor }} disabled={loading} />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Confirm Password</label>
                <input type="password" required value={confirmPassword} onChange={(e) => setConfirmPassword(e.target.value)} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:ring-1" style={{ borderColor: 'var(--border)', outlineColor: primaryColor }} disabled={loading} />
              </div>
              <button type="submit" disabled={loading} className="w-full mt-2 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg text-sm flex justify-center items-center gap-2 transform active:scale-95" style={{ backgroundColor: primaryColor, boxShadow: `0 4px 14px 0 ${primaryColor}40` }}>
                {loading ? 'Resetting...' : 'Reset Password'}
              </button>
            </form>
          )}

          <div className="mt-6 text-center">
             <button type="button" onClick={() => router.push(`/${domain}/login`)} className="text-sm text-text-muted hover:text-foreground transition-colors">
               Back to Login
             </button>
          </div>
        </div>
      </div>
    </div>
  );
}
