'use client';

import React, { useEffect, useState } from 'react';
import { useRouter, usePathname } from 'next/navigation';

// Maps role names to their allowed route prefixes
const ROLE_ROUTE_MAP: Record<string, string[]> = {
  SuperAdmin: ['/superadmin', '/business', '/user'],
  BusinessAdmin: ['/business', '/user'],
  Cashier: ['/user'],
  InventoryManager: ['/user'],
  Accountant: ['/user'],
};

// Maps role names to their default dashboard
const ROLE_HOME_MAP: Record<string, string> = {
  SuperAdmin: '/superadmin',
  BusinessAdmin: '/business',
  Cashier: '/user',
  InventoryManager: '/user',
  Accountant: '/user',
};

export default function DashboardsLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  // null  = not yet checked (avoids false redirect during hydration)
  // false = confirmed unauthenticated → will redirect
  // true  = confirmed authenticated → render children
  const [isAuthenticated, setIsAuthenticated] = useState<boolean | null>(null);

  useEffect(() => {
    const token = localStorage.getItem('fastpos_token');
    const userJson = localStorage.getItem('fastpos_user');

    if (!token || !userJson) {
      localStorage.removeItem('fastpos_token');
      localStorage.removeItem('fastpos_user');
      router.replace('/login');
      return;
    }

    try {
      const parsedUser = JSON.parse(userJson);
      const primaryRole = parsedUser?.roles?.[0]?.name || '';

      // Check if the user's role allows access to this route
      const allowedPrefixes = ROLE_ROUTE_MAP[primaryRole] || [];
      const isAllowed = allowedPrefixes.some(prefix => pathname.startsWith(prefix));

      if (!isAllowed) {
        // Redirect to their own dashboard instead of kicking to login
        const home = ROLE_HOME_MAP[primaryRole] || '/login';
        router.replace(home);
        return;
      }

      setIsAuthenticated(true);
    } catch {
      localStorage.removeItem('fastpos_token');
      localStorage.removeItem('fastpos_user');
      router.replace('/login');
    }
  }, [router, pathname]);

  // Show spinner while the check is still pending (null state)
  if (isAuthenticated !== true) {
    return (
      <div className="flex h-screen w-screen items-center justify-center bg-background">
        <div className="flex flex-col items-center gap-4">
          <div className="w-10 h-10 border-3 border-surface border-t-primary rounded-full animate-spin" />
          <p className="text-text-muted font-medium text-sm animate-pulse">Verifying session...</p>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
