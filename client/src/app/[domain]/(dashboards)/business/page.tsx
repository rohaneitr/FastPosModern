'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer } from 'recharts';
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
      console.error(err);
      alert('Failed to load EOD Report');
    } finally {
      setPrintingEod(false);
    }
  };

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-500">
            {t('business.dashboardTitle')}
          </h1>
          <p className="text-text-muted mt-1">{t('business.dashboardDesc')}</p>
        </div>
        <button 
          onClick={handlePrintZReport} 
          disabled={printingEod}
          className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-xl flex items-center gap-2 transition-colors disabled:opacity-50"
        >
          {printingEod ? 'Loading...' : '🖨️ Print Z-Report'}
        </button>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-primary/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">💰</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('business.todaysSales')}</p>
          <h2 className="text-3xl font-bold text-white">{loading ? '—' : format(kpi?.today_sales || 0)}</h2>
          {!loading && kpi?.sales_change_pct !== 0 && (
            <p className={`${kpi?.sales_change_pct >= 0 ? 'text-success' : 'text-danger'} text-xs font-bold mt-2 flex items-center gap-1`}>
              {kpi?.sales_change_pct >= 0 ? '↑' : '↓'}
              {Math.abs(kpi?.sales_change_pct || 0)}% vs Yesterday
            </p>
          )}
        </div>

        <div className="bg-surface/30 border border-emerald-500/20 p-6 rounded-2xl relative overflow-hidden group hover:border-emerald-500/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">💎</div>
          <p className="text-emerald-400 font-medium text-sm mb-1">True Net Profit</p>
          <h2 className="text-3xl font-bold text-white">{loading ? '—' : format(kpi?.net_profit || 0)}</h2>
          <p className="text-text-muted text-xs font-medium mt-2">Revenue - COGS - Expenses</p>
        </div>

        <div className="bg-surface/30 border border-orange-500/20 p-6 rounded-2xl relative overflow-hidden group hover:border-orange-500/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">📉</div>
          <p className="text-orange-400 font-medium text-sm mb-1">Total Expenses</p>
          <h2 className="text-3xl font-bold text-white">{loading ? '—' : format(kpi?.total_expenses_this_month || 0)}</h2>
          <p className="text-text-muted text-xs font-medium mt-2">Operating Expenses This Month</p>
        </div>

        <div className="bg-surface/30 border border-rose-500/20 p-6 rounded-2xl relative overflow-hidden group hover:border-rose-500/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">⏳</div>
          <p className="text-rose-400 font-medium text-sm mb-1">Outstanding Dues</p>
          <h2 className="text-3xl font-bold text-white">{loading ? '—' : format(kpi?.total_dues || 0)}</h2>
          <p className="text-text-muted text-xs font-medium mt-2">Unpaid Customer Credits</p>
        </div>

        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-primary/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">📦</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('business.productsSoldToday')}</p>
          <h2 className="text-3xl font-bold text-white">{loading ? '—' : kpi?.products_sold || 0}</h2>
          {!loading && kpi?.products_sold_change_pct !== 0 && (
            <p className={`${kpi?.products_sold_change_pct >= 0 ? 'text-success' : 'text-danger'} text-xs font-bold mt-2 flex items-center gap-1`}>
              {kpi?.products_sold_change_pct >= 0 ? '↑' : '↓'} {Math.abs(kpi?.products_sold_change_pct || 0)}% {t('business.vsYesterday')}
            </p>
          )}
        </div>

        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-primary/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">👥</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('business.totalCustomers')}</p>
          <h2 className="text-3xl font-bold text-white">{loading ? '—' : (kpi?.total_customers || 0).toLocaleString()}</h2>
          <p className="text-text-muted text-xs font-medium mt-2">{kpi?.new_customers_this_week || 0} {t('business.newThisWeek')}</p>
        </div>

        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-warning/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">⚠️</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('business.lowStockAlerts')}</p>
          <h2 className={`text-3xl font-bold ${(kpi?.low_stock_count || 0) > 0 ? 'text-warning' : 'text-success'}`}>
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
            <div className="h-64 flex items-center justify-center text-text-muted">Loading chart...</div>
          ) : !kpi?.sales_trend?.length ? (
            <div className="h-64 flex items-center justify-center text-text-muted">No sales data yet</div>
          ) : (
            <div className="h-64 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={kpi.sales_trend} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                  <defs>
                    <linearGradient id="colorSales" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.4}/>
                      <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}/>
                    </linearGradient>
                  </defs>
                  <XAxis dataKey="date" tickFormatter={(val) => new Date(val).toLocaleDateString('en', { weekday: 'short' })} stroke="#64748b" fontSize={12} tickLine={false} axisLine={false} />
                  <YAxis stroke="#64748b" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(val) => format(val)} />
                  <Tooltip
                    contentStyle={{ backgroundColor: 'rgba(15, 23, 42, 0.9)', borderColor: 'rgba(255, 255, 255, 0.1)', borderRadius: '8px' }}
                    itemStyle={{ color: '#fff' }}
                    labelStyle={{ color: '#94a3b8' }}
                  />
                  <Area type="monotone" dataKey="total" stroke="#3b82f6" strokeWidth={3} fillOpacity={1} fill="url(#colorSales)" />
                </AreaChart>
              </ResponsiveContainer>
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
            <div className="w-full overflow-x-auto">
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

      {/* Low Stock Alerts Table */}
      {(kpi?.low_stock_items?.length > 0) && (
        <div className="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-6 mt-2">
          <h3 className="text-xl font-bold text-rose-500 mb-4 flex items-center gap-2">⚠️ Low Stock Alerts</h3>
          <div className="overflow-x-auto">
            <div className="w-full overflow-x-auto">
<table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-rose-500/30 text-rose-400 text-sm">
                  <th className="pb-3 font-medium">Product Name</th>
                  <th className="pb-3 font-medium">SKU</th>
                  <th className="pb-3 font-medium text-right">Available Stock</th>
                </tr>
              </thead>
              <tbody className="text-sm">
                {loading ? (
                  <tr><td colSpan={3} className="py-8 text-center text-text-muted">Loading...</td></tr>
                ) : (
                  kpi.low_stock_items.map((item: any, i: number) => (
                    <tr key={i} className="border-b border-border/20 last:border-0 hover:bg-rose-500/5 transition-colors">
                      <td className="py-4 text-white font-medium">{item.name}</td>
                      <td className="py-4 text-text-muted font-mono">{item.sku}</td>
                      <td className="py-4 text-rose-500 font-bold text-right text-lg">{item.qty_available}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
</div>
          </div>
          <div className="mt-4 text-right">
             <Link href="/business/inventory" className="text-sm text-rose-400 hover:text-rose-300 font-bold underline">Manage Inventory →</Link>
          </div>
        </div>
      )}

      
      {/* Hidden EOD Thermal Print Area */}
      {eodData && (
        <div className="hidden print:block absolute top-0 left-0 bg-white text-black z-[9999] p-2 text-xs font-mono w-full min-h-screen">
          <div className="mx-auto bg-white text-black relative z-10 w-[80mm]">
            <div className="text-center mb-4">
              <h1 className="font-bold text-xl uppercase tracking-wider">END OF DAY REPORT</h1>
              <h2 className="font-bold text-md mt-1">Z-REPORT</h2>
              <p className="mt-2 text-sm">Date: {eodData.date}</p>
              <p className="text-sm">Printed: {new Date().toLocaleString()}</p>
              <p className="text-xs mt-1 border-b-2 border-black pb-2 border-dashed">Cashier: {JSON.parse(localStorage.getItem('fastpos_user') || '{}')?.name || 'Admin'}</p>
            </div>

            <div className="w-full mb-4">
              <div className="flex justify-between font-bold mb-1 border-b border-dashed border-gray-400 pb-1">
                <span>GROSS SALES</span>
                <span>{format(eodData.total_sales)}</span>
              </div>
              <div className="flex justify-between font-bold mb-1 border-b border-dashed border-gray-400 pb-1">
                <span>TOTAL RETURNS</span>
                <span>{format(eodData.total_returns)}</span>
              </div>
              <div className="flex justify-between font-bold text-lg mb-3 border-b-2 border-black pb-2 mt-2">
                <span>NET SALES</span>
                <span>{format(eodData.net_sales)}</span>
              </div>

              <h3 className="font-bold text-center border-b border-black pb-1 mb-2">COLLECTED TENDERS</h3>
              
              <div className="flex justify-between mb-1">
                <span>CASH</span>
                <span>{format(eodData.collected.cash)}</span>
              </div>
              <div className="flex justify-between mb-1">
                <span>MOBILE BANKING</span>
                <span>{format(eodData.collected.mobile)}</span>
              </div>
              <div className="flex justify-between mb-1">
                <span>CARD / BANK</span>
                <span>{format(eodData.collected.card)}</span>
              </div>
              <div className="flex justify-between text-red-500 mb-1 border-b border-dashed border-gray-400 pb-1">
                <span>(-) OP EXPENSES</span>
                <span>{format(eodData.cash_expenses || 0)}</span>
              </div>
              <div className="flex justify-between font-bold text-xl mt-2 border-b-2 border-black pb-2">
                <span>TOTAL DEPOSIT</span>
                <span>{format(eodData.collected.total)}</span>
              </div>
            </div>

            <div className="text-center mt-8 pt-8">
              <div className="w-48 border-t-2 border-black mx-auto pt-1 text-sm font-bold">
                Manager Signature
              </div>
            </div>
          </div>
          <style dangerouslySetInnerHTML={{__html: `
            @media print {
              body { background-color: white !important; color: black !important; }
              body * { visibility: hidden; }
              .print\\:block, .print\\:block * { visibility: visible; }
              .print\\:block { position: absolute; left: 0; top: 0; width: 100% !important; background: white !important; color: black !important; padding: 0 !important; margin: 0 !important; }
              @page { size: 80mm auto; margin: 0; }
            }
          `}} />
        </div>
      )}
    </div>
  );
}
