'use client';

import React from 'react';
import { Tabs, TabsList, TabTrigger, TabContent } from '@/components/ui/tabs';
import { DateRangeProvider, useDateRange } from '@/features/reports/components/date-range-provider';
import { ProfitAndLossReport } from '@/features/reports/components/profit-and-loss-report';
import toast from 'react-hot-toast';

function ReportsDashboard() {
  const { startDate, endDate, setStartDate, setEndDate } = useDateRange();

  const handleExport = () => {
    toast.error('Server-side PDF Export coming in next phase.');
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
            className="bg-primary/20 text-primary hover:bg-primary hover:text-white px-4 py-1.5 rounded-lg text-sm font-bold transition-colors"
          >
            Export PDF
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
          <div className="glass-card p-12 text-center rounded-xl border border-border text-text-muted">
            Sales Summary Report Generation under construction.
          </div>
        </TabContent>
        <TabContent value="inventory">
          <div className="glass-card p-12 text-center rounded-xl border border-border text-text-muted">
            Inventory Valuation Report Generation under construction.
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
