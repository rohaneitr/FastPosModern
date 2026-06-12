'use client';

import React, { useState, useEffect } from 'react';
import LanguageSwitcher from '@/components/LanguageSwitcher';
import Link from 'next/link';
import { useRouter, usePathname } from 'next/navigation';
import api from '@/lib/api';
import { useTranslation, SUPPORTED_LANGUAGES, type LanguageCode } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';
import AnnouncementBanner from '@/components/AnnouncementBanner';
import NotificationBell from '@/components/NotificationBell';

export default function SuperAdminLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { t, locale, setLocale } = useTranslation();
  const { currentCurrency, currencies, updateSettings } = useCurrency();
  const [user, setUser] = useState<{name: string, email: string, roles?: any[]} | null>(null);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  // Close mobile menu on route change
  useEffect(() => {
    setIsMobileMenuOpen(false);
  }, [pathname]);

  useEffect(() => {
    const storedUser = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');
    const token = sessionStorage.getItem('fastpos_token') || localStorage.getItem('fastpos_token');
    
    if (!token || !storedUser) {
      router.replace('/superadmin-login');
      return;
    }

    try {
      const parsedUser = JSON.parse(storedUser);
      const isSuperAdmin = parsedUser.roles?.some((r: any) => r.name === 'SuperAdmin');
      
      if (!isSuperAdmin) {
        router.replace('/superadmin-login');
        return;
      }
      
      setUser(parsedUser);
    } catch (err) {
      localStorage.removeItem('fastpos_user');
      localStorage.removeItem('fastpos_token');
      sessionStorage.removeItem('fastpos_user');
      sessionStorage.removeItem('fastpos_token');
      router.replace('/superadmin-login');
    }
  }, [router]);

  const handleLogout = async () => {
    try { await api.post('/logout'); } catch {}
    finally {
      localStorage.removeItem('fastpos_user');
      localStorage.removeItem('fastpos_token');
      sessionStorage.removeItem('fastpos_user');
      sessionStorage.removeItem('fastpos_token');
      router.push('/superadmin-login');
    }
  };

  const menuItems = [
    { name: t('nav.platformOverview'), path: '/superadmin', icon: '🌍' },
    { name: 'Pending Approvals', path: '/superadmin/approvals', icon: '🛡️' },
    { name: 'Sub. Requests', path: '/superadmin/subscription-requests', icon: '📥' },
    { name: t('nav.manageTenants'), path: '/superadmin/tenants', icon: '🏢' },
    { name: t('nav.subscriptionsBilling'), path: '/superadmin/subscriptions', icon: '💳' },
    { name: t('nav.licenseKeys'), path: '/superadmin/licenses', icon: '🔑' },
    { name: t('nav.systemMonitoring'), path: '/superadmin/monitoring', icon: '📡' },
    { name: 'Backup Center', path: '/superadmin/backups', icon: '📥' },
    { name: 'Audit Logs',  path: '/superadmin/audit-logs',  icon: '📋' },
    { name: 'Email Logs',  path: '/superadmin/email-logs',  icon: '📧' },
    { name: t('nav.globalSettings'), path: '/superadmin/settings', icon: '⚙️' },
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
        <div className="h-16 flex items-center justify-between px-6 border-b border-border bg-rose-500/5">
          <span className="text-xl font-black bg-clip-text text-transparent bg-gradient-to-r from-rose-500 to-orange-400">
            FastPOS <span className="text-white text-sm font-medium ml-1">SaaS</span>
          </span>
          <button onClick={() => setIsMobileMenuOpen(false)} className="md:hidden text-text-muted hover:text-white">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>
        <div className="flex-1 overflow-y-auto py-4 px-3 custom-scrollbar">
          <div className="mb-4 px-3">
            <p className="text-xs font-bold text-rose-500 uppercase tracking-wider">{t('superadmin.title')}</p>
          </div>
          <nav className="flex flex-col gap-0.5">
            {menuItems.map((item) => {
              const isActive = pathname === item.path || (item.path !== '/superadmin' && pathname.startsWith(item.path));
              return (
                <Link key={item.path} href={item.path}
                  className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all border border-transparent
                    ${isActive ? 'text-white bg-rose-500/15 border-rose-500/20' : 'text-text-muted hover:text-white hover:bg-rose-500/10 hover:border-rose-500/20'}`}>
                  <span className="text-lg">{item.icon}</span>{item.name}
                </Link>
              );
            })}
          </nav>
        </div>
        <div className="p-4 border-t border-border">
          <Link href="/superadmin/profile" className="flex items-center gap-3 mb-4 hover:bg-surface/50 p-2 -mx-2 rounded-lg transition-colors cursor-pointer block">
            <div className="w-8 h-8 rounded-full bg-rose-500/20 text-rose-500 flex items-center justify-center font-bold text-sm">
              {user?.name?.charAt(0)?.toUpperCase() || 'S'}
            </div>
            <div className="flex flex-col min-w-0 flex-1">
              <span className="text-sm font-bold text-white truncate">{user?.name || 'Super Admin'}</span>
              <span className="text-xs text-rose-400 font-medium truncate">{user?.email || ''}</span>
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
        <AnnouncementBanner />
        <header className="h-16 bg-surface/50 border-b border-rose-500/10 flex items-center justify-between px-4 md:px-6 backdrop-blur-md z-10">
          <div className="flex items-center gap-2 md:gap-4">
            <button onClick={() => setIsMobileMenuOpen(true)} className="md:hidden text-text-muted hover:text-white p-1">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
            <span className="px-2.5 py-1 rounded bg-rose-500/20 text-rose-400 text-xs font-bold border border-rose-500/30">{t('superadmin.saasLabel')}</span>
          </div>
          <div className="flex items-center gap-3">
            <select 
              value={currentCurrency.code} 
              onChange={(e) => updateSettings({ code: e.target.value })}
              className="bg-rose-500/10 border border-rose-500/20 rounded-lg px-2 py-1 text-xs font-bold text-rose-400 outline-none cursor-pointer"
            >
              {currencies.map(c => (
                <option key={c.code} value={c.code} className="bg-background text-foreground font-normal">
                  {c.symbol} {c.code}
                </option>
              ))}
            </select>
            <NotificationBell />
            <LanguageSwitcher />
          </div>
        </header>
        <div className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 custom-scrollbar relative">{children}</div>
      </main>
    </div>
  );
}
