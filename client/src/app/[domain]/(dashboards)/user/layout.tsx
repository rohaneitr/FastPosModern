'use client';

import React, { useState, useEffect } from 'react';
import LanguageSwitcher from '@/components/LanguageSwitcher';
import Link from 'next/link';
import { useRouter, usePathname } from 'next/navigation';
import api from '@/lib/api';
import { useTranslation, SUPPORTED_LANGUAGES, type LanguageCode } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';
import ClockWidget from '@/components/ClockWidget';

export default function UserLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { t, locale, setLocale } = useTranslation();
  const { currentCurrency, currencies, updateSettings } = useCurrency();
  const [user, setUser] = useState<{name: string, email: string, roles?: any[]} | null>(null);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isPending, setIsPending] = useState(false);
  const [licenseKey, setLicenseKey] = useState('');
  const [isActivating, setIsActivating] = useState(false);

  // Close mobile menu on route change
  useEffect(() => {
    setIsMobileMenuOpen(false);
  }, [pathname]);

  useEffect(() => {
    const token = sessionStorage.getItem('fastpos_token') || localStorage.getItem('fastpos_token');
    const storedUser = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');
    
    if (!token || !storedUser) {
      router.replace('/login');
      return;
    }

    try {
      const parsedUser = JSON.parse(storedUser);
      setUser(parsedUser);
      
      // Pending Activation Check
      if (['pending_activation', 'pending_license'].includes(parsedUser.business?.status)) {
         setIsPending(true);
      } else {
         setIsPending(false);
      }
    } catch {
      localStorage.removeItem('fastpos_token');
      localStorage.removeItem('fastpos_user');
      router.replace('/login');
    }
  }, [router]);

  const handleLogout = async () => {
    try { await api.post('/logout'); } catch {}
    finally {
      localStorage.removeItem('fastpos_user');
      localStorage.removeItem('fastpos_token');
      sessionStorage.removeItem('fastpos_user');
      sessionStorage.removeItem('fastpos_token');
      router.push('/login');
    }
  };

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

  const menuItems = [
    { name: t('nav.dashboard'), path: '/user', icon: '📊' },
    { name: t('nav.posTerminal'), path: '/user/pos', icon: '🛒' },
    { name: t('nav.mySales'), path: '/user/sales', icon: '💵' },
    { name: t('nav.stockChecking'), path: '/user/inventory', icon: '📦' },
    { name: t('nav.clockInOut'), path: '/user/attendance', icon: '⏱️' },
  ];

  return (
    <div className="flex h-screen bg-background overflow-hidden">
      {/* Mobile Overlay */}
      {isMobileMenuOpen && (
        <div 
          className="fixed inset-0 bg-black/50 z-40 md:hidden backdrop-blur-sm"
          onClick={() => setIsMobileMenuOpen(false)}
        />
      )}

      <aside className={`w-64 bg-surface border-r border-border flex flex-col fixed inset-y-0 left-0 z-50 transform transition-transform duration-300 md:relative md:translate-x-0 ${isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="h-16 flex items-center justify-between px-6 border-b border-border">
          <span className="text-xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500">
            FastPOS <span className="text-white text-sm font-medium ml-1">{t('user.title')}</span>
          </span>
          <button onClick={() => setIsMobileMenuOpen(false)} className="md:hidden text-text-muted hover:text-white">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>
        <div className="flex-1 overflow-y-auto py-4 px-3 custom-scrollbar">
          <div className="mb-4 px-3">
            <p className="text-xs font-bold text-text-muted uppercase tracking-wider">{t('user.staffTerminal')}</p>
          </div>
          <nav className="flex flex-col gap-0.5">
            {menuItems.map((item) => {
              const isActive = pathname === item.path || (item.path !== '/user' && pathname.startsWith(item.path));
              return (
                <Link key={item.path} href={item.path}
                  className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all border border-transparent
                    ${isActive ? 'text-white bg-blue-500/15 border-blue-500/20' : 'text-text-muted hover:text-white hover:bg-blue-500/10 hover:border-blue-500/20'}`}>
                  <span className="text-lg">{item.icon}</span>{item.name}
                </Link>
              );
            })}
          </nav>
        </div>
        <div className="p-4 border-t border-border">
          <Link href="/user/profile" className="flex items-center gap-3 mb-4 hover:bg-surface/50 p-2 -mx-2 rounded-lg transition-colors cursor-pointer block">
            <div className="w-8 h-8 rounded-full bg-blue-500/20 text-blue-500 flex items-center justify-center font-bold text-sm">
              {user?.name?.charAt(0)?.toUpperCase() || 'U'}
            </div>
            <div className="flex flex-col min-w-0 flex-1">
              <span className="text-sm font-bold text-white truncate">{user?.name || t('user.title')}</span>
              <span className="text-xs text-blue-400 font-medium truncate">{user?.roles?.[0]?.name || 'User'}</span>
            </div>
          </Link>
          <button onClick={handleLogout}
            className="w-full bg-danger/10 hover:bg-danger/20 text-danger border border-danger/20 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            {t('auth.signOut')}
          </button>
        </div>
      </aside>

      <main className="flex-1 flex flex-col h-full overflow-hidden relative">
        <header className="h-16 bg-surface/50 border-b border-border flex items-center justify-between px-4 md:px-6 backdrop-blur-md z-10">
          <div className="flex items-center gap-2 md:gap-4">
            <button onClick={() => setIsMobileMenuOpen(true)} className="md:hidden text-text-muted hover:text-white p-1">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
            <div className="text-sm font-medium text-text-muted hidden sm:block">{t('user.register')}: <span className="text-white font-bold">REG-01</span></div>
          </div>
          <div className="flex items-center gap-3">
            <ClockWidget />
            <select 
              value={currentCurrency.code} 
              onChange={(e) => updateSettings({ code: e.target.value })}
              className="bg-blue-500/10 border border-blue-500/20 rounded-lg px-2 py-1 text-xs font-bold text-blue-400 outline-none cursor-pointer"
            >
              {currencies.map(c => (
                <option key={c.code} value={c.code} className="bg-background text-foreground font-normal">
                  {c.symbol} {c.code}
                </option>
              ))}
            </select>
            <LanguageSwitcher />
          </div>
        </header>
        <div className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 custom-scrollbar relative">
          {isPending ? (
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
            children
          )}
        </div>
      </main>
    </div>
  );
}
