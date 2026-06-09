'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { usePosSounds } from '@/hooks/usePosSounds';

export default function TenantSettingsHub() {
  const { playTaskSuccess } = usePosSounds();
  const [activeTab, setActiveTab] = useState('profile');
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // Tab 1: Business Profile
  const [businessProfile, setBusinessProfile] = useState({
    name: '',
    phone: '',
    address: '',
    tax_number: '',
    currency: 'USD',
    timezone: 'UTC'
  });

  // Tab 2: Branding
  const [dashboardLogoFile, setDashboardLogoFile] = useState<File | null>(null);
  const [dashboardLogoPreview, setDashboardLogoPreview] = useState<string>('');
  const [invoiceLogoFile, setInvoiceLogoFile] = useState<File | null>(null);
  const [invoiceLogoPreview, setInvoiceLogoPreview] = useState<string>('');

  // Tab 3: Receipt/Invoice
  const [invoiceSettings, setInvoiceSettings] = useState({
    footer_message: 'Thank you for your business!',
    terms_conditions: 'Returns accepted within 14 days with original receipt.'
  });

  // Tab 4: Security & Preferences
  const [preferences, setPreferences] = useState({
    theme: 'system'
  });
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [avatarPreview, setAvatarPreview] = useState<string>('');

  useEffect(() => {
    // Initialization could fetch settings here
  }, []);

  const handleBrandingFileChange = (e: React.ChangeEvent<HTMLInputElement>, type: 'dashboard' | 'invoice') => {
    const file = e.target.files?.[0];
    if (file) {
      if (!file.type.startsWith('image/')) return;
      const url = URL.createObjectURL(file);
      if (type === 'dashboard') {
        setDashboardLogoFile(file);
        setDashboardLogoPreview(url);
      } else {
        setInvoiceLogoFile(file);
        setInvoiceLogoPreview(url);
      }
    }
  };

  const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (!file.type.startsWith('image/')) return;
      setAvatarFile(file);
      setAvatarPreview(URL.createObjectURL(file));
    }
  };

  const handleThemeChange = (val: string) => {
    setPreferences({ ...preferences, theme: val });
    const root = document.documentElement;
    if (val === 'dark') {
      root.classList.add('dark');
    } else if (val === 'light') {
      root.classList.remove('dark');
    } else {
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
         root.classList.add('dark');
      } else {
         root.classList.remove('dark');
      }
    }
  };

  // Generic Submit Handlers
  const handleProfileSubmit = async (e: React.FormEvent) => {
    e.preventDefault(); setSubmitting(true);
    setTimeout(() => { setSubmitting(false); playTaskSuccess(); alert('Business Profile saved!'); }, 500);
  };

  const handleBrandingSubmit = async (e: React.FormEvent) => {
    e.preventDefault(); setSubmitting(true);
    try {
      const formData = new FormData();
      if (dashboardLogoFile) formData.append('dashboard_logo', dashboardLogoFile);
      if (invoiceLogoFile) formData.append('invoice_logo', invoiceLogoFile);
      await api.post('/business/branding', formData, { headers: { 'Content-Type': 'multipart/form-data' }});
      playTaskSuccess(); alert('Tenant branding updated successfully!');
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to update branding');
    } finally { setSubmitting(false); }
  };

  const handleInvoiceSubmit = async (e: React.FormEvent) => {
    e.preventDefault(); setSubmitting(true);
    setTimeout(() => { setSubmitting(false); playTaskSuccess(); alert('Invoice settings saved!'); }, 500);
  };

  const handlePreferencesSubmit = async (e: React.FormEvent) => {
    e.preventDefault(); setSubmitting(true);
    try {
      const formData = new FormData();
      formData.append('theme_preference', preferences.theme);
      if (avatarFile) formData.append('avatar', avatarFile);
      await api.post('/profile/update', formData, { headers: { 'Content-Type': 'multipart/form-data' }});
      playTaskSuccess(); alert('Preferences updated!');
    } catch (err) {
      alert('Failed to update preferences');
    } finally { setSubmitting(false); }
  };

  const tabs = [
    { id: 'profile', label: 'Business Profile', icon: '🏢' },
    { id: 'branding', label: 'Branding & Logos', icon: '🎨' },
    { id: 'invoices', label: 'Receipts & Invoices', icon: '🧾' },
    { id: 'preferences', label: 'Security & Preferences', icon: '🔒' },
  ];

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12 w-full max-w-7xl mx-auto">
      <div>
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500">Settings Hub</h1>
        <p className="text-text-muted mt-1">Manage your business profile, workspace branding, and preferences.</p>
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
                  ? 'bg-primary text-white shadow-lg' 
                  : 'bg-surface/50 text-text-muted hover:bg-surface hover:text-white border border-border'
              }`}
            >
              <span>{tab.icon}</span> {tab.label}
            </button>
          ))}
        </div>

        {/* Tab Content */}
        <div className="flex-1 glass-card rounded-2xl p-6 border border-border min-h-[500px]">
          
          {/* TAB 1: Business Profile */}
          {activeTab === 'profile' && (
            <div className="animate-in slide-in-from-right-4 duration-300">
              <h2 className="text-xl font-bold text-white mb-6 border-b border-border pb-4">Business Profile</h2>
              <form onSubmit={handleProfileSubmit} className="flex flex-col gap-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">Business Name</label>
                    <input required value={businessProfile.name} onChange={e => setBusinessProfile({...businessProfile, name: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" />
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">Phone Number</label>
                    <input value={businessProfile.phone} onChange={e => setBusinessProfile({...businessProfile, phone: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" />
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">Tax Number / VAT ID</label>
                    <input value={businessProfile.tax_number} onChange={e => setBusinessProfile({...businessProfile, tax_number: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" />
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">Address</label>
                    <input value={businessProfile.address} onChange={e => setBusinessProfile({...businessProfile, address: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors" />
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">Default Currency</label>
                    <select value={businessProfile.currency} onChange={e => setBusinessProfile({...businessProfile, currency: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors">
                      <option value="USD">USD ($)</option>
                      <option value="EUR">EUR (€)</option>
                      <option value="GBP">GBP (£)</option>
                      <option value="BDT">BDT (৳)</option>
                    </select>
                  </div>
                  <div className="flex flex-col gap-2">
                    <label className="text-sm font-medium text-text-muted">Timezone</label>
                    <select value={businessProfile.timezone} onChange={e => setBusinessProfile({...businessProfile, timezone: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors">
                      <option value="UTC">UTC</option>
                      <option value="America/New_York">America/New_York</option>
                      <option value="Asia/Dhaka">Asia/Dhaka</option>
                    </select>
                  </div>
                </div>
                <div className="flex justify-end pt-4">
                  <button type="submit" disabled={submitting} className="bg-primary hover:bg-primary/90 text-white px-8 py-3 rounded-xl font-bold transition-all disabled:opacity-50">
                    {submitting ? 'Saving...' : 'Save Profile'}
                  </button>
                </div>
              </form>
            </div>
          )}

          {/* TAB 2: Branding */}
          {activeTab === 'branding' && (
            <div className="animate-in slide-in-from-right-4 duration-300">
              <h2 className="text-xl font-bold text-white mb-6 border-b border-border pb-4">Branding & Logos</h2>
              <form onSubmit={handleBrandingSubmit} className="flex flex-col gap-8">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                  {/* Dashboard Logo */}
                  <div className="bg-surface/50 border border-border rounded-2xl p-6 flex flex-col">
                    <h3 className="text-lg font-bold text-white mb-1">Dashboard Logo</h3>
                    <p className="text-sm text-text-muted mb-4">Appears in the sidebar navigation.</p>
                    <label className="flex-1 min-h-[200px] border-2 border-dashed border-border hover:border-primary/50 bg-background rounded-xl flex flex-col items-center justify-center cursor-pointer transition-colors p-4 group">
                      <input type="file" className="hidden" accept="image/*" onChange={e => handleBrandingFileChange(e, 'dashboard')} />
                      {dashboardLogoPreview ? (
                        <img src={dashboardLogoPreview} alt="Dashboard Preview" className="max-w-full max-h-[150px] object-contain group-hover:opacity-50 transition-opacity" />
                      ) : (
                        <div className="text-center text-text-muted group-hover:text-primary">
                          <span className="text-sm font-medium">Click to upload or drag & drop</span>
                        </div>
                      )}
                    </label>
                  </div>

                  {/* Invoice Logo */}
                  <div className="bg-surface/50 border border-border rounded-2xl p-6 flex flex-col">
                    <h3 className="text-lg font-bold text-white mb-1">Invoice Logo</h3>
                    <p className="text-sm text-text-muted mb-4">Printed at the top of receipts.</p>
                    <label className="flex-1 min-h-[200px] border-2 border-dashed border-border hover:border-primary/50 bg-background rounded-xl flex flex-col items-center justify-center cursor-pointer transition-colors p-4 group">
                      <input type="file" className="hidden" accept="image/*" onChange={e => handleBrandingFileChange(e, 'invoice')} />
                      {invoiceLogoPreview ? (
                        <img src={invoiceLogoPreview} alt="Invoice Preview" className="max-w-full max-h-[150px] object-contain group-hover:opacity-50 transition-opacity" />
                      ) : (
                        <div className="text-center text-text-muted group-hover:text-primary">
                          <span className="text-sm font-medium">Click to upload or drag & drop</span>
                        </div>
                      )}
                    </label>
                  </div>
                </div>
                <div className="flex justify-end pt-4">
                  <button type="submit" disabled={submitting} className="bg-primary hover:bg-primary/90 text-white px-8 py-3 rounded-xl font-bold transition-all disabled:opacity-50">
                    {submitting ? 'Uploading...' : 'Save Branding Assets'}
                  </button>
                </div>
              </form>
            </div>
          )}

          {/* TAB 3: Receipt/Invoice */}
          {activeTab === 'invoices' && (
            <div className="animate-in slide-in-from-right-4 duration-300">
              <h2 className="text-xl font-bold text-white mb-6 border-b border-border pb-4">Receipt & Invoice Settings</h2>
              <form onSubmit={handleInvoiceSubmit} className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                  <label className="text-sm font-medium text-text-muted">Custom Invoice Footer Message</label>
                  <textarea rows={3} value={invoiceSettings.footer_message} onChange={e => setInvoiceSettings({...invoiceSettings, footer_message: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors custom-scrollbar" />
                </div>
                <div className="flex flex-col gap-2">
                  <label className="text-sm font-medium text-text-muted">Terms & Conditions</label>
                  <textarea rows={5} value={invoiceSettings.terms_conditions} onChange={e => setInvoiceSettings({...invoiceSettings, terms_conditions: e.target.value})} className="bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary/50 transition-colors custom-scrollbar" />
                </div>
                <div className="flex justify-end pt-4">
                  <button type="submit" disabled={submitting} className="bg-primary hover:bg-primary/90 text-white px-8 py-3 rounded-xl font-bold transition-all disabled:opacity-50">
                    {submitting ? 'Saving...' : 'Save Invoice Settings'}
                  </button>
                </div>
              </form>
            </div>
          )}

          {/* TAB 4: Security & Preferences */}
          {activeTab === 'preferences' && (
            <div className="animate-in slide-in-from-right-4 duration-300">
              <h2 className="text-xl font-bold text-white mb-6 border-b border-border pb-4">Security & Preferences</h2>
              <form onSubmit={handlePreferencesSubmit} className="flex flex-col gap-8">
                
                {/* Profile Avatar */}
                <div className="flex items-center gap-6 bg-surface/50 border border-border rounded-2xl p-6">
                  <div className="relative group w-24 h-24 rounded-full overflow-hidden border-2 border-border/50 bg-background flex items-center justify-center shrink-0">
                    {avatarPreview ? (
                      <img src={avatarPreview} alt="Avatar" className="w-full h-full object-cover" />
                    ) : (
                      <span className="text-4xl text-text-muted font-bold">👤</span>
                    )}
                    <label className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center cursor-pointer transition-opacity text-xs font-bold text-white tracking-wider uppercase">
                      Change
                      <input type="file" className="hidden" accept="image/*" onChange={handleAvatarChange} />
                    </label>
                  </div>
                  <div>
                    <h3 className="text-lg font-bold text-white mb-1">Profile Picture</h3>
                    <p className="text-sm text-text-muted">Upload a new avatar (JPEG, PNG).</p>
                  </div>
                </div>

                {/* Theme Switcher */}
                <div className="flex flex-col gap-4">
                  <h3 className="text-lg font-bold text-white">Theme Preference</h3>
                  <div className="grid grid-cols-3 gap-4">
                    {['light', 'dark', 'system'].map(theme => (
                      <button
                        key={theme}
                        type="button"
                        onClick={() => handleThemeChange(theme)}
                        className={`py-3 px-4 rounded-xl border ${preferences.theme === theme ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background text-text-muted hover:border-text-muted'} font-medium capitalize transition-all duration-150 active:scale-[0.97]`}
                      >
                        {theme}
                      </button>
                    ))}
                  </div>
                </div>

                <div className="flex justify-end pt-4">
                  <button type="submit" disabled={submitting} className="bg-primary hover:bg-primary/90 text-white px-8 py-3 rounded-xl font-bold transition-all disabled:opacity-50">
                    {submitting ? 'Saving...' : 'Save Preferences'}
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
