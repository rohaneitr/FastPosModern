'use client';

import React, { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import api from '@/lib/api';

// ─── Password strength checker ────────────────────────────────────────────────

function getStrength(pw: string): { score: number; label: string; color: string } {
  let score = 0;
  if (pw.length >= 8)  score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;

  if (score <= 1) return { score, label: 'Weak',   color: 'bg-red-500'    };
  if (score <= 2) return { score, label: 'Fair',    color: 'bg-amber-500'  };
  if (score <= 3) return { score, label: 'Good',    color: 'bg-yellow-400' };
  if (score <= 4) return { score, label: 'Strong',  color: 'bg-emerald-400'};
  return             { score, label: 'Very Strong', color: 'bg-emerald-500' };
}

// ─── Inner component (uses useSearchParams, must be wrapped in Suspense) ──────

function ResetPasswordForm() {
  const router       = useRouter();
  const searchParams = useSearchParams();

  const [token,           setToken]           = useState('');
  const [email,           setEmail]           = useState('');
  const [newPassword,     setNewPassword]     = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showNew,         setShowNew]         = useState(false);
  const [showConfirm,     setShowConfirm]     = useState(false);
  const [loading,         setLoading]         = useState(false);
  const [error,           setError]           = useState('');
  const [success,         setSuccess]         = useState(false);

  // Read token + email from URL query params (delivered via email link)
  useEffect(() => {
    const t = searchParams.get('token');
    const e = searchParams.get('email');
    if (t) setToken(t);
    if (e) setEmail(e);
  }, [searchParams]);

  const strength = getStrength(newPassword);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!token || !email) {
      setError('Invalid reset link. Please request a new password reset email.');
      return;
    }
    if (newPassword !== confirmPassword) {
      setError('Passwords do not match.');
      return;
    }
    if (newPassword.length < 8) {
      setError('Password must be at least 8 characters.');
      return;
    }

    setLoading(true);
    try {
      await api.post('/reset-password', {
        email,
        token,
        password:              newPassword,
        password_confirmation: confirmPassword,
      });
      setSuccess(true);
    } catch (err: any) {
      // Generic message — don't expose internal details
      setError(
        err?.response?.data?.message ||
        'Failed to reset password. The link may have expired — please request a new one.'
      );
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div className="flex flex-col items-center gap-5 text-center py-4 animate-in zoom-in-95 duration-300">
        <div className="w-16 h-16 bg-emerald-500/10 rounded-full flex items-center justify-center text-emerald-400 text-3xl">
          ✅
        </div>
        <div>
          <h2 className="text-xl font-bold text-white mb-1">Password Updated</h2>
          <p className="text-text-muted text-sm">Your password has been reset. Please sign in with your new credentials.</p>
        </div>
        <button
          onClick={() => router.push('/login')}
          className="w-full bg-gradient-to-r from-primary to-indigo-600 hover:from-primary-hover hover:to-indigo-700 text-white font-semibold py-3 rounded-xl transition-all text-sm"
        >
          Go to Sign In
        </button>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      <div className="text-center mb-2">
        <div className="w-12 h-12 bg-gradient-to-br from-primary to-indigo-600 rounded-2xl mx-auto flex items-center justify-center mb-4 shadow-xl shadow-primary/25">
          🔐
        </div>
        <h1 className="text-2xl font-bold text-foreground">Set New Password</h1>
        <p className="text-text-muted text-sm mt-1.5">
          {email ? <>Resetting for <span className="text-white font-medium">{email}</span></> : 'Create a strong new password'}
        </p>
      </div>

      {error && (
        <div className="bg-danger/10 border border-danger/30 text-danger text-sm px-4 py-3 rounded-xl flex items-start gap-3">
          <span className="shrink-0 mt-0.5">⚠️</span>
          <span>{error}</span>
        </div>
      )}

      {/* New password */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="new-password" className="text-sm font-medium text-text-muted">New Password</label>
        <div className="relative">
          <input
            id="new-password"
            type={showNew ? 'text' : 'password'}
            required
            value={newPassword}
            onChange={e => setNewPassword(e.target.value)}
            placeholder="Min. 8 chars, mixed case, numbers, symbols"
            autoComplete="new-password"
            disabled={loading}
            className="w-full bg-background/60 border border-border rounded-xl pl-4 pr-12 py-3 text-foreground text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary/50 transition-all placeholder:text-text-muted/50"
          />
          <button
            type="button"
            onClick={() => setShowNew(v => !v)}
            tabIndex={-1}
            className="absolute right-3.5 top-1/2 -translate-y-1/2 text-text-muted hover:text-foreground transition-colors"
          >
            {showNew ? '🙈' : '👁️'}
          </button>
        </div>

        {/* Strength meter */}
        {newPassword.length > 0 && (
          <div className="mt-1 space-y-1 animate-in fade-in duration-200">
            <div className="flex gap-1 h-1.5">
              {[1,2,3,4,5].map(i => (
                <div
                  key={i}
                  className={`flex-1 rounded-full transition-all duration-300 ${
                    i <= strength.score ? strength.color : 'bg-border'
                  }`}
                />
              ))}
            </div>
            <p className={`text-xs font-medium ${
              strength.score <= 1 ? 'text-red-400' :
              strength.score <= 2 ? 'text-amber-400' :
              strength.score <= 3 ? 'text-yellow-400' : 'text-emerald-400'
            }`}>{strength.label}</p>
          </div>
        )}
      </div>

      {/* Confirm password */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor="confirm-password" className="text-sm font-medium text-text-muted">Confirm Password</label>
        <div className="relative">
          <input
            id="confirm-password"
            type={showConfirm ? 'text' : 'password'}
            required
            value={confirmPassword}
            onChange={e => setConfirmPassword(e.target.value)}
            placeholder="Repeat your new password"
            autoComplete="new-password"
            disabled={loading}
            className={`w-full bg-background/60 border rounded-xl pl-4 pr-12 py-3 text-foreground text-sm outline-none focus:ring-1 transition-all placeholder:text-text-muted/50 ${
              confirmPassword && confirmPassword !== newPassword
                ? 'border-red-500/60 focus:border-red-500 focus:ring-red-500/30'
                : 'border-border focus:border-primary focus:ring-primary/50'
            }`}
          />
          <button
            type="button"
            onClick={() => setShowConfirm(v => !v)}
            tabIndex={-1}
            className="absolute right-3.5 top-1/2 -translate-y-1/2 text-text-muted hover:text-foreground transition-colors"
          >
            {showConfirm ? '🙈' : '👁️'}
          </button>
        </div>
        {confirmPassword && confirmPassword !== newPassword && (
          <p className="text-xs text-red-400 animate-in fade-in duration-150">Passwords do not match</p>
        )}
      </div>

      {/* Requirements checklist */}
      <ul className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1 text-xs">
        {[
          { ok: newPassword.length >= 8,               label: '8+ characters'    },
          { ok: /[A-Z]/.test(newPassword),             label: 'Uppercase letter'  },
          { ok: /[a-z]/.test(newPassword),             label: 'Lowercase letter'  },
          { ok: /[0-9]/.test(newPassword),             label: 'Number'            },
          { ok: /[^A-Za-z0-9]/.test(newPassword),     label: 'Symbol (!@#...)'   },
          { ok: newPassword === confirmPassword && newPassword.length > 0, label: 'Passwords match' },
        ].map(req => (
          <li key={req.label} className={`flex items-center gap-1.5 transition-colors ${req.ok ? 'text-emerald-400' : 'text-text-muted/60'}`}>
            <span>{req.ok ? '✓' : '○'}</span> {req.label}
          </li>
        ))}
      </ul>

      <button
        type="submit"
        disabled={loading || strength.score < 3 || newPassword !== confirmPassword}
        className="w-full mt-1 bg-gradient-to-r from-primary to-indigo-600 hover:from-primary-hover hover:to-indigo-700 text-white font-semibold py-3 rounded-xl transition-all shadow-lg text-sm flex justify-center items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {loading ? (
          <><span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" /> Resetting…</>
        ) : 'Reset Password'}
      </button>

      <button
        type="button"
        onClick={() => router.push('/login')}
        className="text-xs text-text-muted hover:text-foreground text-center transition-colors"
      >
        ← Back to Sign In
      </button>
    </form>
  );
}

// ─── Page export (Suspense boundary required for useSearchParams) ─────────────

export default function ResetPasswordPage() {
  return (
    <div className="min-h-screen bg-background flex items-center justify-center relative overflow-hidden">
      <div className="absolute top-[-30%] left-[-15%] w-[60%] h-[60%] rounded-full bg-primary/10 blur-[180px] pointer-events-none" />
      <div className="absolute bottom-[-25%] right-[-10%] w-[50%] h-[50%] rounded-full bg-indigo-500/8 blur-[160px] pointer-events-none" />
      <div className="w-full max-w-[420px] mx-4 relative z-10">
        <div className="glass-card p-7 rounded-2xl shadow-2xl border border-white/[0.06]">
          <Suspense fallback={
            <div className="flex items-center justify-center py-12 gap-3 text-text-muted">
              <span className="w-5 h-5 border-2 border-primary/30 border-t-primary rounded-full animate-spin" />
              Loading…
            </div>
          }>
            <ResetPasswordForm />
          </Suspense>
        </div>
        <p className="text-center text-xs text-text-muted/60 mt-6">
          © {new Date().getFullYear()} FastPOS. All rights reserved.
        </p>
      </div>
    </div>
  );
}
