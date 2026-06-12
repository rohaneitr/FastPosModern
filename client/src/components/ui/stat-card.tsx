import React from 'react';
import { cn } from '@/lib/utils';

export interface StatCardProps {
  label: string;
  value: string | number;
  icon?: React.ReactNode;
  trend?: {
    value: number;
    label?: string;
  };
  className?: string;
}

export function StatCard({ label, value, icon, trend, className }: StatCardProps) {
  return (
    <div
      className={cn(
        'bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group transition-colors hover:border-emerald-500/30',
        className
      )}
    >
      {icon && (
        <div className="absolute top-3 right-3 opacity-10 group-hover:opacity-20 group-hover:scale-110 transition-all text-white">
          {icon}
        </div>
      )}
      <p className="text-text-muted font-medium text-sm mb-1">{label}</p>
      <h2 className="text-3xl font-bold text-white">{value}</h2>
      {trend && (
        <p
          className={cn(
            'text-xs font-bold mt-2 flex items-center gap-1',
            trend.value >= 0 ? 'text-emerald-400' : 'text-red-400'
          )}
        >
          {trend.value >= 0 ? '↑' : '↓'} {Math.abs(trend.value)}%{' '}
          {trend.label || 'vs Yesterday'}
        </p>
      )}
    </div>
  );
}
