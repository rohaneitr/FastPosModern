'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useTranslation } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';
import api from '@/lib/api';

export default function BusinessDashboard() {
  const { t } = useTranslation();
  const { format } = useCurrency();

  const [kpi, setKpi] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/reports/dashboard')
      .then(res => setKpi(res.data))
      .catch(() => setKpi(null))
      .finally(() => setLoading(false));
  }, []);

  // Get max value in sales trend for chart scaling
  const trendMax = kpi?.sales_trend?.length
    ? Math.max(...kpi.sales_trend.map((d: any) => parseFloat(d.total) || 0), 1)
    : 1;

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
      <div>
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500">
          {t('business.dashboardTitle')}
        </h1>
        <p className="text-text-muted mt-1">{t('business.dashboardDesc')}</p>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-primary/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">💰</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('business.todaysSales')}</p>
          <h2 className="text-4xl font-bold text-white">{loading ? '—' : format(kpi?.today_sales || 0)}</h2>
          {!loading && kpi?.sales_change_pct !== 0 && (
            <p className={`${kpi?.sales_change_pct >= 0 ? 'text-success' : 'text-danger'} text-xs font-bold mt-2 flex items-center gap-1`}>
              {kpi?.sales_change_pct >= 0 ? (
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
              ) : (
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
              )}
              {Math.abs(kpi?.sales_change_pct || 0)}% {t('business.vsYesterday')}
            </p>
          )}
        </div>

        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-primary/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">📦</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('business.productsSoldToday')}</p>
          <h2 className="text-4xl font-bold text-white">{loading ? '—' : kpi?.products_sold || 0}</h2>
          {!loading && kpi?.products_sold_change_pct !== 0 && (
            <p className={`${kpi?.products_sold_change_pct >= 0 ? 'text-success' : 'text-danger'} text-xs font-bold mt-2 flex items-center gap-1`}>
              {kpi?.products_sold_change_pct >= 0 ? '↑' : '↓'} {Math.abs(kpi?.products_sold_change_pct || 0)}% {t('business.vsYesterday')}
            </p>
          )}
        </div>

        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-primary/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">👥</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('business.totalCustomers')}</p>
          <h2 className="text-4xl font-bold text-white">{loading ? '—' : (kpi?.total_customers || 0).toLocaleString()}</h2>
          <p className="text-text-muted text-xs font-medium mt-2">{kpi?.new_customers_this_week || 0} {t('business.newThisWeek')}</p>
        </div>

        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-warning/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">⚠️</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('business.lowStockAlerts')}</p>
          <h2 className={`text-4xl font-bold ${(kpi?.low_stock_count || 0) > 0 ? 'text-warning' : 'text-success'}`}>
            {loading ? '—' : kpi?.low_stock_count || 0}
          </h2>
          <p className={`${(kpi?.low_stock_count || 0) > 0 ? 'text-warning' : 'text-success'} text-xs font-medium mt-2`}>
            {(kpi?.low_stock_count || 0) > 0 ? t('business.requiresAttention') : 'All stocked'}
          </p>
        </div>
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {/* Sales Trend Chart */}
        <div className="bg-surface/30 border border-border rounded-2xl p-6 lg:col-span-2">
          <h3 className="text-xl font-bold text-white mb-4">📈 7-Day Sales Trend</h3>
          {loading ? (
            <div className="h-48 flex items-center justify-center text-text-muted">Loading chart...</div>
          ) : !kpi?.sales_trend?.length ? (
            <div className="h-48 flex items-center justify-center text-text-muted">No sales data yet</div>
          ) : (
            <div className="flex items-end gap-2 h-48">
              {kpi.sales_trend.map((day: any, i: number) => {
                const height = Math.max((parseFloat(day.total) / trendMax) * 100, 4);
                const dayLabel = new Date(day.date).toLocaleDateString('en', { weekday: 'short' });
                return (
                  <div key={i} className="flex-1 flex flex-col items-center gap-2 group">
                    <span className="text-xs text-text-muted opacity-0 group-hover:opacity-100 transition-opacity">
                      {format(parseFloat(day.total))}
                    </span>
                    <div
                      className="w-full bg-gradient-to-t from-primary/60 to-primary rounded-t-lg transition-all hover:from-primary/80 hover:to-indigo-400"
                      style={{ height: `${height}%`, minHeight: 6 }}
                    />
                    <span className="text-xs text-text-muted">{dayLabel}</span>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Top Products */}
        <div className="bg-surface/30 border border-border rounded-2xl p-6">
          <h3 className="text-xl font-bold text-white mb-4">🏆 Top Products</h3>
          {!kpi?.top_products?.length ? (
            <p className="text-text-muted text-sm">No sales data yet</p>
          ) : (
            <div className="flex flex-col gap-3">
              {kpi.top_products.map((p: any, i: number) => (
                <div key={i} className="flex items-center justify-between py-2 border-b border-border/30 last:border-0">
                  <div className="flex items-center gap-3">
                    <span className={`w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold ${
                      i === 0 ? 'bg-amber-500/20 text-amber-400' : i === 1 ? 'bg-slate-400/20 text-slate-300' : 'bg-orange-700/20 text-orange-400'
                    }`}>{i + 1}</span>
                    <span className="text-white text-sm font-medium truncate max-w-[140px]">{p.name}</span>
                  </div>
                  <div className="text-right">
                    <p className="text-white text-sm font-bold">{format(parseFloat(p.revenue || 0))}</p>
                    <p className="text-text-muted text-xs">{p.qty_sold} sold</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Bottom Row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {/* Quick Actions */}
        <div className="bg-surface/30 border border-border rounded-2xl p-6 lg:col-span-1">
          <h3 className="text-xl font-bold text-white mb-4">{t('business.quickActions')}</h3>
          <div className="flex flex-col gap-3">
            <Link href="/user/pos" className="w-full flex items-center gap-3 bg-primary/10 hover:bg-primary/20 text-primary p-4 rounded-xl transition-colors font-medium border border-primary/20">
              <span className="text-2xl">🖥️</span> {t('nav.openPOS')}
            </Link>
            <Link href="/business/products" className="w-full flex items-center gap-3 bg-surface hover:bg-surface/80 text-white p-4 rounded-xl transition-colors font-medium border border-border">
              <span className="text-2xl">➕</span> {t('business.addNewProduct')}
            </Link>
            <Link href="/business/inventory" className="w-full flex items-center gap-3 bg-surface hover:bg-surface/80 text-white p-4 rounded-xl transition-colors font-medium border border-border">
              <span className="text-2xl">📋</span> {t('business.receiveInventory')}
            </Link>
            <Link href="/business/reports" className="w-full flex items-center gap-3 bg-surface hover:bg-surface/80 text-white p-4 rounded-xl transition-colors font-medium border border-border">
              <span className="text-2xl">📊</span> {t('business.viewSalesReport')}
            </Link>
          </div>
        </div>

        {/* Recent Transactions */}
        <div className="bg-surface/30 border border-border rounded-2xl p-6 lg:col-span-2">
          <div className="flex justify-between items-center mb-6">
            <h3 className="text-xl font-bold text-white">{t('business.recentTransactions')}</h3>
            <Link href="/business/sales" className="text-sm text-primary hover:underline font-medium">{t('business.viewAll')}</Link>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-border/50 text-text-muted text-sm">
                  <th className="pb-3 font-medium">{t('business.invoiceId')}</th>
                  <th className="pb-3 font-medium">{t('common.time')}</th>
                  <th className="pb-3 font-medium">{t('business.cashier')}</th>
                  <th className="pb-3 font-medium text-right">{t('common.total')}</th>
                  <th className="pb-3 font-medium text-center">{t('common.status')}</th>
                </tr>
              </thead>
              <tbody className="text-sm">
                {loading ? (
                  <tr><td colSpan={5} className="py-8 text-center text-text-muted">Loading...</td></tr>
                ) : !kpi?.recent_transactions?.length ? (
                  <tr><td colSpan={5} className="py-8 text-center text-text-muted">No transactions yet</td></tr>
                ) : (
                  kpi.recent_transactions.map((tx: any, i: number) => (
                    <tr key={i} className="border-b border-border/20 last:border-0 hover:bg-surface/30 transition-colors">
                      <td className="py-4 text-white font-medium">{tx.invoice_no}</td>
                      <td className="py-4 text-text-muted">{new Date(tx.transaction_date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</td>
                      <td className="py-4 text-text-muted">{tx.cashier_name?.trim() || '—'}</td>
                      <td className="py-4 text-white font-medium text-right">{format(parseFloat(tx.final_total))}</td>
                      <td className="py-4 text-center">
                        <span className={`px-2 py-1 rounded text-xs font-bold ${tx.status === 'final' ? 'bg-success/20 text-success' : 'bg-rose-500/20 text-rose-500'}`}>
                          {tx.status === 'final' ? t('business.completed') : tx.status}
                        </span>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
