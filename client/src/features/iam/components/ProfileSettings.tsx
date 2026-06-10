'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useTranslation } from '@/lib/i18n';

interface ProfileSettingsProps {
  role: 'SuperAdmin' | 'BusinessAdmin' | 'User';
}

export default function ProfileSettings({ role }: ProfileSettingsProps) {
  const { t } = useTranslation();
  const [activeTab, setActiveTab] = useState('personal');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  
  const [profile, setProfile] = useState<any>(null);
  const [activities, setActivities] = useState<any[]>([]);

  // Form states
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [address, setAddress] = useState('');
  
  // Security states
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  
  // 2FA states
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(false);
  const [qrCodeSvg, setQrCodeSvg] = useState('');
  const [secret, setSecret] = useState('');
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [otpCode, setOtpCode] = useState('');
  const [twoFactorLoading, setTwoFactorLoading] = useState(false);
  
  const [message, setMessage] = useState<{type: 'success'|'error', text: string} | null>(null);

  useEffect(() => {
    fetchProfile();
    fetchActivities();
  }, []);

  const fetchProfile = async () => {
    try {
      const res = await api.get('/profile');
      setProfile(res.data);
      setFirstName(res.data.first_name || '');
      setLastName(res.data.last_name || '');
      setEmail(res.data.email || '');
      setPhone(res.data.phone || '');
      setAddress(res.data.address || '');
      setTwoFactorEnabled(res.data.two_factor_enabled || false);
      setLoading(false);
    } catch (err) {

      setLoading(false);
    }
  };

  const fetchActivities = async () => {
    try {
      const res = await api.get('/profile/activities');
      setActivities(res.data);
    } catch (err) {

    }
  };

  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setMessage(null);
    try {
      const res = await api.put('/profile', {
        first_name: firstName,
        last_name: lastName,
        email,
        phone,
        address
      });
      setMessage({ type: 'success', text: 'Profile updated successfully' });
      // update local storage user cache if needed
      const storedUser = JSON.parse(localStorage.getItem('fastpos_user') || '{}');
      localStorage.setItem('fastpos_user', JSON.stringify({...storedUser, name: `${firstName} ${lastName}`, email}));
    } catch (err: any) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Failed to update profile' });
    } finally {
      setSaving(false);
    }
  };

  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setMessage(null);
    try {
      await api.post('/profile/password', {
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword
      });
      setMessage({ type: 'success', text: 'Password changed successfully' });
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (err: any) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Failed to change password' });
    } finally {
      setSaving(false);
    }
  };

  const handleEnable2FA = async () => {
    setTwoFactorLoading(true);
    setMessage(null);
    try {
      const res = await api.post('/profile/2fa/enable');
      setQrCodeSvg(res.data.qr_code_svg);
      setSecret(res.data.secret);
      setRecoveryCodes(res.data.recovery_codes);
    } catch (err: any) {
      setMessage({ type: 'error', text: 'Failed to initiate 2FA setup.' });
    } finally {
      setTwoFactorLoading(false);
    }
  };

  const handleConfirm2FA = async (e: React.FormEvent) => {
    e.preventDefault();
    setTwoFactorLoading(true);
    setMessage(null);
    try {
      await api.post('/profile/2fa/confirm', { code: otpCode });
      setMessage({ type: 'success', text: 'Two-Factor Authentication enabled successfully!' });
      setTwoFactorEnabled(true);
      setQrCodeSvg('');
    } catch (err: any) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Invalid OTP code.' });
    } finally {
      setTwoFactorLoading(false);
    }
  };

  const handleDisable2FA = async () => {
    if (!window.confirm('Are you sure you want to disable 2FA? You will need to provide your password.')) return;
    const pwd = window.prompt('Enter your current password to disable 2FA:');
    if (!pwd) return;
    
    setTwoFactorLoading(true);
    setMessage(null);
    try {
      await api.post('/profile/2fa/disable', { password: pwd });
      setMessage({ type: 'success', text: 'Two-Factor Authentication disabled.' });
      setTwoFactorEnabled(false);
    } catch (err: any) {
      setMessage({ type: 'error', text: err.response?.data?.message || 'Failed to disable 2FA. Incorrect password?' });
    } finally {
      setTwoFactorLoading(false);
    }
  };

  if (loading) return <div className="p-8 text-center text-text-muted">{t('common.loading')}</div>;

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500 max-w-5xl mx-auto w-full">
      <div className="flex items-center gap-4 border-b border-border pb-4">
        <div className="w-16 h-16 rounded-full bg-emerald-500/20 text-emerald-500 flex items-center justify-center font-bold text-2xl">
          {profile?.avatar ? <img src={profile.avatar} alt="Avatar" className="rounded-full w-full h-full object-cover"/> : (firstName?.charAt(0) || 'U')}
        </div>
        <div>
          <h1 className="text-2xl font-bold text-white">{firstName} {lastName}</h1>
          <p className="text-text-muted">{role} • {email}</p>
        </div>
      </div>

      <div className="flex gap-2 border-b border-border">
        {['personal', 'security', 'preferences', 'activity'].map(tab => (
          <button
            key={tab}
            onClick={() => { setActiveTab(tab); setMessage(null); }}
            className={`px-4 py-2 font-medium text-sm rounded-t-lg transition-colors ${
              activeTab === tab ? 'bg-surface/50 text-emerald-400 border-b-2 border-emerald-500' : 'text-text-muted hover:text-white hover:bg-surface/30'
            }`}
          >
            {tab.charAt(0).toUpperCase() + tab.slice(1)}
          </button>
        ))}
        {role === 'BusinessAdmin' && (
          <button onClick={() => window.location.href = '/business/settings'} className="px-4 py-2 font-medium text-sm text-text-muted hover:text-emerald-400">
            Business Settings ↗
          </button>
        )}
      </div>

      <div className="bg-surface/30 border border-border p-6 rounded-2xl">
        {message && (
          <div className={`p-4 mb-6 rounded-lg text-sm font-medium ${message.type === 'success' ? 'bg-success/10 text-success border border-success/20' : 'bg-danger/10 text-danger border border-danger/20'}`}>
            {message.text}
          </div>
        )}

        {activeTab === 'personal' && (
          <form onSubmit={handleUpdateProfile} className="flex flex-col gap-5">
            <h2 className="text-lg font-bold text-white mb-2">Personal Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">First Name</label>
                <input type="text" value={firstName} onChange={e => setFirstName(e.target.value)} required className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Last Name</label>
                <input type="text" value={lastName} onChange={e => setLastName(e.target.value)} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Email Address</label>
                <input type="email" value={email} onChange={e => setEmail(e.target.value)} required className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Phone Number</label>
                <input type="text" value={phone} onChange={e => setPhone(e.target.value)} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50" />
              </div>
              <div className="flex flex-col gap-1.5 md:col-span-2">
                <label className="text-sm font-medium text-text-muted">Address</label>
                <textarea value={address} onChange={e => setAddress(e.target.value)} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50 h-24 resize-none" />
              </div>
            </div>
            <div className="flex justify-end mt-4">
              <button type="submit" disabled={saving} className="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2.5 rounded-lg font-bold transition-colors disabled:opacity-50">
                {saving ? t('common.loading') : t('common.save')}
              </button>
            </div>
          </form>
        )}

        {activeTab === 'security' && (
          <div className="flex flex-col w-full">
            <form onSubmit={handleChangePassword} className="flex flex-col gap-5 max-w-md">
              <h2 className="text-lg font-bold text-white mb-2">Change Password</h2>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Current Password</label>
                <input type="password" value={currentPassword} onChange={e => setCurrentPassword(e.target.value)} required className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50" />
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">New Password</label>
                <input type="password" value={newPassword} onChange={e => setNewPassword(e.target.value)} required minLength={8} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50" />
                <p className="text-xs text-text-muted">Must be at least 8 characters with numbers and symbols.</p>
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-text-muted">Confirm New Password</label>
                <input type="password" value={confirmPassword} onChange={e => setConfirmPassword(e.target.value)} required minLength={8} className="bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-emerald-500/50" />
              </div>
              <div className="flex justify-end mt-4">
                <button type="submit" disabled={saving || !newPassword || newPassword !== confirmPassword} className="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2.5 rounded-lg font-bold transition-colors disabled:opacity-50">
                  {saving ? t('common.loading') : 'Update Password'}
                </button>
              </div>
            </form>

          <div className="mt-10 border-t border-border pt-8 max-w-2xl">
              <h2 className="text-lg font-bold text-white mb-2">Two-Factor Authentication (2FA)</h2>
              <p className="text-sm text-text-muted mb-6">Add an extra layer of security to your account by requiring a time-based one-time password (TOTP) from an authenticator app when logging in.</p>
              
              {twoFactorEnabled ? (
                <div className="p-5 border border-success/30 bg-success/10 rounded-xl flex items-center justify-between">
                  <div>
                    <h3 className="font-bold text-success flex items-center gap-2">
                      <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                      2FA is Currently Enabled
                    </h3>
                    <p className="text-sm text-text-muted mt-1">Your account is highly secure.</p>
                  </div>
                  <button onClick={handleDisable2FA} disabled={twoFactorLoading} className="px-4 py-2 bg-background border border-danger/50 text-danger rounded-lg font-bold hover:bg-danger/10 transition-colors">
                    Disable 2FA
                  </button>
                </div>
              ) : qrCodeSvg ? (
                <div className="p-6 border border-border bg-background rounded-xl">
                  <h3 className="font-bold text-white mb-4">Complete 2FA Setup</h3>
                  <div className="flex flex-col md:flex-row gap-8">
                    <div className="flex-shrink-0 bg-white p-4 rounded-lg w-48 h-48 flex items-center justify-center" dangerouslySetInnerHTML={{ __html: qrCodeSvg }} />
                    <div className="flex-1">
                      <p className="text-sm text-text-muted mb-4">1. Scan this QR code using an authenticator app like Google Authenticator, Authy, or Microsoft Authenticator.</p>
                      <p className="text-sm text-text-muted mb-4">2. Can't scan? Enter this secret code manually:<br/><strong className="text-white font-mono">{secret}</strong></p>
                      
                      <form onSubmit={handleConfirm2FA} className="flex gap-3">
                        <input type="text" value={otpCode} onChange={e => setOtpCode(e.target.value)} placeholder="6-digit code" required maxLength={6} className="bg-surface border border-border rounded-lg px-4 py-2 text-white outline-none focus:border-emerald-500/50 w-32 font-mono" />
                        <button type="submit" disabled={twoFactorLoading || otpCode.length < 6} className="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg font-bold transition-colors disabled:opacity-50">
                          Verify & Enable
                        </button>
                      </form>
                    </div>
                  </div>
                  
                  {recoveryCodes.length > 0 && (
                    <div className="mt-8 p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                      <h4 className="font-bold text-amber-500 mb-2">Recovery Codes</h4>
                      <p className="text-xs text-text-muted mb-3">Save these codes in a secure location. You can use them to access your account if you lose your authenticator device.</p>
                      <div className="grid grid-cols-2 gap-2 font-mono text-sm text-amber-100">
                        {recoveryCodes.map(code => <div key={code}>{code}</div>)}
                      </div>
                    </div>
                  )}
                </div>
              ) : (
                <button onClick={handleEnable2FA} disabled={twoFactorLoading} className="bg-background border border-emerald-500 text-emerald-500 hover:bg-emerald-500/10 px-6 py-2.5 rounded-lg font-bold transition-colors">
                  Set up Two-Factor Authentication
                </button>
              )}
            </div>
          </div>
        )}

        {activeTab === 'preferences' && (
          <div className="flex flex-col gap-5">
            <h2 className="text-lg font-bold text-white mb-2">Account Preferences</h2>
            <div className="p-4 bg-background border border-border rounded-lg">
              <p className="text-sm text-text-muted mb-2">To change your language or currency, use the global settings dropdown in the header or the Business Settings panel.</p>
              <p className="text-sm text-text-muted">More preferences will be available in future updates.</p>
            </div>
          </div>
        )}

        {activeTab === 'activity' && (
          <div className="flex flex-col gap-5">
            <h2 className="text-lg font-bold text-white mb-2">Recent Activity</h2>
            {activities.length === 0 ? (
              <p className="text-text-muted text-sm p-4 bg-background border border-border rounded-lg">No recent activity found.</p>
            ) : (
              <div className="overflow-x-auto border border-border rounded-lg bg-background">
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="border-b border-border bg-surface/30 text-text-muted text-xs uppercase tracking-wider">
                      <th className="px-4 py-3 font-medium">Action</th>
                      <th className="px-4 py-3 font-medium">IP Address</th>
                      <th className="px-4 py-3 font-medium">Date & Time</th>
                    </tr>
                  </thead>
                  <tbody className="text-sm">
                    {activities.map((act) => (
                      <tr key={act.id} className="border-b border-border/50 last:border-0 hover:bg-surface/30">
                        <td className="px-4 py-3 text-white font-medium">{act.action}</td>
                        <td className="px-4 py-3 text-text-muted font-mono text-xs">{act.ip_address || 'Unknown'}</td>
                        <td className="px-4 py-3 text-text-muted">{new Date(act.created_at).toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
