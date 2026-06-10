'use client';

import React, { useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { useDashboardKPI } from '@/lib/queries/use-dashboard-kpi';
import { Button } from '@/components/ui/button';
import { ErrorBoundary } from '@/components/ui/error-boundary';
import { DashboardKPIRow } from '@/components/dashboard/kpi-row';
import { SalesTrendChart } from '@/components/dashboard/sales-trend-chart';
import { TopProductsList } from '@/components/dashboard/top-products-list';
import { QuickActionsPanel } from '@/components/dashboard/quick-actions-panel';
import { RecentTransactionsTable } from '@/components/dashboard/recent-transactions-table';
import { LowStockAlerts } from '@/components/dashboard/low-stock-alerts';
import { EODPrintTemplate } from '@/components/dashboard/eod-print-template';
import { Printer } from 'lucide-react';
import api from '@/lib/api';

export default function BusinessDashboard() {
  const { t } = useTranslation();
  const { kpi, isLoading } = useDashboardKPI();

  // EOD Report print state (stays local — not worth a SWR hook for a one-shot action)
  const [printingEod, setPrintingEod] = useState(false);
  const [eodData, setEodData] = useState<any>(null);

  const handlePrintZReport = async () => {
    setPrintingEod(true);
    try {
      const res = await api.get('/reports/eod');
      setEodData(res.data);
      setTimeout(() => {
        window.print();
        setTimeout(() => setEodData(null), 500);
      }, 500);
    } catch (err) {

      alert('Failed to load EOD Report');
    } finally {
      setPrintingEod(false);
    }
  };

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
      {/* Page Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-400">
            {t('business.dashboardTitle')}
          </h1>
          <p className="text-text-muted mt-1">{t('business.dashboardDesc')}</p>
        </div>
        <Button
          onClick={handlePrintZReport}
          loading={printingEod}
          variant="secondary"
          icon={<Printer className="w-4 h-4" />}
        >
          Print Z-Report
        </Button>
      </div>

      {/* KPI Row */}
      <ErrorBoundary>
        <DashboardKPIRow kpi={kpi} isLoading={isLoading} />
      </ErrorBoundary>

      {/* Middle Row: Chart + Top Products */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <ErrorBoundary>
          <SalesTrendChart data={kpi?.sales_trend} isLoading={isLoading} />
        </ErrorBoundary>
        <ErrorBoundary>
          <TopProductsList products={kpi?.top_products} isLoading={isLoading} />
        </ErrorBoundary>
      </div>

      {/* Bottom Row: Quick Actions + Recent Transactions */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <ErrorBoundary>
          <QuickActionsPanel />
        </ErrorBoundary>
        <ErrorBoundary>
          <RecentTransactionsTable
            transactions={kpi?.recent_transactions}
            isLoading={isLoading}
          />
        </ErrorBoundary>
      </div>

      {/* Low Stock Alerts (conditionally rendered) */}
      <ErrorBoundary>
        <LowStockAlerts items={kpi?.low_stock_items} />
      </ErrorBoundary>

      {/* Hidden EOD Thermal Print Template */}
      <EODPrintTemplate data={eodData} />
    </div>
  );
}
