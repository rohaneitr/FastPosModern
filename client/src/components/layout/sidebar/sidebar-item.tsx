'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

export interface SidebarItemProps {
  name: string;
  path: string;
  icon: LucideIcon;
  collapsed?: boolean;
  badge?: string;
  onClick?: () => void;
}

export function SidebarItem({ name, path, icon: Icon, collapsed, badge, onClick }: SidebarItemProps) {
  const pathname = usePathname();
  
  // Active state: exact match for dashboard root, startsWith for subpages
  const isActive = path === '/business' || path === '/superadmin'
    ? pathname.endsWith(path)
    : pathname.startsWith(`/${pathname.split('/')[1]}${path}`);

  return (
    <Link
      href={path}
      onClick={onClick}
      title={collapsed ? name : undefined}
      className={cn(
        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group',
        isActive
          ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
          : 'text-text-muted hover:text-white hover:bg-white/5 border border-transparent',
        collapsed && 'justify-center px-2'
      )}
    >
      <Icon
        className={cn(
          'w-[18px] h-[18px] shrink-0 transition-colors',
          isActive ? 'text-emerald-400' : 'text-text-muted group-hover:text-white'
        )}
      />
      {!collapsed && (
        <>
          <span className="truncate flex-1">{name}</span>
          {badge && (
            <span className="px-1.5 py-0.5 text-[10px] font-bold rounded bg-emerald-500/20 text-emerald-400">
              {badge}
            </span>
          )}
        </>
      )}
    </Link>
  );
}
