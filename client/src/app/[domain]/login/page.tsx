'use client';

import React, { useState, useEffect } from 'react';
import { useRouter, useParams } from 'next/navigation';
import api from '@/lib/api';
import { useTranslation, SUPPORTED_LANGUAGES, type LanguageCode } from '@/lib/i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher';
import { useRateLimitStore } from '@/store/useRateLimitStore';

export default function TenantLoginPage() {
  const router = useRouter();
  const params = useParams();
  const domain = params.domain as string;

  const { t, locale, setLocale } = useTranslation();
  const { isRateLimited, retryAfterSeconds } = useRateLimitStore();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [requires2FA, setRequires2FA] = useState(false);
  const [twoFactorCode, setTwoFactorCode] = useState('');
  
  // Tenant Branding State
  const [tenantBranding, setTenantBranding] = useState<{name: string, logo: string | null, color: string | null} | null>(null);

  useEffect(() => {
    const user = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');
    if (user) {
      try {
        const parsed = JSON.parse(user);
        const role = parsed?.roles?.[0]?.name;
        if (role === 'BusinessAdmin') {
          router.replace('/business');
        } else {
          router.replace('/user/pos');
        }
      } catch {
        router.replace('/business');
      }
    }

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
           setError(`Could not verify tenant domain '${domain}'. Proceeding with default branding.`);
        });
    }
  }, [router, domain]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!requires2FA && (!email.trim() || !password.trim())) {
      setError(t('auth.enterBoth'));
      return;
    }
    if (requires2FA && !twoFactorCode.trim()) {
      setError('Please enter your 2FA code.');
      return;
    }
    setLoading(true);
    setError('');

    try {
      const payload: any = {
        username: email.trim(),
        password,
        domain: domain,
        subdomain: domain,
        remember_me: rememberMe
      };

      if (requires2FA) {
        payload.two_factor_code = twoFactorCode.trim();
      }

      // Establish CSRF handshake (must hit the base URL, not /api/v1)
      const baseUrl = api.defaults.baseURL?.replace('/api/v1', '') || 'http://localhost:8002';
      await api.get(`${baseUrl}/sanctum/csrf-cookie`);

      const response = await api.post('/login', payload);

      const userData = response.data.user;

      if (!userData) {
        setError(t('auth.unexpectedResponse'));
        return;
      }

      localStorage.removeItem('fastpos_user');
      sessionStorage.removeItem('fastpos_user');

      if (rememberMe) {
        localStorage.setItem('fastpos_user', JSON.stringify(userData));
      } else {
        sessionStorage.setItem('fastpos_user', JSON.stringify(userData));
      }

      // Edge Security: Set role cookie so Next.js Middleware can enforce RBAC
      const roleName = userData?.roles?.[0]?.name;
      if (roleName) {
        document.cookie = `fastpos_user_role=${roleName}; path=/; max-age=86400; SameSite=Lax`;
      }

      if (userData.language && userData.language !== locale) {
        setLocale(userData.language as LanguageCode);
      }

      if (['BusinessAdmin', 'Admin', 'Manager'].includes(roleName)) {
        router.push('/business');
      } else {
        router.push('/user/pos');
      }
    } catch (err: any) {
      console.error("CRITICAL_LOGIN_FAIL:", err);
      if (err.response?.status === 428 && err.response?.data?.requires_2fa) {
        setRequires2FA(true);
      } else if (err.response?.status === 422) {
        setError(err.response?.data?.message || t('auth.invalidCredentials'));
      } else if (err.response?.status === 401) {
        setError(t('auth.invalidCredentials'));
      } else if (err.code === 'ERR_NETWORK') {
        setError(t('auth.networkError'));
      } else {
        setError(err.response?.data?.message || t('auth.invalidCredentials'));
      }
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

      <div className="absolute top-6 right-6 z-20">
        <LanguageSwitcher />
      </div>

      <div className="w-full max-w-[420px] mx-4 relative z-10">
        <div className="text-center mb-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
          {tenantBranding?.logo ? (
            <img src={tenantBranding.logo} alt={tenantBranding.name} className="h-16 mx-auto object-contain mb-4" />
          ) : (
            <div className="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-xl" style={{ background: `linear-gradient(135deg, ${primaryColor}, #000000)` }}>
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
          )}
          <h1 className="text-3xl font-extrabold text-foreground tracking-tight">{tenantBranding?.name || 'Loading Workspace...'}</h1>
          <p className="text-text-muted text-sm mt-1.5">{t('auth.signInToAccount')}</p>
        </div>

        <div className="glass-card p-7 rounded-2xl shadow-2xl border border-border/50 backdrop-blur-xl animate-in zoom-in-95 duration-500">
          {error && (
             <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-xl mb-5 flex items-start gap-3"><span>{error}</span></div>
          )}
          <form onSubmit={handleLogin} className="flex flex-col gap-5">
            {!requires2FA ? (
              <>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-text-muted">{t('auth.emailAddress')}</label>
                  <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:ring-1" style={{ borderColor: 'var(--border)', outlineColor: primaryColor }} disabled={loading} />
                </div>
                <div className="flex flex-col gap-1.5">
                  <div className="flex justify-between items-center">
                    <label className="text-sm font-medium text-text-muted">{t('auth.password')}</label>
                    <button type="button" onClick={() => router.push(`/${domain}/forgot-password`)} className="text-xs font-semibold hover:underline" style={{ color: primaryColor }}>
                      Forgot Password?
                    </button>
                  </div>
                  <div className="relative">
                    <input type={showPassword ? 'text' : 'password'} required value={password} onChange={(e) => setPassword(e.target.value)} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all pr-12 focus:ring-1" style={{ borderColor: 'var(--border)', outlineColor: primaryColor }} disabled={loading} />
                    <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-4 top-1/2 -translate-y-1/2 text-text-muted hover:text-foreground">
                      {showPassword ? "Hide" : "Show"}
                    </button>
                  </div>
                </div>
                <div className="flex items-center gap-2 mt-1">
                  <input
                    type="checkbox"
                    id="rememberMe"
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                    className="w-4 h-4 rounded border-border text-primary focus:ring-primary"
                  />
                  <label htmlFor="rememberMe" className="text-sm text-text-muted select-none cursor-pointer">
                    Remember Me
                  </label>
                </div>
              </>
            ) : (
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Two-Factor Authentication</label>
                <p className="text-xs text-text-muted mb-2">Please enter the 6-digit code from your authenticator app.</p>
                <input type="text" required value={twoFactorCode} onChange={(e) => setTwoFactorCode(e.target.value)} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-foreground text-sm outline-none transition-all focus:ring-1 text-center tracking-[0.5em] font-mono text-lg" style={{ borderColor: 'var(--border)', outlineColor: primaryColor }} disabled={loading} maxLength={10} placeholder="000000" />
              </div>
            )}
            <button type="submit" disabled={loading || isRateLimited} className="w-full mt-2 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg text-sm flex justify-center items-center gap-2 transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed" style={{ backgroundColor: primaryColor, boxShadow: `0 4px 14px 0 ${primaryColor}40` }}>
              {loading ? t('common.loading') : (isRateLimited ? `Please wait ${retryAfterSeconds}s` : (requires2FA ? 'Verify 2FA' : t('auth.signIn')))}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
