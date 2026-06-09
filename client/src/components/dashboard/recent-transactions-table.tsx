'use client';

import React from 'react';
import Link from 'next/link';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { TableRowSkeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/ui/empty-state';
import { useTranslation } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';
import { Receipt } from 'lucide-react';

interface RecentTransactionsTableProps {
  transactions: any[] | undefined;
  isLoading: boolean;
}

export function RecentTransactionsTable({ transactions, isLoading }: RecentTransactionsTableProps) {
  const { t } = useTranslation();
  const { format } = useCurrency();

  return (
    <Card className="lg:col-span-2">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Receipt className="w-5 h-5 text-emerald-400" />
          {t('business.recentTransactions')}
        </CardTitle>
        <Link
          href="/business/sales"
          className="text-sm text-emerald-400 hover:text-emerald-300 font-medium transition-colors"
        >
          {t('business.viewAll')} →
        </Link>
      </CardHeader>
      <CardContent>
        <div className="overflow-x-auto rounded-xl border border-border/50">
          <table className="w-full text-left text-sm">
            <thead className="bg-surface/50 border-b border-border/50">
              <tr>
                <th className="px-4 py-3 font-medium text-text-muted">{t('business.invoiceId')}</th>
                <th className="px-4 py-3 font-medium text-text-muted">{t('common.time')}</th>
                <th className="px-4 py-3 font-medium text-text-muted">{t('business.cashier')}</th>
                <th className="px-4 py-3 font-medium text-text-muted text-right">{t('common.total')}</th>
                <th className="px-4 py-3 font-medium text-text-muted text-center">{t('common.status')}</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <TableRowSkeleton key={i} columns={5} />
                ))
              ) : !transactions?.length ? (
                <tr>
                  <td colSpan={5}>
                    <EmptyState
                      icon={<Receipt className="w-8 h-8" />}
                      title="No transactions yet"
                      description="Recent sales will appear here."
                      className="py-10"
                    />
                  </td>
                </tr>
              ) : (
                transactions.map((tx: any, i: number) => (
                  <tr
                    key={i}
                    className="border-b border-border/20 last:border-0 hover:bg-white/[0.02] transition-colors"
                  >
                    <td className="px-4 py-3.5 text-white font-medium font-mono text-xs">
                      {tx.invoice_no}
                    </td>
                    <td className="px-4 py-3.5 text-text-muted">
                      {new Date(tx.transaction_date).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit',
                      })}
                    </td>
                    <td className="px-4 py-3.5 text-text-muted">{tx.cashier_name?.trim() || '—'}</td>
                    <td className="px-4 py-3.5 text-white font-medium text-right">
                      {format(parseFloat(tx.final_total))}
                    </td>
                    <td className="px-4 py-3.5 text-center">
                      <Badge variant={tx.status === 'final' ? 'success' : 'danger'}>
                        {tx.status === 'final' ? t('business.completed') : tx.status}
                      </Badge>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  );
}
