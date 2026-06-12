'use client';

import React from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import { useCurrency } from '@/lib/currency';
import { Trophy } from 'lucide-react';

interface TopProductsListProps {
  products: any[] | undefined;
  isLoading: boolean;
}

const rankStyles = [
  'bg-amber-500/20 text-amber-400',   // Gold
  'bg-slate-400/20 text-slate-300',    // Silver
  'bg-orange-700/20 text-orange-400',  // Bronze
];

export function TopProductsList({ products, isLoading }: TopProductsListProps) {
  const { format } = useCurrency();

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Trophy className="w-5 h-5 text-amber-400" />
          Top Products
        </CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <div className="flex flex-col gap-4">
            {Array.from({ length: 5 }).map((_, i) => (
              <div key={i} className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <Skeleton className="w-7 h-7 rounded-lg" />
                  <Skeleton className="h-4 w-24" />
                </div>
                <Skeleton className="h-4 w-16" />
              </div>
            ))}
          </div>
        ) : !products?.length ? (
          <EmptyState
            icon={<Trophy className="w-8 h-8" />}
            title="No sales data"
            description="Top products will appear after your first sale."
          />
        ) : (
          <div className="flex flex-col gap-1">
            {products.map((p: any, i: number) => (
              <div
                key={i}
                className="flex items-center justify-between py-2.5 px-2 rounded-lg hover:bg-white/5 transition-colors border-b border-border/20 last:border-0"
              >
                <div className="flex items-center gap-3">
                  <span
                    className={`w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold ${
                      rankStyles[i] || 'bg-surface text-text-muted'
                    }`}
                  >
                    {i + 1}
                  </span>
                  <span className="text-white text-sm font-medium truncate max-w-[140px]">
                    {p.name}
                  </span>
                </div>
                <div className="text-right">
                  <p className="text-white text-sm font-bold">{format(parseFloat(p.revenue || 0))}</p>
                  <p className="text-text-muted text-xs">{p.qty_sold} sold</p>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
