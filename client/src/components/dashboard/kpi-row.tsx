'use client';

import React from 'react';
import { StatCard } from '@/components/ui/stat-card';
import { StatCardSkeleton } from '@/components/ui/skeleton';
import { useTranslation } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';
import {
  DollarSign, Gem, TrendingDown, Clock, Package, Users, AlertTriangle,
} from 'lucide-react';

interface KPIRowProps {
  kpi: any;
  isLoading: boolean;
}

export function DashboardKPIRow({ kpi, isLoading }: KPIRowProps) {
  const { t } = useTranslation();
  const { format } = useCurrency();

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {Array.from({ length: 7 }).map((_, i) => (
          <StatCardSkeleton key={i} />
        ))}
      </div>
    );
  }

  const lowStockCount = kpi?.low_stock_count || 0;

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <StatCard
        label={t('business.todaysSales')}
        value={format(kpi?.today_sales || 0)}
        icon={<DollarSign className="w-8 h-8" />}
        trend={kpi?.sales_change_pct !== 0 ? { value: kpi?.sales_change_pct || 0 } : undefined}
        className="border-emerald-500/20 hover:border-emerald-500/40"
      />

      <StatCard
        label="True Net Profit"
        value={format(kpi?.net_profit || 0)}
        icon={<Gem className="w-8 h-8" />}
        className="border-emerald-500/20 hover:border-emerald-500/40"
      />

      <StatCard
        label="Total Expenses"
        value={format(kpi?.total_expenses_this_month || 0)}
        icon={<TrendingDown className="w-8 h-8" />}
        className="border-orange-500/20 hover:border-orange-500/40"
      />

      <StatCard
        label="Outstanding Dues"
        value={format(kpi?.total_dues || 0)}
        icon={<Clock className="w-8 h-8" />}
        className="border-rose-500/20 hover:border-rose-500/40"
      />

      <StatCard
        label={t('business.productsSoldToday')}
        value={kpi?.products_sold || 0}
        icon={<Package className="w-8 h-8" />}
        trend={kpi?.products_sold_change_pct !== 0 ? { value: kpi?.products_sold_change_pct || 0, label: t('business.vsYesterday') } : undefined}
      />

      <StatCard
        label={t('business.totalCustomers')}
        value={(kpi?.total_customers || 0).toLocaleString()}
        icon={<Users className="w-8 h-8" />}
      />

      <StatCard
        label={t('business.lowStockAlerts')}
        value={lowStockCount}
        icon={<AlertTriangle className="w-8 h-8" />}
        className={lowStockCount > 0 ? 'border-amber-500/20 hover:border-amber-500/40' : 'border-emerald-500/20'}
      />
    </div>
  );
}
