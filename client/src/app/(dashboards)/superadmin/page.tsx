'use client';

import React from 'react';
import { useTranslation } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';

export default function SuperadminOverview() {
  const { t } = useTranslation();
  const { format } = useCurrency();

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
      <div>
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-rose-400 to-orange-500">
          {t('superadmin.platformOverviewTitle')}
        </h1>
        <p className="text-text-muted mt-1">{t('superadmin.platformOverviewDesc')}</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl">🏢</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('superadmin.activeTenants')}</p>
          <h2 className="text-4xl font-bold text-white">124</h2>
          <p className="text-success text-xs font-bold mt-2">↑ 12% this month</p>
        </div>
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl">💳</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('superadmin.mrr')}</p>
          <h2 className="text-4xl font-bold text-white">{format(14500)}</h2>
          <p className="text-success text-xs font-bold mt-2">↑ 8% this month</p>
        </div>
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl">👨‍💻</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('superadmin.totalActiveUsers')}</p>
          <h2 className="text-4xl font-bold text-white">8,492</h2>
          <p className="text-success text-xs font-bold mt-2">↑ 15% this month</p>
        </div>
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl">⚠️</div>
          <p className="text-text-muted font-medium text-sm mb-1">{t('superadmin.suspendedTenants')}</p>
          <h2 className="text-4xl font-bold text-danger">3</h2>
          <p className="text-text-muted text-xs font-medium mt-2">{t('superadmin.requiresReview')}</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-surface/30 border border-border rounded-2xl p-6">
          <h3 className="text-xl font-bold text-white mb-4">{t('superadmin.recentRegistrations')}</h3>
          <div className="flex flex-col gap-4">
            <div className="flex justify-between items-center border-b border-border/50 pb-4">
              <div>
                <p className="font-bold text-white">Tech Haven Retail</p>
                <p className="text-xs text-text-muted">Pro Plan • 5 Locations</p>
              </div>
              <span className="text-xs font-bold text-success bg-success/20 px-2 py-1 rounded">{t('common.active')}</span>
            </div>
            <div className="flex justify-between items-center border-b border-border/50 pb-4">
              <div>
                <p className="font-bold text-white">Local Cafe Co.</p>
                <p className="text-xs text-text-muted">Basic Plan • 1 Location</p>
              </div>
              <span className="text-xs font-bold text-success bg-success/20 px-2 py-1 rounded">{t('common.active')}</span>
            </div>
          </div>
        </div>

        <div className="bg-surface/30 border border-border rounded-2xl p-6">
          <h3 className="text-xl font-bold text-white mb-4">{t('superadmin.systemAlerts')}</h3>
          <div className="flex flex-col gap-4">
            <div className="bg-warning/10 border border-warning/20 p-4 rounded-xl flex gap-4 items-start">
              <span className="text-xl">⚠️</span>
              <div>
                <p className="text-sm font-bold text-warning">Database Storage Nearing Limit</p>
                <p className="text-xs text-text-muted mt-1">Tenant DB-Cluster 02 is at 85% capacity. Consider scaling up before end of month.</p>
              </div>
            </div>
            <div className="bg-rose-500/10 border border-rose-500/20 p-4 rounded-xl flex gap-4 items-start">
              <span className="text-xl">🔒</span>
              <div>
                <p className="text-sm font-bold text-rose-500">Failed Login Attempts Spike</p>
                <p className="text-xs text-text-muted mt-1">Multiple failed admin logins detected from IP 192.168.1.55 on Acme Corp account.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
