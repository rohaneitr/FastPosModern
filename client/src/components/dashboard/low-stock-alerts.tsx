'use client';

import React from 'react';
import Link from 'next/link';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AlertTriangle, ArrowRight } from 'lucide-react';

interface LowStockAlertsProps {
  items: any[] | undefined;
}

export function LowStockAlerts({ items }: LowStockAlertsProps) {
  if (!items?.length) return null;

  return (
    <Card className="border-rose-500/20 bg-rose-500/5">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-rose-400">
          <AlertTriangle className="w-5 h-5" />
          Low Stock Alerts
          <Badge variant="danger" className="ml-2">{items.length}</Badge>
        </CardTitle>
        <Link
          href="/business/inventory"
          className="text-sm text-rose-400 hover:text-rose-300 font-medium flex items-center gap-1 transition-colors"
        >
          Manage Inventory <ArrowRight className="w-3.5 h-3.5" />
        </Link>
      </CardHeader>
      <CardContent>
        <div className="overflow-x-auto rounded-xl border border-rose-500/20">
          <table className="w-full text-left text-sm">
            <thead className="bg-rose-500/5 border-b border-rose-500/20">
              <tr>
                <th className="px-4 py-3 font-medium text-rose-400">Product Name</th>
                <th className="px-4 py-3 font-medium text-rose-400">SKU</th>
                <th className="px-4 py-3 font-medium text-rose-400 text-right">Available Stock</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item: any, i: number) => (
                <tr
                  key={i}
                  className="border-b border-border/20 last:border-0 hover:bg-rose-500/5 transition-colors"
                >
                  <td className="px-4 py-3.5 text-white font-medium">{item.name}</td>
                  <td className="px-4 py-3.5 text-text-muted font-mono text-xs">{item.sku}</td>
                  <td className="px-4 py-3.5 text-right">
                    <span className="text-rose-400 font-bold text-lg">{item.qty_available}</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  );
}
