'use client';

import React, { useState } from 'react';
import { cn } from '@/lib/utils';
import { PanelLeftClose, PanelLeftOpen } from 'lucide-react';
import { SidebarItem } from './sidebar-item';
import type { SidebarMenuItem } from './sidebar-config';

export interface SidebarProps {
  items: SidebarMenuItem[];
  activeModules: string[] | null;
  isCashier: boolean;
  tenantName?: string;
  tenantLogo?: string | null;
  className?: string;
  /** Called when a mobile menu item is clicked (to close the overlay) */
  onNavigate?: () => void;
}

export function Sidebar({
  items,
  activeModules,
  isCashier,
  tenantName,
  tenantLogo,
  className,
  onNavigate,
}: SidebarProps) {
  const [collapsed, setCollapsed] = useState(false);

  const hasModule = (slugs?: string[]) => {
    if (!slugs || slugs.length === 0) return true;
    if (activeModules === null) return true; // Still loading
    if (!Array.isArray(activeModules)) return false;
    return slugs.some(slug =>
      activeModules.some(mod => mod.toLowerCase().includes(slug.toLowerCase()))
    );
  };

  const visibleItems = items.filter(item => {
    if (item.adminOnly && isCashier) return false;
    if (!hasModule(item.moduleAccess)) return false;
    return true;
  });

  // Group items by section
  let currentSection = '';

  return (
    <aside
      className={cn(
        'flex flex-col h-full bg-background/95 backdrop-blur-xl border-r border-border transition-all duration-300',
        collapsed ? 'w-[68px]' : 'w-64',
        className
      )}
    >
      {/* Header */}
      <div className={cn('flex items-center gap-3 p-4 border-b border-border/50', collapsed && 'justify-center')}>
        {tenantLogo ? (
          <img src={tenantLogo} alt={tenantName || 'Logo'} className="w-8 h-8 rounded-lg object-contain" />
        ) : (
          <div className="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 font-bold text-sm shrink-0">
            {(tenantName || 'F')[0].toUpperCase()}
          </div>
        )}
        {!collapsed && (
          <div className="flex-1 min-w-0">
            <p className="text-sm font-bold text-white truncate">{tenantName || 'FastPOS'}</p>
            <p className="text-[10px] text-text-muted uppercase tracking-wider">Enterprise ERP</p>
          </div>
        )}
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto custom-scrollbar px-2 py-3 flex flex-col gap-0.5">
        {visibleItems.map((item, i) => {
          let sectionHeader = null;
          if (item.section && item.section !== currentSection) {
            currentSection = item.section;
            sectionHeader = !collapsed ? (
              <p key={`section-${item.section}`} className="text-[10px] font-bold text-text-muted/60 uppercase tracking-widest px-3 pt-4 pb-1.5">
                {item.section}
              </p>
            ) : (
              <div key={`section-${item.section}`} className="border-t border-border/30 my-2 mx-2" />
            );
          }

          return (
            <React.Fragment key={item.path + i}>
              {sectionHeader}
              <SidebarItem
                name={item.name}
                path={item.path}
                icon={item.icon}
                collapsed={collapsed}
                badge={item.badge}
                onClick={onNavigate}
              />
            </React.Fragment>
          );
        })}
      </nav>

      {/* Collapse Toggle */}
      <div className="p-2 border-t border-border/50 hidden lg:block">
        <button
          onClick={() => setCollapsed(!collapsed)}
          className="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-text-muted hover:text-white hover:bg-white/5 transition-colors text-xs"
          title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
          {collapsed ? <PanelLeftOpen className="w-4 h-4" /> : <PanelLeftClose className="w-4 h-4" />}
          {!collapsed && <span className="font-medium">Collapse</span>}
        </button>
      </div>
    </aside>
  );
}
