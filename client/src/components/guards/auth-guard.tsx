'use client';

import React, { useEffect, useState } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import api from '@/lib/api';
import { Skeleton } from '@/components/ui/skeleton';

interface AuthGuardProps {
  children: React.ReactNode;
  requiredRoles?: string[];
  onUserResolved?: (user: any) => void;
}

/**
 * Single source of truth for session verification.
 * 1. Reads fastpos_user from localStorage/sessionStorage
 * 2. If missing, attempts API re-hydration via GET /user
 * 3. Renders children or redirects to /login
 * 4. Shows skeleton during loading (NEVER a white screen)
 */
export function AuthGuard({ children, requiredRoles, onUserResolved }: AuthGuardProps) {
  const router = useRouter();
  const pathname = usePathname();
  const [status, setStatus] = useState<'loading' | 'authenticated' | 'unauthenticated'>('loading');

  useEffect(() => {
    let cancelled = false;

    const verify = async () => {
      // Step 1: Check local storage
      const stored = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');
      
      if (stored) {
        try {
          const parsed = JSON.parse(stored);
          if (parsed && parsed.id) {
            // Check role access if required
            if (requiredRoles && requiredRoles.length > 0) {
              const userRoles = (parsed.roles || []).map((r: any) => r.name || r);
              const hasAccess = requiredRoles.some(role => userRoles.includes(role));
              if (!hasAccess) {
                if (!cancelled) {
                  router.replace('/login');
                  setStatus('unauthenticated');
                }
                return;
              }
            }
            
            if (!cancelled) {
              onUserResolved?.(parsed);
              setStatus('authenticated');
            }
            return;
          }
        } catch {
          // Corrupted storage, fall through to API check
        }
      }

      // Step 2: API re-hydration (cookie may be valid even if storage is empty)
      try {
        const res = await api.get('/user');
        const userData = res.data;
        
        if (userData && userData.id) {
          // Persist to storage for future checks
          localStorage.setItem('fastpos_user', JSON.stringify(userData));
          
          if (!cancelled) {
            onUserResolved?.(userData);
            setStatus('authenticated');
          }
          return;
        }
      } catch {
        // API failed — session is truly dead
      }

      // Step 3: No session found
      if (!cancelled) {
        setStatus('unauthenticated');
        router.replace('/login');
      }
    };

    verify();

    return () => {
      cancelled = true;
    };
  }, [router, pathname, requiredRoles, onUserResolved]);

  if (status === 'loading') {
    return <AuthSkeleton />;
  }

  if (status === 'unauthenticated') {
    return <AuthSkeleton />; // Show skeleton while redirect is in flight
  }

  return <>{children}</>;
}

/** Full-page skeleton shown during auth verification */
function AuthSkeleton() {
  return (
    <div className="flex h-screen bg-background">
      {/* Sidebar skeleton */}
      <div className="w-64 border-r border-border p-4 flex flex-col gap-3 shrink-0 hidden lg:flex">
        <Skeleton className="h-10 w-32 mb-6" />
        {Array.from({ length: 8 }).map((_, i) => (
          <Skeleton key={i} className="h-9 w-full rounded-lg" />
        ))}
      </div>
      {/* Content skeleton */}
      <div className="flex-1 p-8 flex flex-col gap-6">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-4 w-72" />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-28 rounded-2xl" />
          ))}
        </div>
        <Skeleton className="h-64 rounded-2xl mt-4" />
      </div>
    </div>
  );
}
