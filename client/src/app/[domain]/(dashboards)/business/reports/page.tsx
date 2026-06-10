'use client';

import React, { useState } from 'react';
import { Tabs, TabsList, TabTrigger, TabContent } from '@/components/ui/tabs';
import { DateRangeProvider, useDateRange } from '@/features/reports/components/date-range-provider';
import { ProfitAndLossReport } from '@/features/reports/components/profit-and-loss-report';
import toast from 'react-hot-toast';
import useSWR from 'swr';
import api from '@/lib/api';

const fetcher = (url: string) => api.get(url).then(res => res.data);

function ReportsDashboard() {
  const { startDate, endDate, setStartDate, setEndDate } = useDateRange();
  const [isExporting, setIsExporting] = useState(false);

  // Data fetching
  const { data: salesData, error: salesError } = useSWR(
    `/reports/sales?start_date=${startDate}&end_date=${endDate}`,
    fetcher
  );

  const { data: inventoryData, error: inventoryError } = useSWR(
    `/reports/inventory-valuation`,
    fetcher
  );

  const handleExport = async () => {
    setIsExporting(true);
    const toastId = toast.loading('Generating Sales PDF Report...');
    try {
      const response = await api.get(`/reports/sales/export-pdf?start_date=${startDate}&end_date=${endDate}`, {
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `sales_report_${new Date().getTime()}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      toast.success('PDF Downloaded successfully!', { id: toastId });
    } catch (error) {
      toast.error('Failed to export PDF.', { id: toastId });
    } finally {
      setIsExporting(false);
    }
  };

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">
            Financial Source of Truth
          </h1>
          <p className="text-text-muted mt-1">
            Immutable, double-entry aggregated financial reports.
          </p>
        </div>
        
        <div className="flex items-center gap-3 glass-card p-2 rounded-xl border border-border">
          <input 
            type="date" 
            value={startDate}
            onChange={(e) => setStartDate(e.target.value)}
            className="bg-surface border border-border rounded-lg px-3 py-1.5 text-sm outline-none text-white focus:border-primary"
          />
          <span className="text-text-muted text-sm font-bold">TO</span>
          <input 
            type="date" 
            value={endDate}
            onChange={(e) => setEndDate(e.target.value)}
            className="bg-surface border border-border rounded-lg px-3 py-1.5 text-sm outline-none text-white focus:border-primary"
          />
          <div className="w-px h-6 bg-border mx-1"></div>
          <button 
            onClick={handleExport}
            disabled={isExporting}
            className="bg-primary/20 text-primary hover:bg-primary hover:text-white px-4 py-1.5 rounded-lg text-sm font-bold transition-colors disabled:opacity-50"
          >
            {isExporting ? 'Exporting...' : 'Export PDF'}
          </button>
        </div>
      </div>

      <Tabs defaultValue="pnl">
        <div className="glass-card rounded-xl p-2 inline-flex self-start gap-2 flex-wrap border border-border mb-6">
          <TabsList className="bg-transparent gap-2 p-0 h-auto">
            <TabTrigger value="pnl" className="px-6 py-2.5 rounded-lg text-sm font-bold data-[state=active]:bg-emerald-500 data-[state=active]:text-white data-[state=active]:shadow-md data-[state=active]:shadow-emerald-500/20 data-[state=inactive]:text-text-muted data-[state=inactive]:hover:text-white data-[state=inactive]:hover:bg-white/5 data-[state=inactive]:bg-transparent border-0 transition-all">
              Profit & Loss
            </TabTrigger>
            <TabTrigger value="sales" className="px-6 py-2.5 rounded-lg text-sm font-bold data-[state=active]:bg-emerald-500 data-[state=active]:text-white data-[state=active]:shadow-md data-[state=active]:shadow-emerald-500/20 data-[state=inactive]:text-text-muted data-[state=inactive]:hover:text-white data-[state=inactive]:hover:bg-white/5 data-[state=inactive]:bg-transparent border-0 transition-all">
              Sales Summary
            </TabTrigger>
            <TabTrigger value="inventory" className="px-6 py-2.5 rounded-lg text-sm font-bold data-[state=active]:bg-emerald-500 data-[state=active]:text-white data-[state=active]:shadow-md data-[state=active]:shadow-emerald-500/20 data-[state=inactive]:text-text-muted data-[state=inactive]:hover:text-white data-[state=inactive]:hover:bg-white/5 data-[state=inactive]:bg-transparent border-0 transition-all">
              Inventory Valuation
            </TabTrigger>
          </TabsList>
        </div>

        <TabContent value="pnl">
          <ProfitAndLossReport />
        </TabContent>
        <TabContent value="sales">
          <div className="glass-card rounded-xl border border-border p-6 min-h-[400px]">
            <h2 className="text-xl font-bold text-white mb-4">Daily Sales Summary</h2>
            {!salesData && !salesError ? <div className="text-center p-8 text-text-muted">Loading sales data...</div> : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-surface/50 border-b border-border">
                    <tr>
                      <th className="p-4 font-semibold text-text-muted">Date</th>
                      <th className="p-4 font-semibold text-text-muted text-right">Transactions</th>
                      <th className="p-4 font-semibold text-text-muted text-right">Revenue</th>
                    </tr>
                  </thead>
                  <tbody>
                    {salesData?.map((row: any) => (
                      <tr key={row.date} className="border-b border-border/50 hover:bg-white/5">
                        <td className="p-4 font-mono">{row.date}</td>
                        <td className="p-4 text-right">{row.total_transactions}</td>
                        <td className="p-4 text-right text-emerald-400 font-bold">${parseFloat(row.daily_total).toFixed(2)}</td>
                      </tr>
                    ))}
                    {(!salesData || salesData.length === 0) && (
                      <tr><td colSpan={3} className="p-8 text-center text-text-muted">No sales in this period.</td></tr>
                    )}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </TabContent>
        <TabContent value="inventory">
          <div className="glass-card rounded-xl border border-border p-6 min-h-[400px]">
            <h2 className="text-xl font-bold text-white mb-4">Stock Valuation</h2>
            {!inventoryData && !inventoryError ? <div className="text-center p-8 text-text-muted">Loading inventory data...</div> : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-surface/50 border-b border-border">
                    <tr>
                      <th className="p-4 font-semibold text-text-muted">Product / SKU</th>
                      <th className="p-4 font-semibold text-text-muted">Category</th>
                      <th className="p-4 font-semibold text-text-muted text-right">Qty</th>
                      <th className="p-4 font-semibold text-text-muted text-right">Cost Value</th>
                    </tr>
                  </thead>
                  <tbody>
                    {inventoryData?.map((item: any) => (
                      <tr key={item.sku} className="border-b border-border/50 hover:bg-white/5">
                        <td className="p-4">
                          <div className="font-semibold text-white">{item.name}</div>
                          <div className="text-xs text-text-muted">{item.sku}</div>
                        </td>
                        <td className="p-4 text-text-muted">{item.category || 'N/A'}</td>
                        <td className="p-4 text-right font-mono">{item.qty_available}</td>
                        <td className="p-4 text-right text-indigo-400 font-bold">${parseFloat(item.total_value).toFixed(2)}</td>
                      </tr>
                    ))}
                    {(!inventoryData || inventoryData.length === 0) && (
                      <tr><td colSpan={4} className="p-8 text-center text-text-muted">No stock available.</td></tr>
                    )}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </TabContent>
      </Tabs>
    </div>
  );
}

export default function ReportsPage() {
  return (
    <DateRangeProvider>
      <ReportsDashboard />
    </DateRangeProvider>
  );
}
