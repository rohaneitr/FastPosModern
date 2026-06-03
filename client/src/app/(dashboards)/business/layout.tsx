'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter, usePathname } from 'next/navigation';
import api from '@/lib/api';
import { useTranslation, SUPPORTED_LANGUAGES, type LanguageCode } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';

export default function BusinessLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { t, locale, setLocale } = useTranslation();
  const { currentCurrency, currencies, updateSettings } = useCurrency();
  const [user, setUser] = useState<{name: string, email: string, roles?: any[]} | null>(null);

  useEffect(() => {
    const storedUser = localStorage.getItem('fastpos_user');
    if (storedUser) setUser(JSON.parse(storedUser));
  }, []);

  const handleLogout = async () => {
    try { await api.post('/logout'); } catch {}
    finally {
      localStorage.removeItem('fastpos_user');
      localStorage.removeItem('fastpos_token');
      router.push('/login');
    }
  };

  const menuItems = [
    { name: t('nav.dashboard'), path: '/business', icon: '📊' },
    { name: t('nav.salesInvoices'), path: '/business/sales', icon: '💰' },
    { name: t('nav.products'), path: '/business/products', icon: '📦' },
    { name: t('nav.inventoryStock'), path: '/business/inventory', icon: '🏭' },
    { name: t('nav.categoriesBrands'), path: '/business/categories', icon: '📑' },
    { name: t('nav.customersCRM'), path: '/business/contacts', icon: '👥' },
    { name: t('nav.purchases'), path: '/business/purchases', icon: '📥' },
    { name: t('nav.accounting'), path: '/business/accounting', icon: '💳' },
    { name: t('nav.hrStaff'), path: '/business/hr', icon: '👨‍💼' },
    { name: t('nav.usersRoles'), path: '/business/users', icon: '🛡️' },
    { name: t('nav.reports'), path: '/business/reports', icon: '📈' },
    { name: t('nav.settings'), path: '/business/settings', icon: '⚙️' },
  ];

  return (
    <div className="flex h-screen bg-background overflow-hidden">
      <aside className="w-64 bg-surface border-r border-border flex flex-col hidden md:flex">
        <div className="h-16 flex items-center px-6 border-b border-border">
          <span className="text-xl font-black bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">
            FastPOS <span className="text-white text-sm font-medium ml-1">{t('business.title')}</span>
          </span>
        </div>
        <div className="flex-1 overflow-y-auto py-4 px-3 custom-scrollbar">
          <div className="mb-4 px-3">
            <p className="text-xs font-bold text-text-muted uppercase tracking-wider">{t('business.tenantAdmin')}</p>
          </div>
          <nav className="flex flex-col gap-0.5">
            {menuItems.map((item) => {
              const isActive = pathname === item.path || (item.path !== '/business' && pathname.startsWith(item.path));
              return (
                <Link key={item.path} href={item.path}
                  className={`flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all border border-transparent
                    ${isActive ? 'text-white bg-emerald-500/15 border-emerald-500/20' : 'text-text-muted hover:text-white hover:bg-emerald-500/10 hover:border-emerald-500/20'}`}>
                  <span className="text-lg">{item.icon}</span>{item.name}
                </Link>
              );
            })}
          </nav>
        </div>
        <div className="p-4 border-t border-border">
          <Link href="/business/profile" className="flex items-center gap-3 mb-4 hover:bg-surface/50 p-2 -mx-2 rounded-lg transition-colors cursor-pointer block">
            <div className="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-500 flex items-center justify-center font-bold text-sm">
              {user?.name?.charAt(0)?.toUpperCase() || 'B'}
            </div>
            <div className="flex flex-col min-w-0 flex-1">
              <span className="text-sm font-bold text-white truncate">{user?.name || t('business.tenantAdmin')}</span>
              <span className="text-xs text-text-muted truncate">{user?.email || ''}</span>
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
        <header className="h-16 bg-surface/50 border-b border-border flex items-center justify-between px-6 backdrop-blur-md z-10">
          <div className="flex items-center gap-4">
            <div className="hidden lg:flex relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted text-sm">🔍</span>
              <input type="text" placeholder={t('nav.quickSearch')} className="bg-background border border-border rounded-full pl-9 pr-4 py-1.5 text-sm outline-none focus:border-emerald-500/50 w-64 transition-all" />
            </div>
          </div>
          <div className="flex items-center gap-3">
            {/* Currency indicator */}
            <select 
              value={currentCurrency.code} 
              onChange={(e) => updateSettings({ code: e.target.value })}
              className="bg-emerald-500/10 border border-emerald-500/20 rounded-lg px-2 py-1 text-xs font-bold text-emerald-400 outline-none cursor-pointer"
            >
              {currencies.map(c => (
                <option key={c.code} value={c.code} className="bg-background text-foreground font-normal">
                  {c.symbol} {c.code}
                </option>
              ))}
            </select>
            {/* Language Switcher */}
            <select value={locale} onChange={(e) => setLocale(e.target.value as LanguageCode)}
              className="bg-background border border-border rounded-lg px-2 py-1 text-xs text-foreground outline-none cursor-pointer">
              {SUPPORTED_LANGUAGES.map(lang => (
                <option key={lang.code} value={lang.code}>{lang.nativeName}</option>
              ))}
            </select>
            <button className="text-text-muted hover:text-white relative">
              🔔<span className="absolute -top-1 -right-1 w-2 h-2 bg-rose-500 rounded-full"></span>
            </button>
          </div>
        </header>
        <div className="flex-1 overflow-y-auto p-6 lg:p-8 custom-scrollbar relative z-0">{children}</div>
      </main>
    </div>
  );
}
