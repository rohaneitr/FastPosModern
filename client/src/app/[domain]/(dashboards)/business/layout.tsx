'use client';



import React, { useState, useEffect } from 'react';
import LanguageSwitcher from '@/components/LanguageSwitcher';
import Link from 'next/link';
import { useRouter, usePathname } from 'next/navigation';
import api from '@/lib/api';
import { useTranslation, SUPPORTED_LANGUAGES, type LanguageCode } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';
import ClockWidget from '@/components/ClockWidget';
import AnnouncementBanner from '@/components/AnnouncementBanner';
import NotificationBell from '@/components/NotificationBell';

export default function BusinessLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { t, locale, setLocale } = useTranslation();
  const { currentCurrency, currencies, updateSettings } = useCurrency();
  const [user, setUser] = useState<{name: string, email: string, roles?: any[]} | null>(null);
  const [features, setFeatures] = useState<string[] | null>(null);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isPending, setIsPending] = useState(false);
  const [licenseKey, setLicenseKey] = useState('');
  const [isActivating, setIsActivating] = useState(false);
  
  // Global Search State
  const [globalSearch, setGlobalSearch] = useState('');
  const [globalResults, setGlobalResults] = useState<any[]>([]);
  const [isSearching, setIsSearching] = useState(false);

  // Close mobile menu and global search on route change
  useEffect(() => {
    setIsMobileMenuOpen(false);
    setGlobalSearch('');
  }, [pathname]);

  // Global Search effect
  useEffect(() => {
    const delayDebounceFn = setTimeout(async () => {
      if (!globalSearch) {
        setGlobalResults([]);
        return;
      }
      setIsSearching(true);
      try {
        const res = await api.get(`/products?page=1&search=${encodeURIComponent(globalSearch)}`);
        setGlobalResults(res.data.data || res.data || []);
      } catch (e) {
        console.error("Global search failed", e);
      } finally {
        setIsSearching(false);
      }
    }, 400);
    return () => clearTimeout(delayDebounceFn);
  }, [globalSearch]);

  useEffect(() => {
    const checkAuth = async () => {
      let parsedUser = null;
      const storedUser = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');
      
      if (storedUser) {
        try {
          parsedUser = JSON.parse(storedUser);
        } catch {}
      }

      if (!parsedUser) {
        try {
          // If no stored user but middleware let us through, fetch from API
          const res = await api.get('/user');
          parsedUser = res.data.user || res.data;
          sessionStorage.setItem('fastpos_user', JSON.stringify(parsedUser));
        } catch {
          router.replace('/login');
          return;
        }
      }

      if (!parsedUser) {
        router.replace('/login');
        return;
      }

      const isAdmin = parsedUser.roles?.some((r: any) => 
        ['BusinessAdmin', 'Manager', 'Admin'].includes(r.name)
      );
      const isCashier = parsedUser.roles?.some((r: any) => 
        ['Cashier', 'Staff'].includes(r.name)
      );
      
      if (!isAdmin && !isCashier) {
        router.replace('/login');
        return;
      }

      // Hard Route Guard for Cashiers
      if (isCashier && !isAdmin) {
        const adminPaths = ['/business/dashboard', '/business/reports', '/business/settings', '/business/hr', '/business/users', '/business/accounting'];
        const isTryingAdmin = pathname === '/business' || adminPaths.some(p => pathname.startsWith(p));
        
        if (isTryingAdmin) {
          alert('Access Denied: You do not have permission to view this page.');
          router.replace('/user/pos');
          return;
        }
      }
      
      setUser(parsedUser);

      // Pending Activation Check
      if (['pending_activation', 'pending_license'].includes(parsedUser.business?.status)) {
         setIsPending(true);
      } else {
         setIsPending(false);
      }

      // Use active_modules from the business context as the primary source of truth
      if (parsedUser.business?.active_modules !== undefined && parsedUser.business?.active_modules !== null) {
        let mods = parsedUser.business.active_modules;
        if (typeof mods === 'string') {
          try { mods = JSON.parse(mods); } catch(e) { mods = []; }
        }
        setFeatures(Array.isArray(mods) ? mods : []);
      } else {
        // Fallback to fetching features from subscription
        api.get('/settings/subscription').then(res => {
          const planFeatures = res.data.subscription?.plan?.features;
          let parsed = [];
          if (Array.isArray(planFeatures)) parsed = planFeatures;
          else if (typeof planFeatures === 'string') {
            try { parsed = JSON.parse(planFeatures); } catch(e) {}
          }
          setFeatures(Array.isArray(parsed) ? parsed : []);
        }).catch(() => setFeatures([]));
      }
    };

    checkAuth().catch(console.error);
  }, [router, pathname]);

  const handleLogout = async () => {
    try { await api.post('/logout'); } catch {}
    finally {
      localStorage.removeItem('fastpos_user');
      sessionStorage.removeItem('fastpos_user');
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

  const isCashier = user?.roles?.some(r => ['Cashier', 'Staff'].includes(r.name));

  const hasModule = (slugs: string[]) => {
      // If features is null (still loading), show all modules as default
      if (features === null) return true;
      if (!Array.isArray(features)) return false;
      return features.some(feature => 
          slugs.some(slug => feature.toLowerCase().includes(slug.toLowerCase()))
      );
  };

  const menuItems = [
    ...(isCashier ? [] : [{ name: t('nav.dashboard'), path: '/business', icon: '📊', isVisible: true }]),
    { name: t('nav.salesInvoices'), path: '/business/sales', icon: '💰', isVisible: hasModule(['pos', 'point of sale']) },
    { name: 'Quotations', path: '/business/quotations', icon: '📄', isVisible: hasModule(['quotations']) },
    { name: t('nav.products'), path: '/business/products', icon: '📦', isVisible: hasModule(['inventory', 'inventory management', 'products']) },
    { name: t('nav.inventoryStock'), path: '/business/inventory', icon: '🏭', isVisible: hasModule(['inventory', 'inventory management', 'stock']) },
    { name: 'Stock Transfers', path: '/business/inventory/transfers', icon: '🚚', isVisible: hasModule(['inventory', 'inventory management']) },
    { name: t('nav.categoriesBrands'), path: '/business/categories', icon: '📑', isVisible: hasModule(['inventory', 'inventory management', 'catalog']) },
    { name: t('nav.customersCRM'), path: '/business/contacts', icon: '👥', isVisible: hasModule(['crm', 'customers']) },
    { name: 'Due Collection', path: '/business/customers/due', icon: '💸', isVisible: hasModule(['crm']) },
    { name: t('nav.purchases'), path: '/business/purchases', icon: '📥', isVisible: hasModule(['purchases', 'inventory', 'inventory management']) },
    ...(isCashier ? [] : [{ name: t('nav.accounting'), path: '/business/accounting', icon: '💳', isVisible: hasModule(['accounting', 'finance']) }]),
    { name: 'PC Builder', path: '/business/quotations/pc-builder', icon: '🖥️', isVisible: hasModule(['pc_builder', 'pc builder']) },
    { name: 'CCTV Builder', path: '/business/quotations/cctv-builder', icon: '📹', isVisible: hasModule(['cctv_builder', 'cctv builder']) },
    { name: 'Pharmacy', path: '/business/pharmacy', icon: '💊', isVisible: hasModule(['pharmacy']) },
    { name: 'Warranty & RMA', path: '/business/warranty', icon: '🛡️', isVisible: hasModule(['warranty', 'rma', 'pos', 'point of sale']) },
    ...(isCashier ? [] : [{ name: 'Staff & HR', path: '/business/hr/employees', icon: '👨‍💼', isVisible: hasModule(['hr', 'human resources']) }]),
    ...(isCashier ? [] : [{ name: 'Payroll', path: '/business/hr/payroll', icon: '💸', isVisible: hasModule(['hr', 'human resources']) }]),
    ...(isCashier ? [] : [{ name: t('nav.usersRoles'), path: '/business/users', icon: '🔐', isVisible: hasModule(['users', 'iam', 'roles']) }]),
    ...(isCashier ? [] : [{ name: t('nav.reports'), path: '/business/reports', icon: '📈', isVisible: hasModule(['reports', 'analytics']) }]),
    ...(isCashier ? [] : [{ name: t('nav.settings'), path: '/business/settings', icon: '⚙️', isVisible: hasModule(['settings']) }]),
    ...(isCashier ? [] : [{ name: 'Subscription', path: '/business/billing', icon: '⭐', isVisible: true }]), // Always visible
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
          <span className="text-xl font-black bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">
            FastPOS <span className="text-white text-sm font-medium ml-1">{t('business.title')}</span>
          </span>
          <button onClick={() => setIsMobileMenuOpen(false)} className="md:hidden text-text-muted hover:text-white">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>
        <div className="flex-1 overflow-y-auto py-4 px-3 custom-scrollbar">
          <div className="mb-4 px-3">
            <p className="text-xs font-bold text-text-muted uppercase tracking-wider">{t('business.tenantAdmin')}</p>
          </div>
          <nav className="flex flex-col gap-0.5">
            {menuItems.filter(item => item.isVisible).map((item) => {
              // Hide HR, Users, and Settings for non-admins/non-managers
              const restrictedPaths = ['/business/hr', '/business/users', '/business/settings'];
              const isRestricted = restrictedPaths.includes(item.path);
              const hasAccess = user?.roles?.some((r: any) => ['BusinessAdmin', 'Admin', 'Manager'].includes(r.name));
              
              if (isRestricted && !hasAccess) return null;

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
        <AnnouncementBanner />
        <header className="h-16 bg-surface/50 border-b border-border flex items-center justify-between px-4 md:px-6 backdrop-blur-md z-10">
          <div className="flex items-center gap-4">
            <button onClick={() => setIsMobileMenuOpen(true)} className="md:hidden text-text-muted hover:text-white p-1">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
            <div className="hidden lg:flex relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted text-sm">🔍</span>
              <input 
                type="text" 
                value={globalSearch}
                onChange={e => setGlobalSearch(e.target.value)}
                placeholder={t('nav.quickSearch')} 
                className="bg-background border border-border rounded-full pl-9 pr-4 py-1.5 text-sm outline-none focus:border-emerald-500/50 w-64 transition-all" 
              />
              
              {/* Global Search Dropdown Overlay */}
              {globalSearch && (
                <div className="absolute top-full left-0 mt-3 w-96 bg-surface border border-border rounded-xl shadow-2xl overflow-hidden z-[100] animate-in slide-in-from-top-2">
                  <div className="bg-background/80 backdrop-blur-md px-4 py-2 border-b border-border/50 flex justify-between items-center">
                    <span className="text-xs font-bold text-text-muted uppercase tracking-wider">Search Results</span>
                    <button onClick={() => setGlobalSearch('')} className="text-text-muted hover:text-white text-xs">esc</button>
                  </div>
                  {isSearching ? (
                    <div className="p-8 text-center flex flex-col items-center gap-3">
                      <div className="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                      <span className="text-sm text-text-muted">Searching global inventory...</span>
                    </div>
                  ) : globalResults.length > 0 ? (
                    <div className="max-h-[60vh] overflow-y-auto custom-scrollbar">
                      {globalResults.map(item => (
                        <div key={item.id} className="p-3 border-b border-border/30 hover:bg-white/5 flex flex-col cursor-pointer transition-colors" 
                             onClick={() => { setGlobalSearch(''); router.push('/business/products'); }}>
                          <div className="flex justify-between items-start">
                            <span className="font-bold text-sm text-white line-clamp-1">{item.name}</span>
                            <span className="text-emerald-400 font-bold text-sm whitespace-nowrap ml-2">
                              {currentCurrency.symbol}{item.sell_price_inc_tax || item.price || '0.00'}
                            </span>
                          </div>
                          <div className="flex items-center gap-2 mt-1">
                            <span className="text-xs text-text-muted font-mono bg-background/50 px-1.5 py-0.5 rounded">{item.sku}</span>
                            {item.generic_name && (
                              <span className="text-xs text-text-muted/70 truncate">{item.generic_name}</span>
                            )}
                          </div>
                        </div>
                      ))}
                      <div className="p-3 text-center bg-background/50 hover:bg-emerald-500/10 cursor-pointer transition-colors"
                           onClick={() => { setGlobalSearch(''); router.push('/business/products'); }}>
                        <span className="text-xs text-emerald-500 font-bold">View all matches in Catalog →</span>
                      </div>
                    </div>
                  ) : (
                    <div className="p-8 text-center flex flex-col items-center gap-2">
                      <span className="text-3xl opacity-50">🔍</span>
                      <span className="text-sm text-white font-medium">No results found</span>
                      <span className="text-xs text-text-muted">No products or medicines match "{globalSearch}"</span>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
          <div className="flex items-center gap-3">
            <ClockWidget />
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
            <LanguageSwitcher />
            <NotificationBell />
          </div>
        </header>
        <div className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 custom-scrollbar relative">
          {isPending && pathname !== '/business/billing' ? (
            <div className="absolute inset-0 z-50 flex items-center justify-center bg-background/95 backdrop-blur-sm p-4">
              <div className="max-w-md w-full bg-surface border border-rose-500/30 rounded-2xl p-8 text-center shadow-2xl relative overflow-hidden">
                <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-rose-500 to-orange-500"></div>
                <div className="w-16 h-16 bg-rose-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
                  <span className="text-3xl">🔒</span>
                </div>
                <h2 className="text-2xl font-black text-white mb-2">License Pending Activation</h2>
                <p className="text-text-muted text-sm mb-6">
                  Your tenant license is currently inactive. Please activate your subscription or enter a valid license key to unlock the FastPOS platform.
                </p>
                <div className="flex flex-col gap-3">
                  <input 
                    type="text" 
                    value={licenseKey}
                    onChange={(e) => setLicenseKey(e.target.value)}
                    placeholder="Enter License Key"
                    className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-emerald-500 transition-colors text-center font-mono"
                  />
                  <button 
                    onClick={handleActivateLicense}
                    disabled={isActivating || !licenseKey}
                    className="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:shadow-[0_0_30px_rgba(16,185,129,0.5)] disabled:opacity-50"
                  >
                    {isActivating ? 'Activating...' : 'Activate License'}
                  </button>
                  <div className="flex items-center gap-4 my-2">
                    <div className="h-px bg-border flex-1"></div>
                    <span className="text-text-muted text-xs font-bold uppercase">or</span>
                    <div className="h-px bg-border flex-1"></div>
                  </div>
                  <Link href="/business/billing" className="inline-block bg-surface border border-border hover:bg-white/5 text-white font-bold py-3 px-6 rounded-xl transition-all w-full">
                    View Subscription Plans
                  </Link>
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
