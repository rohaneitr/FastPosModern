import React from 'react';
import { cn } from '@/lib/utils';

export interface EmptyStateProps {
  icon?: React.ReactNode;
  title: string;
  description?: string;
  action?: React.ReactNode;
  className?: string;
}

export function EmptyState({ icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div className={cn('flex flex-col items-center justify-center py-16 px-4 text-center', className)}>
      {icon && <div className="text-5xl mb-4 opacity-30">{icon}</div>}
      <h3 className="text-lg font-bold text-white mb-1">{title}</h3>
      {description && <p className="text-sm text-text-muted mb-6 max-w-sm">{description}</p>}
      {action}
    </div>
  );
}
