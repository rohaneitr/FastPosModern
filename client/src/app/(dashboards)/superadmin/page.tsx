'use client';

import React, { useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';

export default function SuperadminOverview() {
  const { t } = useTranslation();
  const { format, convert, currentCurrency } = useCurrency();
  const [stats, setStats] = useState({ total_tenants: 0, active_subscriptions: 0, total_plans: 0, lifetime_revenue: 0, mrr: 0, arr: 0 });
  const [overview, setOverview] = useState({ recent_tenants: [], system_alerts: [] });
  const [loading, setLoading] = useState(true);

  React.useEffect(() => {
    const fetchStats = async () => {
      try {
        const { default: api } = await import('@/lib/api');
        const [statsRes, overviewRes] = await Promise.all([
          api.get('/superadmin/overview-stats'),
          api.get('/superadmin/dashboard-overview')
        ]);
        setStats(statsRes.data);
        setOverview(overviewRes.data);
      } catch (error) {
        console.error('Failed to load overview stats', error);
      } finally {
        setLoading(false);
      }
    };
    fetchStats();
  }, []);

  if (loading) {
    return <div className="p-8 text-center text-text-muted">Loading overview...</div>;
  }

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
          <h2 className="text-4xl font-bold text-white">{stats.total_tenants}</h2>
          <p className="text-success text-xs font-bold mt-2">Live Data</p>
        </div>
        <div className="bg-surface/30 border border-emerald-500/30 p-6 rounded-2xl relative overflow-hidden shadow-[0_0_15px_rgba(16,185,129,0.1)]">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl text-emerald-500">💰</div>
          <p className="text-emerald-400 font-medium text-sm mb-1 uppercase tracking-wider">Monthly Recurring (MRR)</p>
          <h2 className="text-4xl font-black text-white">{format(convert(parseFloat(stats.mrr as any) || 0, 'USD', currentCurrency.code), currentCurrency.code)}</h2>
          <p className="text-emerald-500/70 text-xs font-bold mt-2">Projected Monthly</p>
        </div>
        <div className="bg-surface/30 border border-emerald-500/30 p-6 rounded-2xl relative overflow-hidden shadow-[0_0_15px_rgba(16,185,129,0.1)]">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl text-emerald-500">💎</div>
          <p className="text-emerald-400 font-medium text-sm mb-1 uppercase tracking-wider">Annual Recurring (ARR)</p>
          <h2 className="text-4xl font-black text-white">{format(convert(parseFloat(stats.arr as any) || 0, 'USD', currentCurrency.code), currentCurrency.code)}</h2>
          <p className="text-emerald-500/70 text-xs font-bold mt-2">Projected Yearly</p>
        </div>
        <div className="bg-surface/30 border border-border p-6 rounded-2xl relative overflow-hidden">
          <div className="absolute top-0 right-0 p-4 opacity-10 text-4xl">💳</div>
          <p className="text-text-muted font-medium text-sm mb-1">Lifetime Collected</p>
          <h2 className="text-4xl font-bold text-white">{format(convert(parseFloat(stats.lifetime_revenue as any) || 0, 'BDT', currentCurrency.code), currentCurrency.code)}</h2>
          <p className="text-success text-xs font-bold mt-2">Live Data</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div className="bg-surface/30 border border-border rounded-2xl p-6">
          <h3 className="text-xl font-bold text-white mb-4">{t('superadmin.recentRegistrations')}</h3>
          <div className="flex flex-col gap-4">
            {overview.recent_tenants.map((tenant: any) => (
              <div key={tenant.id} className="flex justify-between items-center border-b border-border/50 pb-4">
                <div>
                  <p className="font-bold text-white">{tenant.name}</p>
                  <p className="text-xs text-text-muted">{tenant.plan_name || 'No Plan'} • {tenant.active_devices} Active Devices</p>
                </div>
                <span className={`text-xs font-bold px-2 py-1 rounded ${tenant.status === 'active' ? 'text-success bg-success/20' : 'text-warning bg-warning/20'}`}>
                  {tenant.status === 'active' ? t('common.active') : tenant.status}
                </span>
              </div>
            ))}
            {overview.recent_tenants.length === 0 && (
              <p className="text-sm text-text-muted">No recent registrations.</p>
            )}
          </div>
        </div>

        <div className="bg-surface/30 border border-border rounded-2xl p-6">
          <h3 className="text-xl font-bold text-white mb-4">{t('superadmin.systemAlerts')}</h3>
          <div className="flex flex-col gap-4">
            {overview.system_alerts.map((alert: any) => (
              <div key={alert.id} className={`p-4 rounded-xl flex gap-4 items-start border ${
                alert.type === 'danger' ? 'bg-rose-500/10 border-rose-500/20' : 
                alert.type === 'warning' ? 'bg-warning/10 border-warning/20' : 
                'bg-success/10 border-success/20'
              }`}>
                <span className="text-xl">
                  {alert.type === 'danger' ? '🚨' : alert.type === 'warning' ? '⚠️' : '✅'}
                </span>
                <div>
                  <p className={`text-sm font-bold ${
                    alert.type === 'danger' ? 'text-rose-500' : 
                    alert.type === 'warning' ? 'text-warning' : 
                    'text-success'
                  }`}>
                    {alert.title}
                  </p>
                  <p className="text-xs text-text-muted mt-1">{alert.message}</p>
                </div>
              </div>
            ))}
            {overview.system_alerts.length === 0 && (
              <p className="text-sm text-text-muted">No system alerts.</p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
