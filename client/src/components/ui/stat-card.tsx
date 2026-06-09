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
  accentColor?: string;
  className?: string;
}

export function StatCard({ label, value, icon, trend, accentColor, className }: StatCardProps) {
  const borderColorClass = accentColor
    ? `border-${accentColor}-500/20 hover:border-${accentColor}-500/50`
    : 'border-border hover:border-primary/50';

  const labelColorClass = accentColor ? `text-${accentColor}-400` : 'text-text-muted';

  return (
    <div
      className={cn(
        'bg-surface/30 border p-6 rounded-2xl relative overflow-hidden group transition-colors',
        borderColorClass,
        className
      )}
    >
      {icon && (
        <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">
          {icon}
        </div>
      )}
      <p className={cn('font-medium text-sm mb-1', labelColorClass)}>{label}</p>
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
