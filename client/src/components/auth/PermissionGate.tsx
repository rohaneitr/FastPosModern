'use client';

/**
 * PermissionGate.tsx — Granular RBAC Render Guard
 *
 * Reads permissions from the Zustand useAuthStore and conditionally renders
 * children based on whether the authenticated user holds the required permission(s).
 *
 * Behaviour matrix:
 * ┌──────────────────────────────────────────────────────────────────────────┐
 * │ User has '*' or 'all' in permissions → always renders children           │
 * │ requiredPermission: string   → user must hold that exact permission      │
 * │ requiredPermission: string[] → user must hold ALL listed permissions     │
 * │   (use mode="any" to require at least ONE)                               │
 * │ No match → renders `fallback` prop (default: null)                      │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * SSR safety: Returns null on first render to prevent hydration mismatches.
 * Zustand state is client-only (no persisted store) so it is always empty
 * on the server; rendering null until mount is the correct approach.
 *
 * Usage:
 *   <PermissionGate requiredPermission="products.manage">
 *     <CreateProductButton />
 *   </PermissionGate>
 *
 *   <PermissionGate
 *     requiredPermission={['reports.view', 'sales.manage']}
 *     mode="any"
 *     fallback={<p>Insufficient access.</p>}
 *   >
 *     <ReportsDashboard />
 *   </PermissionGate>
 *
 * @version Phase 4 — Frontend Architecture
 */

import React, { useEffect, useState } from 'react';
import { useAuthStore } from '@/store/useAuthStore';

// ── Types ─────────────────────────────────────────────────────────────────────

/**
 * "all"  — the user must hold every permission in the array (AND logic).
 * "any"  — the user must hold at least one permission in the array (OR logic).
 * Ignored when `requiredPermission` is a plain string.
 */
type PermissionMode = 'all' | 'any';

export interface PermissionGateProps {
  /**
   * A single permission string or an array of permission strings.
   * Must match the exact strings seeded into the backend
   * (e.g. "products.manage", "reports.view", "pos.access").
   */
  requiredPermission: string | string[];

  /**
   * Render mode when `requiredPermission` is an array.
   * - "all" (default): user must hold ALL permissions.
   * - "any": user must hold AT LEAST ONE permission.
   */
  mode?: PermissionMode;

  /**
   * Content to render when the permission check fails.
   * Defaults to `null` (renders nothing silently).
   */
  fallback?: React.ReactNode;

  /**
   * The content to render when the user has the required permission(s).
   */
  children: React.ReactNode;
}

// ── Component ─────────────────────────────────────────────────────────────────

/**
 * PermissionGate
 *
 * Wraps UI elements that should only be visible to users with specific
 * granular RBAC permissions. Reads live from the Zustand auth store.
 *
 * Does NOT redirect — it only shows or hides content. Use middleware.ts
 * or AuthGuard for route-level protection. This component is intended for
 * fine-grained UI element visibility (buttons, tabs, form fields).
 */
export function PermissionGate({
  requiredPermission,
  mode = 'all',
  fallback = null,
  children,
}: PermissionGateProps): React.ReactElement | null {
  // ── Hydration guard ──────────────────────────────────────────────────────
  // Zustand's useAuthStore is client-only. During SSR the store is always
  // in its initial state (permissions: []), which would cause every gate to
  // fall through to `fallback`. We suppress the first render to let the
  // client hydrate with the real store state before making visibility decisions.
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  // ── Store subscription ───────────────────────────────────────────────────
  // Subscribe only to `permissions` — avoids re-renders from unrelated state
  // changes (e.g. user profile updates, location_id changes).
  const permissions = useAuthStore((state) => state.permissions);

  // ── SSR: render nothing until client mount ───────────────────────────────
  if (!mounted) return null;

  // ── Permission evaluation ────────────────────────────────────────────────
  const allowed = evaluate(permissions, requiredPermission, mode);

  return allowed
    ? (children as React.ReactElement)
    : (fallback as React.ReactElement | null) ?? null;
}

// ── Pure evaluation logic (testable in isolation) ─────────────────────────────

/**
 * Determines whether the given permission set satisfies the requirement.
 *
 * @param userPermissions  Flat array of permission strings from the auth store.
 * @param required         A single permission string or array of strings.
 * @param mode             "all" (AND) or "any" (OR) — only relevant for arrays.
 * @returns true if access should be granted.
 */
export function evaluate(
  userPermissions: readonly string[],
  required: string | string[],
  mode: PermissionMode,
): boolean {
  // SuperAdmin / BusinessAdmin wildcard bypass
  if (userPermissions.includes('*') || userPermissions.includes('all')) {
    return true;
  }

  if (typeof required === 'string') {
    return userPermissions.includes(required);
  }

  // Array of required permissions
  if (required.length === 0) return true; // empty array → no restriction

  return mode === 'any'
    ? required.some((p) => userPermissions.includes(p))
    : required.every((p) => userPermissions.includes(p));
}

// ── Convenience wrappers ──────────────────────────────────────────────────────

/**
 * PermissionGate.Any — requires at least ONE of the listed permissions.
 * Syntactic sugar; avoids passing mode="any" at every call site.
 *
 * @example
 *   <PermissionGate.Any requiredPermission={['reports.view', 'sales.manage']}>
 *     <ReportTab />
 *   </PermissionGate.Any>
 */
PermissionGate.Any = function PermissionGateAny(
  props: Omit<PermissionGateProps, 'mode'>,
): React.ReactElement | null {
  return <PermissionGate {...props} mode="any" />;
};

/**
 * PermissionGate.All — requires ALL of the listed permissions (default behaviour).
 * Explicit alias for readability when combining multiple strict requirements.
 */
PermissionGate.All = function PermissionGateAll(
  props: Omit<PermissionGateProps, 'mode'>,
): React.ReactElement | null {
  return <PermissionGate {...props} mode="all" />;
};
