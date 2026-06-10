'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { usePosSounds } from '@/hooks/usePosSounds';
import toast from 'react-hot-toast';

export default function SuperAdminSettingsPage() {
  const { playTaskSuccess } = usePosSounds();
  const [activeTab, setActiveTab] = useState('branding');
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [testingSmtp, setTestingSmtp] = useState(false);
  const [smtpError, setSmtpError] = useState('');
  
  // Tab 1: Branding
  const [saasName, setSaasName] = useState('');
  const [saasLogoFile, setSaasLogoFile] = useState<File | null>(null);
  const [saasLogoPreview, setSaasLogoPreview] = useState<string>('');
  const [faviconFile, setFaviconFile] = useState<File | null>(null);
  const [faviconPreview, setFaviconPreview] = useState<string>('');

  // Tab 2: System
  const [systemPrefs, setSystemPrefs] = useState({
    timezone: 'UTC',
    default_currency_symbol: '$',
    smtp_sender_address: 'admin@fastpos.com',
    smtp_host: '',
    smtp_port: '',
    smtp_username: '',
    smtp_password: '',
    smtp_encryption: 'tls',
    enable_registration: false,
    maintenance_mode: false
  });

  // Tab 3: Profile
  const [profile, setProfile] = useState({
    name: '',
    email: '',
    password: '',
    two_factor_enabled: false
  });

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      const res = await api.get('/superadmin/settings');
      const data = res.data;
      if (data.saas_name) setSaasName(data.saas_name);
      if (data.saas_logo) setSaasLogoPreview(data.saas_logo);
      if (data.favicon) setFaviconPreview(data.favicon);
      
      setSystemPrefs({
        timezone: data.timezone || 'UTC',
        default_currency_symbol: data.default_currency_symbol || '$',
        smtp_sender_address: data.smtp_sender_address || 'admin@fastpos.com',
        smtp_host: data.smtp_host || '',
        smtp_port: data.smtp_port || '',
        smtp_username: data.smtp_username || '',
        smtp_password: data.smtp_password || '', // Backend masks this if set
        smtp_encryption: data.smtp_encryption || 'tls',
        enable_registration: data.enable_registration === '1' || data.enable_registration === true,
        maintenance_mode: data.maintenance_mode === '1' || data.maintenance_mode === true
      });
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to load settings');
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>, type: 'logo' | 'favicon') => {
    const file = e.target.files?.[0];
    if (file) {
      if (!file.type.startsWith('image/')) {
        toast.error('Please select an image file');
        return;
      }
      const url = URL.createObjectURL(file);
      if (type === 'logo') {
        setSaasLogoFile(file);
        setSaasLogoPreview(url);
      } else {
        setFaviconFile(file);
        setFaviconPreview(url);
      }
    }
  };

  const handleBrandingSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const formData = new FormData();
      if (saasName) formData.append('saas_name', saasName);
      if (saasLogoFile) formData.append('saas_logo', saasLogoFile);
      if (faviconFile) formData.append('favicon', faviconFile);

      await api.post('/superadmin/settings', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      
      playTaskSuccess();
      toast.success('Global SaaS branding updated successfully!');
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to update global branding');
    } finally {
      setSubmitting(false);
    }
  };

  const handleInstantToggle = async (field: 'enable_registration' | 'maintenance_mode', value: boolean) => {
    const backup = { ...systemPrefs };
    setSystemPrefs(prev => ({ ...prev, [field]: value })); // Optimistic UI

    try {
      await api.post('/superadmin/settings', { [field]: value });
      toast.success(`${field.replace('_', ' ')} ${value ? 'enabled' : 'disabled'}`);
    } catch (err: any) {
      setSystemPrefs(backup); // Rollback
      toast.error(err.response?.data?.message || `Failed to toggle ${field}`);
    }
  };

  const handleSystemSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post('/superadmin/settings', systemPrefs);
      playTaskSuccess();
      toast.success('System & SMTP preferences updated successfully!');
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to update system preferences');
    } finally {
      setSubmitting(false);
    }
  };

  const handleTestSmtp = async () => {
    setTestingSmtp(true);
    setSmtpError('');
    try {
      await api.post('/superadmin/settings/test-smtp', {
        smtp_host: systemPrefs.smtp_host,
        smtp_port: systemPrefs.smtp_port,
        smtp_username: systemPrefs.smtp_username,
        smtp_password: systemPrefs.smtp_password,
        smtp_encryption: systemPrefs.smtp_encryption
      });
      playTaskSuccess();
      toast.success('SMTP Connection Successful!');
    } catch (err: any) {
      setSmtpError(err.response?.data?.message || 'Failed to connect to SMTP server.');
      toast.error('SMTP Connection Failed');
    } finally {
      setTestingSmtp(false);
    }
  };

  const handleProfileSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.put('/user/profile', {
        name: profile.name,
        email: profile.email,
        password: profile.password || undefined,
        two_factor_enabled: profile.two_factor_enabled
      });
      playTaskSuccess();
      toast.success('Admin profile updated successfully!');
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to update admin profile');
    } finally {
      setSubmitting(false);
    }
  };

  const tabs = [
    { id: 'branding', label: 'Global Branding', icon: '🎨' },
    { id: 'system', label: 'System Preferences', icon: '⚙️' },
    { id: 'profile', label: 'Admin Profile', icon: '🛡️' },
  ];

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12 w-full max-w-6xl mx-auto">
      <div>
        <h1 className="text-3xl font-bold text-white mb-2">Platform Settings Hub</h1>
        <p className="text-text-muted">Configure global SaaS identity, white-labeling, and core system preferences.</p>
      </div>

      <div className="flex flex-col md:flex-row gap-8">
        {/* Sidebar Tabs */}
        <div className="w-full md:w-64 flex flex-col gap-2 shrink-0">
          {tabs.map(tab => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`text-left px-4 py-3 rounded-xl font-medium transition-all flex items-center gap-3 ${
                activeTab === tab.id 
                  ? 'bg-primary text-white shadow-lg shadow-primary/20' 
                  : 'bg-surface/50 text-text-muted hover:bg-surface hover:text-white border border-border'
              }`}
            >
              <span>{tab.icon}</span> {tab.label}
            </button>
          ))}
        </div>

        {/* Tab Content */}
        <div className="flex-1 glass-card rounded-2xl p-6 border border-border min-h-[500px]">
          {activeTab === 'branding' && (
            <div className="animate-in slide-in-from-right-4 duration-300">
              <h2 className="text-xl font-bold text-white mb-6 border-b border-border pb-4">Global Branding</h2>
              <form onSubmit={handleBrandingSubmit} className="flex flex-col gap-8">
                <div className="flex flex-col gap-2">
                  <label className="text-sm font-medium text-text-muted">SaaS Platform Name</label>
                  <input value={saasName} onChange={e => setSaasName(e.target.value)} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" placeholder="FastPOS Enterprise" />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                  {/* SaaS Logo Dropzone */}
                  <div className="flex flex-col">
                    <h3 className="text-sm font-medium text-text-muted mb-2">Global Platform Logo</h3>
                    <label className="flex-1 min-h-[160px] border-2 border-dashed border-border hover:border-primary/50 bg-background rounded-xl flex flex-col items-center justify-center cursor-pointer transition-colors p-4 group relative">
                      <input type="file" className="hidden" accept="image/*" onChange={e => handleFileChange(e, 'logo')} />
                      {saasLogoPreview ? (
                        <img src={saasLogoPreview} alt="Logo Preview" className="max-w-full max-h-[120px] object-contain group-hover:opacity-50 transition-opacity" />
                      ) : (
                        <div className="text-center text-text-muted group-hover:text-primary transition-colors">
                          <svg className="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                          <span className="text-xs font-medium">Upload Global Logo</span>
                        </div>
                      )}
                    </label>
                  </div>

                  {/* Favicon Dropzone */}
                  <div className="flex flex-col">
                    <h3 className="text-sm font-medium text-text-muted mb-2">Platform Favicon</h3>
                    <label className="flex-1 min-h-[160px] border-2 border-dashed border-border hover:border-primary/50 bg-background rounded-xl flex flex-col items-center justify-center cursor-pointer transition-colors p-4 group relative">
                      <input type="file" className="hidden" accept="image/*,.ico" onChange={e => handleFileChange(e, 'favicon')} />
                      {faviconPreview ? (
                        <img src={faviconPreview} alt="Favicon Preview" className="max-w-full max-h-[120px] object-contain group-hover:opacity-50 transition-opacity" />
                      ) : (
                        <div className="text-center text-text-muted group-hover:text-primary transition-colors">
                          <svg className="w-8 h-8 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                          <span className="text-xs font-medium">Upload Favicon (.ico, .png)</span>
                        </div>
                      )}
                    </label>
                  </div>
                </div>

                <div className="flex justify-end pt-4">
                  <button type="submit" disabled={submitting || (!saasName && !saasLogoFile && !faviconFile)} className="bg-primary hover:brightness-110 text-white px-8 py-3 rounded-xl font-bold transition-all duration-150 active:scale-[0.97] shadow-lg disabled:opacity-50">
                    {submitting ? 'Saving...' : 'Save Global Branding'}
                  </button>
                </div>
              </form>
            </div>
          )}

          {activeTab === 'system' && (
            <div className="animate-in slide-in-from-right-4 duration-300">
              <h2 className="text-xl font-bold text-white mb-6 border-b border-border pb-4">System Preferences</h2>
              <form onSubmit={handleSystemSubmit} className="flex flex-col gap-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">System Timezone</label>
                    <select value={systemPrefs.timezone} onChange={e => setSystemPrefs({...systemPrefs, timezone: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors">
                      <option value="UTC">UTC (Global Default)</option>
                      <option value="America/New_York">America/New_York</option>
                      <option value="Europe/London">Europe/London</option>
                      <option value="Asia/Dhaka">Asia/Dhaka</option>
                    </select>
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">Default Currency Symbol</label>
                    <input value={systemPrefs.default_currency_symbol} onChange={e => setSystemPrefs({...systemPrefs, default_currency_symbol: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" />
                  </div>
                </div>
                <div className="flex flex-col gap-2">
                  <label className="text-sm font-medium text-text-muted">System SMTP Sender Address (Outbound Mails)</label>
                  <input type="email" value={systemPrefs.smtp_sender_address} onChange={e => setSystemPrefs({...systemPrefs, smtp_sender_address: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" />
                </div>
                
                {/* SMTP Configuration */}
                <div className="flex items-center justify-between mt-4 border-t border-border pt-4">
                  <h3 className="text-lg font-bold text-white">SMTP Configuration</h3>
                  <button type="button" onClick={handleTestSmtp} disabled={testingSmtp || !systemPrefs.smtp_host} className="bg-surface border border-border text-white hover:border-primary/50 px-4 py-2 rounded-lg text-sm font-bold transition-all disabled:opacity-50 flex items-center gap-2">
                    {testingSmtp ? <span className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"/> : '📡'}
                    {testingSmtp ? 'Testing...' : 'Test Connection'}
                  </button>
                </div>

                {smtpError && (
                  <div className="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl text-sm font-mono whitespace-pre-wrap">
                    <span className="font-bold text-rose-500 block mb-1">SMTP Connection Failed:</span>
                    {smtpError}
                  </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">SMTP Host</label>
                    <input type="text" value={systemPrefs.smtp_host} onChange={e => setSystemPrefs({...systemPrefs, smtp_host: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" placeholder="smtp.mailgun.org" />
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">SMTP Port</label>
                    <input type="number" value={systemPrefs.smtp_port} onChange={e => setSystemPrefs({...systemPrefs, smtp_port: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" placeholder="587" />
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">SMTP Username</label>
                    <input type="text" value={systemPrefs.smtp_username} onChange={e => setSystemPrefs({...systemPrefs, smtp_username: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" placeholder="postmaster@yourdomain.com" />
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">SMTP Password</label>
                    <input type="password" value={systemPrefs.smtp_password} onChange={e => setSystemPrefs({...systemPrefs, smtp_password: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" placeholder={systemPrefs.smtp_password === '********' ? '******** (Encrypted)' : 'Enter SMTP Password'} />
                  </div>
                  <div className="flex flex-col gap-2 md:col-span-2">
                    <label className="text-sm font-medium text-text-muted">Encryption</label>
                    <select value={systemPrefs.smtp_encryption} onChange={e => setSystemPrefs({...systemPrefs, smtp_encryption: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors">
                      <option value="tls">TLS</option>
                      <option value="ssl">SSL</option>
                      <option value="none">None</option>
                    </select>
                  </div>
                </div>

                <h3 className="text-lg font-bold text-white mt-8 border-t border-border pt-4">Global Feature Flags</h3>
                <div className="grid grid-cols-1 gap-4">
                  <div className="flex items-center justify-between p-4 bg-surface/50 border border-border rounded-xl">
                    <div>
                      <h4 className="font-bold text-white">Enable Public Registration</h4>
                      <p className="text-sm text-text-muted">Allow new users to sign up and create businesses.</p>
                    </div>
                    <label className="relative inline-flex items-center cursor-pointer">
                      <input type="checkbox" className="sr-only peer" checked={systemPrefs.enable_registration} onChange={e => handleInstantToggle('enable_registration', e.target.checked)} />
                      <div className="w-11 h-6 bg-surface peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-text-muted peer-checked:after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary border border-border"></div>
                    </label>
                  </div>
                  <div className="flex flex-col gap-4 p-4 bg-danger/10 border border-danger/20 rounded-xl">
                    <div className="flex items-center justify-between">
                      <div>
                        <h4 className="font-bold text-danger">Maintenance Mode (API Lockout)</h4>
                        <p className="text-sm text-danger/80">Take the entire SaaS offline (returns 503 to all tenants).</p>
                      </div>
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" className="sr-only peer" checked={systemPrefs.maintenance_mode} onChange={async (e) => {
                          const isEnabled = e.target.checked;
                          const msg = (document.getElementById('maintenance_msg') as HTMLInputElement)?.value || 'System under maintenance.';
                          const backup = systemPrefs.maintenance_mode;
                          setSystemPrefs(prev => ({ ...prev, maintenance_mode: isEnabled }));
                          try {
                            const res = await api.post('/superadmin/maintenance/toggle', { enabled: isEnabled, message: msg });
                            if (res.data.bypass_url) toast.success(`Bypass URL: ${res.data.bypass_url}`, { duration: 10000 });
                            else toast.success(res.data.message);
                          } catch (err: any) {
                            setSystemPrefs(prev => ({ ...prev, maintenance_mode: backup }));
                            toast.error(err.response?.data?.message || 'Failed to toggle maintenance mode');
                          }
                        }} />
                        <div className="w-11 h-6 bg-surface peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-danger peer-checked:after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-danger border border-danger/50"></div>
                      </label>
                    </div>
                    {systemPrefs.maintenance_mode ? (
                       <div className="bg-danger/20 p-3 rounded-lg border border-danger/30">
                         <p className="text-xs text-white font-bold mb-1">Active Bypass Secret (Save this URL!)</p>
                         <p className="text-xs text-danger font-mono break-all bg-background/50 p-2 rounded">Check your recent toast or audit logs to recover your bypass URL if you lose access.</p>
                       </div>
                    ) : (
                      <input id="maintenance_msg" placeholder="Optional offline message for tenants..." className="bg-background border border-danger/20 rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-danger/50 transition-colors w-full" />
                    )}
                  </div>
                </div>

                <div className="flex justify-end pt-8">
                  <button type="submit" disabled={submitting} className="bg-primary hover:brightness-110 text-white px-8 py-3 rounded-xl font-bold transition-all duration-150 active:scale-[0.97] shadow-lg disabled:opacity-50">
                    {submitting ? 'Saving...' : 'Save System Preferences'}
                  </button>
                </div>
              </form>
            </div>
          )}

          {activeTab === 'profile' && (
            <div className="animate-in slide-in-from-right-4 duration-300">
              <h2 className="text-xl font-bold text-white mb-6 border-b border-border pb-4">Admin Profile & Security</h2>
              <form onSubmit={handleProfileSubmit} className="flex flex-col gap-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">Full Name</label>
                    <input required value={profile.name} onChange={e => setProfile({...profile, name: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" placeholder="Super Admin" />
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">Email Address</label>
                    <input required type="email" value={profile.email} onChange={e => setProfile({...profile, email: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" placeholder="admin@example.com" />
                  </div>
                </div>
                <div className="flex flex-col gap-2">
                  <label className="text-sm font-medium text-text-muted">New Password (leave blank to keep current)</label>
                  <input type="password" value={profile.password} onChange={e => setProfile({...profile, password: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" placeholder="••••••••" />
                </div>
                
                <div className="flex items-center gap-4 p-4 border border-border rounded-xl bg-background/50 mt-2">
                  <div className="text-2xl">{profile.two_factor_enabled ? '🔒' : '🔓'}</div>
                  <div className="flex-1">
                    <h4 className="font-bold text-white">Two-Factor Authentication</h4>
                    <p className="text-sm text-text-muted">Protect your SuperAdmin account with an authenticator app.</p>
                  </div>
                  <button type="button" onClick={() => setProfile({...profile, two_factor_enabled: !profile.two_factor_enabled})} className={`px-4 py-2 rounded-lg font-bold transition-colors ${profile.two_factor_enabled ? 'bg-danger/10 text-danger border border-danger/20 hover:bg-danger/20' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20'}`}>
                    {profile.two_factor_enabled ? 'Disable 2FA' : 'Enable 2FA'}
                  </button>
                </div>

                <div className="flex justify-end pt-4">
                  <button type="submit" disabled={submitting} className="bg-primary hover:brightness-110 text-white px-8 py-3 rounded-xl font-bold transition-all duration-150 active:scale-[0.97] shadow-lg disabled:opacity-50">
                    {submitting ? 'Saving...' : 'Update Admin Profile'}
                  </button>
                </div>
              </form>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
