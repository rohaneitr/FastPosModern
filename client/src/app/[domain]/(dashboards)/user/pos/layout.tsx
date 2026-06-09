'use client';
import React, { useEffect, useState } from 'react';
import Image from 'next/image';
import LanguageSwitcher from '@/components/LanguageSwitcher';
import api from '@/lib/api';
import { RegisterSessionProvider } from '@/features/sales/cash-control/RegisterSessionProvider';

export default function POSLayout({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<any>(null);
  const [licenseKey, setLicenseKey] = useState('');
  const [isActivating, setIsActivating] = useState(false);

  useEffect(() => {
    try {
      const userJson = localStorage.getItem('fastpos_user');
      if (userJson) {
        setUser(JSON.parse(userJson));
      }
    } catch {}
  }, []);

  const handleActivateLicense = async () => {
    if (!licenseKey.trim()) return alert('Please enter a valid license key.');
    setIsActivating(true);
    try {
      await api.post('/tenant/activate-license', { license_key: licenseKey.trim() });
      alert('License activated successfully! Reloading...');
      window.location.reload();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to activate license. Invalid or expired key.');
    } finally {
      setIsActivating(false);
    }
  };

  const handleLogout = () => {
    localStorage.removeItem('fastpos_user');
    localStorage.removeItem('fastpos_token');
    window.location.href = '/login';
  };

  return (
    <div className="min-h-screen bg-background text-foreground overflow-hidden">
      {/* Background decorative elements */}
      <div className="fixed top-[-10%] left-[-10%] w-[40%] h-[40%] rounded-full bg-primary/20 blur-[120px] pointer-events-none" />
      <div className="fixed bottom-[-10%] right-[-10%] w-[30%] h-[30%] rounded-full bg-success/10 blur-[100px] pointer-events-none" />
      
      {/* Top Navbar */}
      <header className="h-16 glass flex items-center justify-between px-6 z-10 relative">
        <div className="flex items-center gap-4">
          <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center font-bold">
            F
          </div>
          <h1 className="text-xl font-semibold tracking-tight">FastPos Modern</h1>
        </div>
        
        <div className="flex items-center gap-4">
          <LanguageSwitcher />
          <div className="text-sm text-text-muted">Register: Main Store</div>
          <div className="h-8 w-8 rounded-full bg-surface border border-border flex items-center justify-center overflow-hidden">
            {user?.profile_photo_url ? (
              <Image src={user.profile_photo_url} alt="User Avatar" width={32} height={32} className="object-cover w-full h-full" />
            ) : (
              <span className="font-bold">{user?.first_name?.[0] || 'U'}</span>
            )}
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="h-[calc(100vh-4rem)] p-4 relative z-10">
        {['pending_activation', 'pending_license'].includes(user?.business?.status) ? (
          <div className="absolute inset-0 z-50 flex items-center justify-center bg-background/95 backdrop-blur-sm p-4">
            <div className="max-w-md w-full bg-surface border border-rose-500/30 rounded-2xl p-8 text-center shadow-2xl relative overflow-hidden">
              <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-rose-500 to-orange-500"></div>
              <div className="w-16 h-16 bg-rose-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <span className="text-3xl">🔒</span>
              </div>
              <h2 className="text-2xl font-black text-white mb-2">License Pending Activation</h2>
              <p className="text-text-muted text-sm mb-6">
                Your tenant license is currently inactive. Please ask your Business Admin to activate the subscription or enter a valid license key below to unlock the FastPOS platform.
              </p>
              <div className="flex flex-col gap-3">
                  <input 
                    type="text" 
                    value={licenseKey}
                    onChange={(e) => setLicenseKey(e.target.value)}
                    placeholder="Enter License Key"
                    className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-blue-500 transition-colors text-center font-mono"
                  />
                  <button 
                    onClick={handleActivateLicense}
                    disabled={isActivating || !licenseKey}
                    className="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-[0_0_20px_rgba(59,130,246,0.3)] hover:shadow-[0_0_30px_rgba(59,130,246,0.5)] disabled:opacity-50"
                  >
                    {isActivating ? 'Activating...' : 'Activate License'}
                  </button>
                  <button onClick={handleLogout} className="text-text-muted text-sm mt-2 hover:text-white transition-colors">
                    Sign Out
                  </button>
              </div>
            </div>
          </div>
        ) : (
          <RegisterSessionProvider>
            {children}
          </RegisterSessionProvider>
        )}
      </main>
    </div>
  );
}
