'use client';

/**
 * QueryProvider.tsx — TanStack Query v5 Global Provider
 *
 * Wraps the entire Next.js App Router tree with a QueryClientProvider.
 * Must be a Client Component ('use client') because QueryClient holds
 * in-memory state that cannot live on the server.
 *
 * ERP-Optimized Defaults Rationale:
 * ┌───────────────────┬──────────┬────────────────────────────────────────────┐
 * │ Option            │ Value    │ Reason                                     │
 * ├───────────────────┼──────────┼────────────────────────────────────────────┤
 * │ staleTime         │ 5 min    │ ERP data (products, users, settings) is    │
 * │                   │          │ written rarely. 5 min prevents thundering  │
 * │                   │          │ herd on tab switches without stale UX.     │
 * ├───────────────────┼──────────┼────────────────────────────────────────────┤
 * │ gcTime            │ 10 min   │ Keep data in memory for instant re-renders │
 * │                   │          │ even after a component unmounts.           │
 * ├───────────────────┼──────────┼────────────────────────────────────────────┤
 * │ refetchOnWindow   │ false    │ Prevents API spam when cashiers switch     │
 * │ Focus             │          │ browser tabs during a billing/register     │
 * │                   │          │ operation. All mutations invalidate keys   │
 * │                   │          │ explicitly instead.                        │
 * ├───────────────────┼──────────┼────────────────────────────────────────────┤
 * │ refetchOnReconnect│ true     │ Network drop recovery is important in POS  │
 * │                   │          │ terminal environments.                     │
 * ├───────────────────┼──────────┼────────────────────────────────────────────┤
 * │ retry             │ 1        │ Fast failure: show error state quickly.    │
 * │                   │          │ Excessive retries mask real outages.       │
 * ├───────────────────┼──────────┼────────────────────────────────────────────┤
 * │ retryDelay        │ 1000ms   │ Fixed 1s gap (no exponential backoff for  │
 * │                   │          │ ERP — operators need immediate feedback).  │
 * └───────────────────┴──────────┴────────────────────────────────────────────┘
 *
 * HOW TO USE:
 * This provider is consumed by src/app/providers.tsx (see that file).
 * It is NOT imported directly into layout.tsx.
 *
 * @version Phase 4 — Frontend Architecture
 */

import React, { useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

// ── QueryClient factory ────────────────────────────────────────────────────────
// Created inside a useState initialiser so each Next.js request in SSR mode
// gets its own isolated QueryClient (prevents cross-request cache pollution).
// On the client, useState ensures it is created only once per mount.

function makeQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        // Data is considered fresh for 5 minutes — no background refetch during this window
        staleTime: 5 * 60 * 1000,

        // Keep unused data in memory cache for 10 minutes
        gcTime: 10 * 60 * 1000,

        // Do NOT refetch when the user switches browser tabs
        refetchOnWindowFocus: false,

        // DO refetch when the network reconnects (critical for POS terminals)
        refetchOnReconnect: true,

        // Retry once on failure, then surface the error immediately
        retry: 1,
        retryDelay: 1000,

        // Throw errors to the nearest React Error Boundary by default.
        // Individual hooks can override this with throwOnError: false.
        throwOnError: false,
      },
      mutations: {
        // Never retry mutations — double-submitting a checkout/payment is dangerous
        retry: false,

        // Surface mutation errors to the nearest Error Boundary by default
        throwOnError: false,
      },
    },
  });
}

// ── Provider Component ────────────────────────────────────────────────────────

interface QueryProviderProps {
  children: React.ReactNode;
}

/**
 * QueryProvider
 *
 * Wrap this around your app in src/app/providers.tsx.
 * Uses a stable QueryClient instance via useState.
 */
export function QueryProvider({ children }: QueryProviderProps) {
  // useState with an initialiser ensures the QueryClient is:
  //  - Created once on the client (not recreated on every render)
  //  - Created fresh per SSR request (not shared across requests)
  const [queryClient] = useState<QueryClient>(makeQueryClient);

  return (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  );
}
