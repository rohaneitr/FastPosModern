'use client';

import React from 'react';
import Link from 'next/link';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/lib/i18n';
import { Monitor, PackagePlus, ClipboardList, BarChart3 } from 'lucide-react';
import { Zap } from 'lucide-react';

export function QuickActionsPanel() {
  const { t } = useTranslation();

  const actions = [
    { label: t('nav.openPOS'), href: '/user/pos', icon: Monitor, accent: true },
    { label: t('business.addNewProduct'), href: '/business/products', icon: PackagePlus },
    { label: t('business.receiveInventory'), href: '/business/inventory', icon: ClipboardList },
    { label: t('business.viewSalesReport'), href: '/business/reports', icon: BarChart3 },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Zap className="w-5 h-5 text-emerald-400" />
          {t('business.quickActions')}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col gap-2">
          {actions.map((action) => {
            const Icon = action.icon;
            return (
              <Link
                key={action.href}
                href={action.href}
                className={`w-full flex items-center gap-3 p-3.5 rounded-xl transition-all duration-150 font-medium text-sm border ${
                  action.accent
                    ? 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border-emerald-500/20 hover:border-emerald-500/40'
                    : 'bg-surface/50 hover:bg-white/5 text-white border-border hover:border-white/10'
                }`}
              >
                <Icon className="w-5 h-5 shrink-0" />
                {action.label}
              </Link>
            );
          })}
        </div>
      </CardContent>
    </Card>
  );
}
