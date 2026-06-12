'use client';

import React from 'react';

interface ModuleGuardProps {
  /** Module slugs to check against user's active_modules */
  slugs: string[];
  /** The user's active modules array (passed from layout context) */
  activeModules: string[] | null;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

/**
 * Subscription-gated module guard.
 * If activeModules is null (still loading), shows children by default.
 * If the user's plan doesn't include ANY of the required slugs, renders fallback.
 */
export function ModuleGuard({ slugs, activeModules, children, fallback = null }: ModuleGuardProps) {
  // Still loading features — show content by default to avoid flash
  if (activeModules === null) return <>{children}</>;
  if (!Array.isArray(activeModules)) return <>{fallback}</>;

  const hasAccess = slugs.some(slug =>
    activeModules.some(mod => mod.toLowerCase().includes(slug.toLowerCase()))
  );

  if (!hasAccess) return <>{fallback}</>;
  return <>{children}</>;
}
