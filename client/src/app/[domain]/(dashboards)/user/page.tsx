'use client';

import React from 'react';
import Link from 'next/link';
import { useTranslation } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';

export default function UserDashboard() {
  const { t } = useTranslation();
  const { format } = useCurrency();

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
      <div>
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-green-400 to-emerald-600">
          {t('user.staffDashboard')}
        </h1>
        <p className="text-text-muted mt-1">{t('user.staffDashboardDesc')}</p>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-emerald-500/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">💵</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('user.yourSalesToday')}</p>
          <h2 className="text-4xl font-bold text-white">{format(432.50)}</h2>
          <div className="w-full bg-border h-2 rounded-full mt-4 overflow-hidden">
            <div className="bg-emerald-500 h-full w-[65%] rounded-full"></div>
          </div>
          <p className="text-text-muted text-xs font-medium mt-2">65% {t('user.dailyTarget')} ({format(665)})</p>
        </div>
        
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-emerald-500/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">🧾</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('user.transactionsProcessed')}</p>
          <h2 className="text-4xl font-bold text-white">28</h2>
          <p className="text-success text-xs font-bold mt-2 flex items-center gap-1">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            {t('user.topPercent')}
          </p>
        </div>
        
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden group hover:border-emerald-500/50 transition-colors">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl group-hover:scale-110 transition-transform">⏱️</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('user.currentShift')}</p>
          <h2 className="text-4xl font-bold text-white">4h 12m</h2>
          <p className="text-text-muted text-xs font-medium mt-2">{t('user.startedAt')} 08:00 AM</p>
        </div>
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        {/* Operations */}
        <div className="bg-surface/30 border border-border rounded-2xl p-6">
          <h3 className="text-xl font-bold text-white mb-4">{t('user.operations')}</h3>
          <div className="flex flex-col gap-3">
            <Link href="/user/pos" className="w-full flex justify-center items-center gap-3 bg-emerald-500 hover:bg-emerald-600 text-white p-5 rounded-xl transition-colors font-bold text-lg shadow-lg shadow-emerald-500/20">
              <span className="text-2xl">🖥️</span> {t('nav.openPOS')}
            </Link>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
              <button className="flex flex-col items-center justify-center gap-2 bg-surface hover:bg-surface/80 text-white p-4 rounded-xl transition-colors font-medium border border-border">
                <span className="text-2xl">⏸️</span>
                <span className="text-sm">{t('user.pauseShift')}</span>
              </button>
              <button className="flex flex-col items-center justify-center gap-2 bg-surface hover:bg-surface/80 text-white p-4 rounded-xl transition-colors font-medium border border-border">
                <span className="text-2xl">🛑</span>
                <span className="text-sm">{t('user.endShift')}</span>
              </button>
            </div>
          </div>
        </div>

        {/* Recent Activity */}
        <div className="bg-surface/30 border border-border rounded-2xl p-6">
          <div className="flex justify-between items-center mb-6">
            <h3 className="text-xl font-bold text-white">{t('user.recentActivity')}</h3>
          </div>
          
          <div className="relative border-l-2 border-border/50 ml-3 flex flex-col gap-6 pb-2">
            <div className="relative pl-6">
              <div className="absolute -left-[9px] top-1 h-4 w-4 rounded-full bg-emerald-500 ring-4 ring-background"></div>
              <p className="text-sm text-text-muted mb-1">10:24 AM</p>
              <p className="text-white font-medium">{t('user.completedSale')} (INV-1042)</p>
              <p className="text-xs text-text-muted mt-1">{format(145.50)} • {t('user.cashPayment')}</p>
            </div>
            
            <div className="relative pl-6">
              <div className="absolute -left-[9px] top-1 h-4 w-4 rounded-full bg-primary ring-4 ring-background"></div>
              <p className="text-sm text-text-muted mb-1">09:45 AM</p>
              <p className="text-white font-medium">{t('user.completedSale')} (INV-1041)</p>
              <p className="text-xs text-text-muted mt-1">{format(24.00)} • {t('user.cardPayment')}</p>
            </div>

            <div className="relative pl-6">
              <div className="absolute -left-[9px] top-1 h-4 w-4 rounded-full bg-warning ring-4 ring-background"></div>
              <p className="text-sm text-text-muted mb-1">08:00 AM</p>
              <p className="text-white font-medium">{t('user.registerOpened')}</p>
              <p className="text-xs text-text-muted mt-1">{t('user.startingFloat')}: {format(100.00)}</p>
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
