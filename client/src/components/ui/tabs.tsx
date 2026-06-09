'use client';

import React, { useState } from 'react';
import { cn } from '@/lib/utils';

interface TabsProps {
  defaultValue: string;
  children: React.ReactNode;
  className?: string;
}

interface TabsContextType {
  activeTab: string;
  setActiveTab: (tab: string) => void;
}

const TabsContext = React.createContext<TabsContextType | null>(null);

function useTabs() {
  const context = React.useContext(TabsContext);
  if (!context) throw new Error('Tab components must be used within <Tabs>');
  return context;
}

export function Tabs({ defaultValue, children, className }: TabsProps) {
  const [activeTab, setActiveTab] = useState(defaultValue);

  return (
    <TabsContext.Provider value={{ activeTab, setActiveTab }}>
      <div className={cn('flex flex-col gap-6', className)}>{children}</div>
    </TabsContext.Provider>
  );
}

export function TabsList({ className, children }: { className?: string; children: React.ReactNode }) {
  return (
    <div
      className={cn(
        'bg-surface/30 border border-border rounded-xl p-1.5 inline-flex self-start gap-1 flex-wrap',
        className
      )}
    >
      {children}
    </div>
  );
}

export function TabTrigger({
  value,
  children,
  className,
  accentColor = 'emerald',
}: {
  value: string;
  children: React.ReactNode;
  className?: string;
  accentColor?: string;
}) {
  const { activeTab, setActiveTab } = useTabs();
  const isActive = activeTab === value;

  const activeStyles: Record<string, string> = {
    emerald: 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20',
    blue: 'bg-blue-500 text-white shadow-md shadow-blue-500/20',
    indigo: 'bg-indigo-500 text-white shadow-md shadow-indigo-500/20',
    rose: 'bg-rose-500 text-white shadow-md shadow-rose-500/20',
  };

  return (
    <button
      onClick={() => setActiveTab(value)}
      className={cn(
        'px-5 py-2 rounded-lg text-sm font-bold transition-all',
        isActive
          ? activeStyles[accentColor] || activeStyles.emerald
          : 'text-text-muted hover:text-white hover:bg-white/5',
        className
      )}
    >
      {children}
    </button>
  );
}

export function TabContent({
  value,
  children,
  className,
}: {
  value: string;
  children: React.ReactNode;
  className?: string;
}) {
  const { activeTab } = useTabs();
  if (activeTab !== value) return null;

  return (
    <div className={cn('animate-in slide-in-from-right-4 duration-300', className)}>
      {children}
    </div>
  );
}
