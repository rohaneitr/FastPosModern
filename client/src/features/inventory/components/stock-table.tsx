'use client';

import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SearchInput } from '@/components/ui/search-input';
import { TableRowSkeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/ui/empty-state';
import { PackageSearch } from 'lucide-react';
import { FeatureGate } from '@/components/common/FeatureGate';

interface StockTableProps {
  stocks: any[];
  isLoading: boolean;
  searchQuery?: string;
  onSearchChange?: (q: string) => void;
  onAdjust: (product: any) => void;
  onTransfer: (product: any) => void;
  showSearch?: boolean;
}

export function StockTable({
  stocks,
  isLoading,
  searchQuery,
  onSearchChange,
  onAdjust,
  onTransfer,
  showSearch = false,
}: StockTableProps) {
  return (
    <div className="flex flex-col gap-6 animate-in slide-in-from-right-4">
      {showSearch && (
        <div className="flex gap-4 items-center bg-surface/30 p-4 rounded-xl border border-border">
          <div className="w-full max-w-md">
            <SearchInput
              value={searchQuery || ''}
              onChange={onSearchChange || (() => {})}
              placeholder="Search by Product Name or SKU..."
            />
          </div>
          <div className="ml-auto text-sm font-medium px-4 py-2 bg-emerald-500/10 text-emerald-400 rounded-lg border border-emerald-500/20">
            {/* Ideally we compute the total value here. For now keeping it visually similar to the original */}
            Total Items: {stocks.length}
          </div>
        </div>
      )}

      <div className="overflow-x-auto rounded-xl border border-border bg-background/50">
        <table className="w-full text-left text-sm">
          <thead className="bg-surface/80 border-b border-border">
            <tr>
              <th className="p-4 font-semibold text-text-muted">Product</th>
              <th className="p-4 font-semibold text-text-muted">SKU</th>
              <th className="p-4 font-semibold text-text-muted">Location</th>
              <th className="p-4 font-semibold text-text-muted text-right">Qty Available</th>
              <th className="p-4 font-semibold text-text-muted text-center">Status</th>
              <th className="p-4 font-semibold text-text-muted text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            {isLoading ? (
              Array.from({ length: 5 }).map((_, i) => <TableRowSkeleton key={i} columns={6} />)
            ) : stocks.length === 0 ? (
              <tr>
                <td colSpan={6} className="p-8">
                  <EmptyState
                    icon={<PackageSearch className="w-8 h-8" />}
                    title="No products found"
                    description="No inventory items match your criteria."
                  />
                </td>
              </tr>
            ) : (
              stocks.map((s) => {
                const qty = parseFloat(s.qty_available);
                let variant: 'success' | 'warning' | 'danger' = 'success';
                let statusText = 'In Stock';
                
                if (qty <= 0) {
                  variant = 'danger';
                  statusText = 'Out of Stock';
                } else if (qty < 10) {
                  variant = 'warning';
                  statusText = 'Low Stock';
                }

                return (
                  <tr
                    key={s.id}
                    className="border-b border-border/50 hover:bg-surface transition-colors"
                  >
                    <td className="p-4 font-bold text-white">{s.product_name}</td>
                    <td className="p-4 font-mono text-text-muted text-xs">{s.sku || 'N/A'}</td>
                    <td className="p-4 text-emerald-400 font-medium">{s.location_name}</td>
                    <td className="p-4 text-right font-mono text-lg text-white">{qty}</td>
                    <td className="p-4 text-center">
                      <Badge variant={variant}>{statusText}</Badge>
                    </td>
                    <td className="p-4 text-center">
                      <div className="flex gap-2 justify-center">
                        <FeatureGate permission="inventory.adjust">
                          <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => onAdjust(s)}
                            className="text-emerald-400 hover:bg-emerald-500/10 border-emerald-500/20"
                          >
                            Adjust
                          </Button>
                        </FeatureGate>
                        <FeatureGate permission="inventory.transfer">
                          <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => onTransfer(s)}
                            className="text-blue-400 hover:bg-blue-500/10 border-blue-500/20"
                          >
                            Transfer
                          </Button>
                        </FeatureGate>
                      </div>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
