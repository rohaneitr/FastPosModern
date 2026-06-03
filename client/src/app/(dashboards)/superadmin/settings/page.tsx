'use client';

import React, { useState, useEffect } from 'react';

export default function SuperadminSettings() {
  const [activeTab, setActiveTab] = useState('general');
  const [isSaving, setIsSaving] = useState(false);
  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);

  // Form State
  const [formData, setFormData] = useState({
    platformName: 'FastPos Modern',
    supportEmail: 'support@fastpos.com',
    companyName: 'FastPOS Global Inc.',
    registrationMode: 'open',
    requireEmailVerification: true,
    defaultTrialDays: 14,
    maintenanceMode: false,
    maxLoginAttempts: 5,
    sessionTimeout: 120, // minutes
    primaryColor: '#10b981'
  });

  // Mock fetch on load
  useEffect(() => {
    // In a real app, this would fetch from /api/superadmin/settings
    const savedSettings = localStorage.getItem('superadmin_settings');
    if (savedSettings) {
      setFormData(JSON.parse(savedSettings));
    }
  }, []);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value, type } = e.target;
    let finalValue: any = value;
    
    if (type === 'checkbox') {
      finalValue = (e.target as HTMLInputElement).checked;
    } else if (type === 'number') {
      finalValue = Number(value);
    }
    
    setFormData(prev => ({ ...prev, [name]: finalValue }));
  };

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({message, type});
    setTimeout(() => setToast(null), 4000);
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);
    
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 800));
    
    localStorage.setItem('superadmin_settings', JSON.stringify(formData));
    setIsSaving(false);
    showToast('Global settings updated successfully', 'success');
  };

  const tabs = [
    { id: 'general', label: 'General Info', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
    { id: 'auth', label: 'Auth & Onboarding', icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
    { id: 'security', label: 'Security & System', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' }
  ];

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-700 pb-12 relative">
      {/* Toast Notification */}
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10
          ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/50 backdrop-blur-md' : 'bg-danger/20 text-danger-300 border border-danger/50 backdrop-blur-md'}`}
        >
          {toast.type === 'success' ? (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" /></svg>
          ) : (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          )}
          {toast.message}
        </div>
      )}

      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-4xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-rose-400 via-orange-400 to-amber-500 tracking-tight">
            Global Platform Settings
          </h1>
          <p className="text-text-muted mt-2 text-sm max-w-2xl leading-relaxed">
            Configure system-wide variables, authentication rules, and maintenance modes. Changes here affect all tenants globally.
          </p>
        </div>
      </div>

      <div className="flex flex-col lg:flex-row gap-8 items-start">
        {/* Navigation Sidebar */}
        <div className="glass-card rounded-2xl border border-border p-3 w-full lg:w-64 flex-shrink-0 sticky top-8">
          <div className="flex flex-row lg:flex-col gap-1 overflow-x-auto">
            {tabs.map(tab => (
              <button 
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-3 px-4 py-3 rounded-xl font-semibold transition-all text-sm whitespace-nowrap
                  ${activeTab === tab.id 
                    ? 'bg-gradient-to-r from-rose-500/10 to-orange-500/10 text-orange-400 border border-orange-500/20 shadow-inner' 
                    : 'text-text-muted hover:bg-white/5 hover:text-white border border-transparent'
                  }`}
              >
                <svg className={`w-5 h-5 ${activeTab === tab.id ? 'text-orange-400' : 'opacity-70'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={tab.icon} />
                </svg>
                {tab.label}
              </button>
            ))}
          </div>
        </div>

        {/* Form Content */}
        <div className="w-full">
          <form onSubmit={handleSave} className="glass-card rounded-2xl border border-border overflow-hidden">
            
            {/* Header of the Tab */}
            <div className="bg-surface/50 border-b border-border p-6">
              <h2 className="text-xl font-bold text-white flex items-center gap-2">
                {tabs.find(t => t.id === activeTab)?.label}
              </h2>
            </div>

            <div className="p-8">
              {activeTab === 'general' && (
                <div className="flex flex-col gap-8 animate-in slide-in-from-right-4 duration-500">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <label className="block text-sm font-semibold text-text-muted mb-2">Platform Name</label>
                      <input 
                        type="text" 
                        name="platformName"
                        value={formData.platformName}
                        onChange={handleChange}
                        required
                        className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-orange-500/50 focus:ring-2 focus:ring-orange-500/20 transition-all" 
                      />
                      <p className="text-xs text-text-muted mt-2">The name displayed on the main login screen.</p>
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-text-muted mb-2">Company Legal Name</label>
                      <input 
                        type="text" 
                        name="companyName"
                        value={formData.companyName}
                        onChange={handleChange}
                        className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-orange-500/50 focus:ring-2 focus:ring-orange-500/20 transition-all" 
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-text-muted mb-2">Global Support Email</label>
                    <input 
                      type="email" 
                      name="supportEmail"
                      value={formData.supportEmail}
                      onChange={handleChange}
                      required
                      className="w-full max-w-md bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-orange-500/50 focus:ring-2 focus:ring-orange-500/20 transition-all" 
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-text-muted mb-2">Brand Primary Color</label>
                    <div className="flex items-center gap-4">
                      <input 
                        type="color" 
                        name="primaryColor"
                        value={formData.primaryColor}
                        onChange={handleChange}
                        className="w-12 h-12 rounded-lg cursor-pointer bg-transparent border-0 p-0" 
                      />
                      <input 
                        type="text" 
                        name="primaryColor"
                        value={formData.primaryColor}
                        onChange={handleChange}
                        className="w-32 bg-background border border-border rounded-xl px-4 py-3 text-white outline-none font-mono text-sm" 
                      />
                    </div>
                  </div>
                </div>
              )}

              {activeTab === 'auth' && (
                <div className="flex flex-col gap-8 animate-in slide-in-from-right-4 duration-500">
                  <div className="p-5 border border-primary/20 bg-primary/5 rounded-xl">
                    <h3 className="font-bold text-primary mb-1">Registration Rules</h3>
                    <p className="text-sm text-text-muted mb-4">Control how new tenants can onboard onto the platform.</p>
                    
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      {[
                        { id: 'open', label: 'Open Registration', desc: 'Anyone can sign up and start a trial.' },
                        { id: 'invite', label: 'Invite Only', desc: 'Requires a referral link or admin invite.' },
                        { id: 'closed', label: 'Closed', desc: 'No new registrations permitted.' }
                      ].map(mode => (
                        <label key={mode.id} className={`cursor-pointer p-4 rounded-xl border transition-all flex flex-col gap-2 ${formData.registrationMode === mode.id ? 'border-primary bg-primary/10 shadow-[0_0_15px_rgba(59,130,246,0.15)]' : 'border-border bg-background hover:bg-surface'}`}>
                          <div className="flex items-center gap-2">
                            <input 
                              type="radio" 
                              name="registrationMode" 
                              value={mode.id} 
                              checked={formData.registrationMode === mode.id}
                              onChange={handleChange}
                              className="text-primary focus:ring-primary h-4 w-4"
                            />
                            <span className="font-bold text-white">{mode.label}</span>
                          </div>
                          <span className="text-xs text-text-muted pl-6">{mode.desc}</span>
                        </label>
                      ))}
                    </div>
                  </div>

                  <div className="flex items-center justify-between p-4 border border-border rounded-xl hover:bg-surface/30 transition-colors">
                    <div>
                      <h4 className="font-bold text-white">Require Email Verification</h4>
                      <p className="text-sm text-text-muted">New owners must verify their email before logging in.</p>
                    </div>
                    <label className="relative inline-flex items-center cursor-pointer">
                      <input type="checkbox" name="requireEmailVerification" checked={formData.requireEmailVerification} onChange={handleChange} className="sr-only peer" />
                      <div className="w-11 h-6 bg-surface peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-success"></div>
                    </label>
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-text-muted mb-2">Default Trial Period (Days)</label>
                    <input 
                      type="number" 
                      name="defaultTrialDays"
                      value={formData.defaultTrialDays}
                      onChange={handleChange}
                      min="0"
                      max="365"
                      className="w-32 bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-orange-500/50 focus:ring-2 focus:ring-orange-500/20 transition-all font-mono" 
                    />
                  </div>
                </div>
              )}

              {activeTab === 'security' && (
                <div className="flex flex-col gap-8 animate-in slide-in-from-right-4 duration-500">
                  <div className={`p-5 border rounded-xl flex items-start justify-between transition-colors
                    ${formData.maintenanceMode ? 'border-amber-500/30 bg-amber-500/10' : 'border-border bg-background'}`}>
                    <div>
                      <h3 className={`font-bold text-lg mb-1 flex items-center gap-2 ${formData.maintenanceMode ? 'text-amber-500' : 'text-white'}`}>
                        {formData.maintenanceMode && <span className="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>}
                        Platform Maintenance Mode
                      </h3>
                      <p className="text-sm text-text-muted max-w-xl">
                        When enabled, all tenants and public APIs will be disabled. A "System Under Maintenance" page will be displayed. Only Superadmins will be able to log in.
                      </p>
                    </div>
                    <label className="relative inline-flex items-center cursor-pointer mt-1">
                      <input type="checkbox" name="maintenanceMode" checked={formData.maintenanceMode} onChange={handleChange} className="sr-only peer" />
                      <div className="w-14 h-7 bg-surface peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-[22px] after:w-[22px] after:transition-all peer-checked:bg-amber-500"></div>
                    </label>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-border/50">
                    <div>
                      <label className="block text-sm font-semibold text-text-muted mb-2">Max Failed Login Attempts</label>
                      <input 
                        type="number" 
                        name="maxLoginAttempts"
                        value={formData.maxLoginAttempts}
                        onChange={handleChange}
                        min="1"
                        max="20"
                        className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-orange-500/50 focus:ring-2 focus:ring-orange-500/20 transition-all font-mono" 
                      />
                      <p className="text-xs text-text-muted mt-2">Locks the account for 15 minutes after limit.</p>
                    </div>
                    <div>
                      <label className="block text-sm font-semibold text-text-muted mb-2">Session Timeout (Minutes)</label>
                      <input 
                        type="number" 
                        name="sessionTimeout"
                        value={formData.sessionTimeout}
                        onChange={handleChange}
                        min="15"
                        max="1440"
                        className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-orange-500/50 focus:ring-2 focus:ring-orange-500/20 transition-all font-mono" 
                      />
                      <p className="text-xs text-text-muted mt-2">Force re-login after inactivity.</p>
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Footer Action */}
            <div className="bg-surface/80 border-t border-border p-6 flex justify-end">
              <button 
                type="submit" 
                disabled={isSaving}
                className="bg-gradient-to-r from-rose-500 to-orange-500 hover:from-rose-600 hover:to-orange-600 text-white px-8 py-3 rounded-xl font-bold transition-all shadow-[0_0_20px_rgba(249,115,22,0.3)] transform hover:scale-105 active:scale-95 disabled:opacity-50 disabled:scale-100 flex items-center gap-2"
              >
                {isSaving ? (
                  <>
                    <svg className="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving Changes...
                  </>
                ) : 'Save Global Settings'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
