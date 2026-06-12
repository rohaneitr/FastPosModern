'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { useTranslation } from '@/lib/i18n';
import api from '@/lib/api';
import AnnouncementBanner from '@/components/AnnouncementBanner';
import { Breadcrumb } from '@/components/ui/breadcrumb';
import { Sidebar } from '@/components/layout/sidebar/sidebar';
import { businessMenuItems } from '@/components/layout/sidebar/sidebar-config';
import { Topbar } from '@/components/layout/topbar/topbar';
import { LicenseGate } from '@/components/guards/license-gate';
import { Skeleton } from '@/components/ui/skeleton';
import { LogOut } from 'lucide-react';
import { Avatar } from '@/components/ui/avatar';
import Link from 'next/link';

export default function BusinessLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const { t } = useTranslation();

  const [user, setUser] = useState<{ name: string; email: string; roles?: any[]; business?: any } | null>(null);
  const [features, setFeatures] = useState<string[] | null>(null);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [isPending, setIsPending] = useState(false);
  const [isAuthChecked, setIsAuthChecked] = useState(false);

  // Close mobile menu on route change
  useEffect(() => {
    setIsMobileMenuOpen(false);
  }, [pathname]);

  // ── Auth & Feature Resolution ──
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
      const isCashierRole = parsedUser.roles?.some((r: any) =>
        ['Cashier', 'Staff'].includes(r.name)
      );

      if (!isAdmin && !isCashierRole) {
        router.replace('/login');
        return;
      }

      // Hard route guard for cashiers
      if (isCashierRole && !isAdmin) {
        const adminPaths = ['/business/dashboard', '/business/reports', '/business/settings', '/business/hr', '/business/users', '/business/accounting'];
        const isTryingAdmin = pathname === '/business' || adminPaths.some(p => pathname.startsWith(p));
        if (isTryingAdmin) {
          alert('Access Denied: You do not have permission to view this page.');
          router.replace('/user/pos');
          return;
        }
      }

      setUser(parsedUser);

      // License status check
      if (['pending_activation', 'pending_license'].includes(parsedUser.business?.status)) {
        setIsPending(true);
      } else {
        setIsPending(false);
      }

      // Feature module resolution
      if (parsedUser.business?.active_modules !== undefined && parsedUser.business?.active_modules !== null) {
        let mods = parsedUser.business.active_modules;
        if (typeof mods === 'string') {
          try { mods = JSON.parse(mods); } catch { mods = []; }
        }
        setFeatures(Array.isArray(mods) ? mods : []);
      } else {
        api.get('/settings/subscription').then(res => {
          const planFeatures = res.data.subscription?.plan?.features;
          let parsed: string[] = [];
          if (Array.isArray(planFeatures)) parsed = planFeatures;
          else if (typeof planFeatures === 'string') {
            try { parsed = JSON.parse(planFeatures); } catch {}
          }
          setFeatures(Array.isArray(parsed) ? parsed : []);
        }).catch(() => setFeatures([]));
      }

      setIsAuthChecked(true);
    };

    checkAuth().catch(console.error);
  }, [router, pathname]);

  // ── Handlers ──
  const handleLogout = useCallback(async () => {
    try { await api.post('/logout'); } catch {}
    finally {
      localStorage.removeItem('fastpos_user');
      sessionStorage.removeItem('fastpos_user');
      router.push('/login');
    }
  }, [router]);

  const handleActivateLicense = useCallback(async (key: string) => {
    try {
      await api.post('/tenant/activate-license', { license_key: key });
      alert('License activated successfully! Reloading...');
      window.location.reload();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to activate license. Invalid or expired key.');
    }
  }, []);

  const isCashier = user?.roles?.some((r: any) => ['Cashier', 'Staff'].includes(r.name)) ?? false;

  // ── Loading State ──
  if (!isAuthChecked) {
    return (
      <div className="flex h-screen bg-background">
        <div className="w-64 border-r border-border p-4 flex flex-col gap-3 shrink-0 hidden lg:flex">
          <Skeleton className="h-10 w-32 mb-6" />
          {Array.from({ length: 10 }).map((_, i) => (
            <Skeleton key={i} className="h-9 w-full rounded-lg" />
          ))}
        </div>
        <div className="flex-1 p-8 flex flex-col gap-6">
          <Skeleton className="h-8 w-48" />
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-28 rounded-2xl" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex h-screen bg-background overflow-hidden">
      {/* Mobile Overlay */}
      {isMobileMenuOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 md:hidden backdrop-blur-sm"
          onClick={() => setIsMobileMenuOpen(false)}
        />
      )}

      {/* Sidebar */}
      <div
        className={`fixed inset-y-0 left-0 z-50 transform transition-transform duration-300 md:relative md:translate-x-0 ${
          isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <Sidebar
          items={businessMenuItems}
          activeModules={features}
          isCashier={isCashier}
          tenantName={user?.business?.name || user?.name}
          onNavigate={() => setIsMobileMenuOpen(false)}
        />

        {/* User Profile + Logout (at sidebar bottom) */}
        <div className="absolute bottom-0 left-0 right-0 p-3 border-t border-border bg-background/95">
          <Link
            href="/business/profile"
            className="flex items-center gap-3 mb-3 hover:bg-white/5 p-2 rounded-lg transition-colors"
          >
            <Avatar name={user?.name} size="sm" />
            <div className="flex flex-col min-w-0 flex-1">
              <span className="text-sm font-bold text-white truncate">{user?.name || t('business.tenantAdmin')}</span>
              <span className="text-[11px] text-text-muted truncate">{user?.email || ''}</span>
            </div>
          </Link>
          <button
            onClick={handleLogout}
            className="w-full bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2"
          >
            <LogOut className="w-4 h-4" />
            {t('auth.signOut')}
          </button>
        </div>
      </div>

      {/* Main Content */}
      <main className="flex-1 flex flex-col h-full overflow-hidden relative">
        <AnnouncementBanner />
        <Topbar onMenuToggle={() => setIsMobileMenuOpen(true)} />

        <div className="flex-1 overflow-y-auto custom-scrollbar relative">
          {isPending && pathname !== '/business/billing' ? (
            <LicenseGate onActivate={handleActivateLicense} onLogout={handleLogout} />
          ) : (
            <div className="p-4 md:p-6 lg:p-8">
              <Breadcrumb className="mb-6" />
              {children}
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
