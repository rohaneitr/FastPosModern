import React from 'react';
import { cn } from '@/lib/utils';

export function Skeleton({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn(
        'animate-pulse rounded-lg bg-surface/50',
        className
      )}
      {...props}
    />
  );
}

/** Pre-composed skeleton for KPI stat cards */
export function StatCardSkeleton() {
  return (
    <div className="bg-surface/30 border border-border p-6 rounded-2xl">
      <Skeleton className="h-4 w-24 mb-3" />
      <Skeleton className="h-8 w-32 mb-2" />
      <Skeleton className="h-3 w-20" />
    </div>
  );
}

/** Pre-composed skeleton for table rows */
export function TableRowSkeleton({ columns = 5 }: { columns?: number }) {
  return (
    <tr className="border-b border-border/20">
      {Array.from({ length: columns }).map((_, i) => (
        <td key={i} className="py-4 px-4">
          <Skeleton className="h-4 w-full max-w-[120px]" />
        </td>
      ))}
    </tr>
  );
}

/** Pre-composed skeleton for chart areas */
export function ChartSkeleton() {
  return (
    <div className="h-64 w-full flex items-end gap-1 p-4">
      {Array.from({ length: 7 }).map((_, i) => (
        <Skeleton
          key={i}
          className="flex-1 rounded-t-md"
          style={{ height: `${30 + Math.random() * 60}%` }}
        />
      ))}
    </div>
  );
}
