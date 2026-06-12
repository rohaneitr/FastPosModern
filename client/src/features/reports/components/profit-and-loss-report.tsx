'use client';

import React, { useState } from 'react';
import { useDateRange } from './date-range-provider';
import { useProfitAndLoss } from '../hooks/use-financial-reports';
import { useCurrency } from '@/lib/currency';
import { Skeleton } from '@/components/ui/skeleton';

export function ProfitAndLossReport() {
  const { startDate, endDate } = useDateRange();
  const { data, isLoading, error, refresh } = useProfitAndLoss(startDate, endDate);
  const { format } = useCurrency();
  const [expandedSection, setExpandedSection] = useState<string | null>('revenue');

  if (isLoading) {
    return (
      <div className="flex flex-col gap-6 animate-in fade-in">
        <Skeleton className="h-32 w-full rounded-xl" />
        <div className="grid grid-cols-2 gap-6">
           <Skeleton className="h-64 w-full rounded-xl" />
           <Skeleton className="h-64 w-full rounded-xl" />
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-8 glass-card border-rose-500/50 rounded-xl text-center">
        <span className="text-4xl block mb-2">⚠️</span>
        <h3 className="text-xl font-bold text-rose-400">Financial Integrity Error</h3>
        <p className="text-text-muted mt-2">{error.response?.data?.message || 'Failed to aggregate ledger entries.'}</p>
        <button onClick={() => refresh()} className="mt-4 bg-surface hover:bg-white/5 border border-border px-4 py-2 rounded-lg text-sm transition-colors">
          Retry Aggregation
        </button>
      </div>
    );
  }

  if (!data) return null;

  const { revenue, cogs, expenses, summary } = data;

  const Section = ({ title, items, total, id, colorClass }: any) => (
    <div className="glass-card rounded-xl border border-border overflow-hidden">
      <div 
        onClick={() => setExpandedSection(expandedSection === id ? null : id)}
        className="p-4 bg-surface/50 flex justify-between items-center cursor-pointer hover:bg-surface transition-colors"
      >
        <h3 className="font-bold text-white">{title}</h3>
        <div className="flex items-center gap-4">
          <span className={`font-mono font-bold ${colorClass}`}>{format(total)}</span>
          <span className="text-text-muted text-sm">{expandedSection === id ? '▼' : '▶'}</span>
        </div>
      </div>
      
      {expandedSection === id && (
        <div className="p-4 flex flex-col gap-2 bg-background/30">
          {items.length === 0 ? (
            <div className="text-sm text-text-muted italic py-2">No entries found for this period.</div>
          ) : (
            items.map((item: any, idx: number) => (
              <div key={idx} className="flex justify-between text-sm py-1 border-b border-border/30 last:border-0">
                <span className="text-text-muted">{item.name}</span>
                <span className="font-mono text-white">{format(item.balance)}</span>
              </div>
            ))
          )}
        </div>
      )}
    </div>
  );

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500">
      
      <div className="glass-card p-6 rounded-xl border border-border grid grid-cols-3 gap-6">
        <div>
          <p className="text-sm text-text-muted uppercase tracking-wider font-bold mb-1">Gross Profit</p>
          <p className="text-3xl font-bold text-white font-mono">{format(summary.gross_profit)}</p>
        </div>
        <div>
          <p className="text-sm text-text-muted uppercase tracking-wider font-bold mb-1">Total Expenses</p>
          <p className="text-3xl font-bold text-rose-400 font-mono">{format(summary.total_expenses)}</p>
        </div>
        <div className="pl-6 border-l border-border">
          <p className="text-sm text-text-muted uppercase tracking-wider font-bold mb-1">Net Income</p>
          <p className={`text-4xl font-bold font-mono ${summary.net_income >= 0 ? 'text-emerald-400' : 'text-rose-500'}`}>
            {format(summary.net_income)}
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="flex flex-col gap-6">
          <Section 
            id="revenue"
            title="Operating Revenue" 
            items={revenue} 
            total={summary.total_revenue} 
            colorClass="text-emerald-400" 
          />
          <Section 
            id="cogs"
            title="Cost of Goods Sold (COGS)" 
            items={cogs} 
            total={summary.total_cogs} 
            colorClass="text-rose-400" 
          />
        </div>
        
        <div className="flex flex-col gap-6">
          <Section 
            id="expenses"
            title="Operating Expenses" 
            items={expenses} 
            total={summary.total_expenses} 
            colorClass="text-rose-400" 
          />
        </div>
      </div>

    </div>
  );
}
