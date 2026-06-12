'use client';

import React, { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';
import { useTranslation, SUPPORTED_LANGUAGES, type LanguageCode } from '@/lib/i18n';
import { useRateLimitStore } from '@/store/useRateLimitStore';
import toast from 'react-hot-toast';

export default function LoginPage() {
  const router = useRouter();
  const { t, locale, setLocale } = useTranslation();
  const { isRateLimited, retryAfterSeconds } = useRateLimitStore();
  
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [requires2FA, setRequires2FA] = useState(false);
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [forgotMode, setForgotMode] = useState<'off'|'request'|'reset'>('off');
  const [resetEmail, setResetEmail] = useState('');
  const [resetToken, setResetToken] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [rememberMe, setRememberMe] = useState(false);

  useEffect(() => {
    const userStr = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');
    if (userStr) {
      try {
        const parsed = JSON.parse(userStr);
        const role = parsed?.roles?.[0]?.name;
        if (role === 'SuperAdmin') {
          router.replace('/superadmin');
        } else {
          // Stale tenant session on root domain. Clear it to prevent 404s.
          localStorage.removeItem('fastpos_user');
          sessionStorage.removeItem('fastpos_user');
        }
      } catch {
        localStorage.removeItem('fastpos_user');
        sessionStorage.removeItem('fastpos_user');
      }
    }
  }, [router]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password.trim()) {
      toast.error(t('auth.enterBoth'));
      return;
    }
    setLoading(true);
    setError('');

    try {
      const payload: any = {
        username: email.trim(),
        password,
        remember_me: rememberMe,
      };

      if (requires2FA) {
        payload.two_factor_code = twoFactorCode;
      }

      // 1. Establish CSRF handshake (must hit the base URL, not /api/v1)
      const baseUrl = api.defaults.baseURL?.replace('/api/v1', '') || 'http://localhost:8002';
      await api.get(`${baseUrl}/sanctum/csrf-cookie`);

      // 2. Dispatch login payload
      const response = await api.post('/login', payload);

      const userData = response.data.user;

      if (!userData) {
        toast.error(t('auth.unexpectedResponse'));
        return;
      }

      // Clear both storages first to prevent conflicts
      localStorage.removeItem('fastpos_user');
      sessionStorage.removeItem('fastpos_user');
      localStorage.removeItem('fastpos_token');
      sessionStorage.removeItem('fastpos_token');

      const storage = rememberMe ? localStorage : sessionStorage;
      storage.setItem('fastpos_user', JSON.stringify(userData));
      if (response.data.token) {
        storage.setItem('fastpos_token', response.data.token);
      }

      // Set user's language preference if available
      if (userData.language && userData.language !== locale) {
        setLocale(userData.language as LanguageCode);
      }

      const roleName = userData?.roles?.[0]?.name;
      if (roleName) {
        document.cookie = `fastpos_user_role=${roleName}; path=/; max-age=86400; SameSite=Lax`;
      }

      if (roleName === 'SuperAdmin') {
        toast.success('Logged in successfully');
        router.push('/superadmin');
      } else {
        // Reject non-SuperAdmin users on the Super Admin login portal
        localStorage.removeItem('fastpos_user');
        sessionStorage.removeItem('fastpos_user');
        toast.error('Unauthorized: This portal is strictly for Super Admin access.');
      }
    } catch (err: any) {
      if (err.response?.status === 428 && err.response?.data?.requires_2fa) {
        setRequires2FA(true);
        toast.success('Please enter your 2FA code');
        return;
      }

      if (err.response?.status === 422 || err.response?.status === 401) {
        const messages = err.response?.data?.errors;
        if (messages) {
          const firstKey = Object.keys(messages)[0];
          toast.error(messages[firstKey]?.[0] || t('auth.validationFailed'));
        } else {
          toast.error(err.response?.data?.message || t('auth.invalidCredentials'));
        }
      } else if (err.response?.status === 401) {
        toast.error(t('auth.invalidCredentials'));
      } else if (err.code === 'ERR_NETWORK') {
        toast.error(t('auth.networkError'));
      } else {
        toast.error(t('auth.invalidCredentials'));
      }
    } finally {
      setLoading(false);
    }
  };

  const handleForgotPassword = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await api.post('/forgot-password', { email: resetEmail });
      // Never read a token from the API response — it should only arrive via email.
      toast.success('If that email is registered, a reset link has been sent. Please check your inbox.');
      // Keep the form visible so the user sees the confirmation message.
    } catch (err: any) {
      // Show a generic message — don't confirm whether the email exists
      toast.success('If that email is registered, a reset link has been sent. Please check your inbox.');
    } finally { setLoading(false); }
  };

  const handleResetPassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (newPassword !== confirmPassword) { toast.error('Passwords do not match.'); return; }
    setLoading(true);
    try {
      await api.post('/reset-password', { email: resetEmail, token: resetToken, password: newPassword, password_confirmation: confirmPassword });
      toast.success('Password reset successfully! You can now log in.');
      setForgotMode('off');
      setResetEmail(''); setResetToken(''); setNewPassword(''); setConfirmPassword('');
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to reset password.');
    } finally { setLoading(false); }
  };

  return (
    <div className="min-h-screen bg-background flex items-center justify-center relative overflow-hidden">
      <div className="absolute top-[-30%] left-[-15%] w-[60%] h-[60%] rounded-full bg-primary/10 blur-[180px] pointer-events-none" />
      <div className="absolute bottom-[-25%] right-[-10%] w-[50%] h-[50%] rounded-full bg-indigo-500/8 blur-[160px] pointer-events-none" />

      {/* Language Switcher (top-right) */}
      <div className="absolute top-6 right-6 z-20">
        <select
          value={locale}
          onChange={(e) => setLocale(e.target.value as LanguageCode)}
          className="bg-surface/80 border border-border rounded-lg px-3 py-1.5 text-sm text-foreground outline-none focus:border-primary cursor-pointer backdrop-blur-md"
        >
          {SUPPORTED_LANGUAGES.map(lang => (
            <option key={lang.code} value={lang.code}>
              {lang.nativeName}
            </option>
          ))}
        </select>
      </div>

      <div className="w-full max-w-[420px] mx-4 relative z-10">
        <div className="text-center mb-8">
          <div className="w-14 h-14 bg-gradient-to-br from-primary to-indigo-600 rounded-2xl mx-auto flex items-center justify-center mb-5 shadow-xl shadow-primary/25">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-foreground tracking-tight">FastPOS</h1>
          <p className="text-text-muted text-sm mt-1.5">{t('auth.signInToAccount')}</p>
        </div>

        <div className="glass-card p-7 rounded-2xl shadow-2xl border border-white/[0.06]">

          <form onSubmit={handleLogin} className="flex flex-col gap-5" autoComplete="off">
            {!requires2FA ? (
              <>
                <div className="flex flex-col gap-1.5">
                  <label htmlFor="login-email" className="text-sm font-medium text-text-muted">{t('auth.emailAddress')}</label>
                  <div className="relative">
                    <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                      </svg>
                    </span>
                    <input id="login-email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)}
                      className="w-full bg-background/60 border border-border rounded-xl pl-11 pr-4 py-3 text-foreground text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary/50 transition-all placeholder:text-text-muted/50"
                      placeholder={t('auth.emailPlaceholder')} autoComplete="off" disabled={loading} />
                  </div>
                </div>

                <div className="flex flex-col gap-1.5">
                  <label htmlFor="login-password" className="text-sm font-medium text-text-muted">{t('auth.password')}</label>
                  <div className="relative">
                    <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                      </svg>
                    </span>
                    <input id="login-password" type={showPassword ? 'text' : 'password'} required value={password} onChange={(e) => setPassword(e.target.value)}
                      className="w-full bg-background/60 border border-border rounded-xl pl-11 pr-12 py-3 text-foreground text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary/50 transition-all placeholder:text-text-muted/50"
                      placeholder={t('auth.passwordPlaceholder')} autoComplete="off" disabled={loading} />
                    <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-3.5 top-1/2 -translate-y-1/2 text-text-muted hover:text-foreground transition-colors" tabIndex={-1}>
                      {showPassword ? (
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                      ) : (
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                      )}
                    </button>
                  </div>
                </div>

                <div className="flex items-center gap-2 mt-1">
                  <input
                    type="checkbox"
                    id="remember-me"
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                    className="w-4 h-4 rounded border-border text-primary focus:ring-primary/50"
                  />
                  <label htmlFor="remember-me" className="text-sm text-text-muted cursor-pointer select-none">
                    Remember me
                  </label>
                </div>
              </>
            ) : (
              <div className="flex flex-col gap-1.5 animate-in fade-in zoom-in-95 duration-300">
                <label htmlFor="login-2fa" className="text-sm font-medium text-text-muted">Authentication Code</label>
                <p className="text-xs text-text-muted mb-2">Enter the 6-digit code from your authenticator app.</p>
                <div className="relative">
                  <span className="absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                  </span>
                  <input id="login-2fa" type="text" required value={twoFactorCode} onChange={(e) => setTwoFactorCode(e.target.value)}
                    className="w-full bg-background/60 border border-border rounded-xl pl-11 pr-4 py-3 text-foreground text-sm font-mono tracking-widest outline-none focus:border-primary focus:ring-1 focus:ring-primary/50 transition-all"
                    placeholder="000000" autoComplete="off" disabled={loading} maxLength={10} autoFocus />
                </div>
                <button type="button" onClick={() => { setRequires2FA(false); setTwoFactorCode(''); }} className="text-xs text-primary hover:text-primary-hover text-left mt-2">
                  Back to login
                </button>
              </div>
            )}

            <button type="submit" disabled={loading || isRateLimited}
              className={`w-full mt-1 bg-gradient-to-r from-primary to-indigo-600 hover:from-primary-hover hover:to-indigo-700 text-white font-semibold py-3 rounded-xl transition-all shadow-lg text-sm flex justify-center items-center gap-2
                ${(loading || isRateLimited) ? 'opacity-70 cursor-not-allowed' : 'hover:shadow-primary/25 hover:shadow-xl active:scale-[0.98]'}`}>
              {loading ? (
                <><div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /><span>{requires2FA ? 'Verifying...' : t('auth.signingIn')}</span></>
              ) : (isRateLimited ? `Please wait ${retryAfterSeconds}s` : (requires2FA ? 'Verify Code' : t('auth.signIn')))}
            </button>

            {forgotMode === 'off' && !requires2FA && (
              <button type="button" onClick={() => { setForgotMode('request'); }} className="text-xs text-primary hover:text-primary-hover mt-2 w-full text-center">
                Forgot Password?
              </button>
            )}
          </form>

          {forgotMode === 'request' && (
            <form onSubmit={handleForgotPassword} className="flex flex-col gap-4 mt-5 pt-5 border-t border-border animate-in fade-in duration-300">
              <p className="text-sm text-text-muted">Enter your email address to receive a password reset token.</p>
              <input type="email" required value={resetEmail} onChange={e => setResetEmail(e.target.value)} placeholder="Email address" className="w-full bg-background/60 border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none focus:border-primary" />
              <div className="flex gap-2">
                <button type="submit" disabled={loading} className="flex-1 bg-primary hover:bg-primary-hover text-white py-2.5 rounded-xl text-sm font-semibold">{loading ? 'Sending...' : 'Send Reset Token'}</button>
                <button type="button" onClick={() => setForgotMode('off')} className="px-4 py-2.5 rounded-xl text-sm text-text-muted hover:text-foreground">Cancel</button>
              </div>
            </form>
          )}

          {forgotMode === 'reset' && (
            <form onSubmit={handleResetPassword} className="flex flex-col gap-4 mt-5 pt-5 border-t border-border animate-in fade-in duration-300">
              <p className="text-sm text-text-muted">Set your new password.</p>
              <input type="password" required value={newPassword} onChange={e => setNewPassword(e.target.value)} placeholder="New password" className="w-full bg-background/60 border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none focus:border-primary" />
              <input type="password" required value={confirmPassword} onChange={e => setConfirmPassword(e.target.value)} placeholder="Confirm new password" className="w-full bg-background/60 border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none focus:border-primary" />
              <div className="flex gap-2">
                <button type="submit" disabled={loading} className="flex-1 bg-primary hover:bg-primary-hover text-white py-2.5 rounded-xl text-sm font-semibold">{loading ? 'Resetting...' : 'Reset Password'}</button>
                <button type="button" onClick={() => { setForgotMode('off'); }} className="px-4 py-2.5 rounded-xl text-sm text-text-muted hover:text-foreground">Cancel</button>
              </div>
            </form>
          )}
        </div>

        <p className="text-center text-xs text-text-muted/60 mt-6">
          {t('common.copyright', { year: new Date().getFullYear().toString() })}
        </p>
      </div>
    </div>
  );
}
