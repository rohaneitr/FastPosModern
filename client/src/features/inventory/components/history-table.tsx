import React from 'react';

interface HistoryItem {
  id: string;
  date: string;
  user: string;
  event_type: string;
  quantity_adjusted: number | string | null;
  reason: string;
  lot_number?: string;
  expiry_date?: string;
}

import { useEntitlements } from '@/hooks/useEntitlements';

interface HistoryTableProps {
  logs: HistoryItem[];
  isLoading: boolean;
}

export function HistoryTable({ logs, isLoading }: HistoryTableProps) {
  const { hasModule } = useEntitlements();
  const hasPharmacyModule = hasModule('pharmacy');

  if (isLoading) {
    return <div className="p-8 text-center text-text-muted">Loading history...</div>;
  }

  if (!logs || logs.length === 0) {
    return <div className="p-8 text-center text-text-muted">No inventory history found.</div>;
  }

  return (
    <div className="w-full overflow-x-auto">
      <table className="w-full text-left text-sm">
        <thead className="bg-surface/50 border-b border-border">
          <tr>
            <th className="p-4 font-semibold text-text-muted">Date</th>
            <th className="p-4 font-semibold text-text-muted">Event Type</th>
            <th className="p-4 font-semibold text-text-muted">Reason / Note</th>
            {hasPharmacyModule && <th className="p-4 font-semibold text-text-muted">Batch / Lot</th>}
            {hasPharmacyModule && <th className="p-4 font-semibold text-text-muted">Expiry</th>}
            <th className="p-4 font-semibold text-text-muted">User</th>
            <th className="p-4 font-semibold text-text-muted text-right">Qty Adjusted</th>
          </tr>
        </thead>
        <tbody>
          {logs.map((log) => {
            const isIncrease = log.event_type === 'Increase' || Number(log.quantity_adjusted) > 0;
            const isTransfer = log.event_type === 'Transfer';
            
            return (
              <tr key={log.id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                <td className="p-4 font-mono text-xs">{new Date(log.date).toLocaleString()}</td>
                <td className="p-4">
                  <span className={`px-2 py-1 rounded-full text-xs font-bold uppercase border ${
                    isTransfer ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30' :
                    isIncrease ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 
                    'bg-rose-500/10 text-rose-400 border-rose-500/30'
                  }`}>
                    {log.event_type}
                  </span>
                </td>
                <td className="p-4">{log.reason || '-'}</td>
                {hasPharmacyModule && <td className="p-4 font-mono text-xs">{log.lot_number || '-'}</td>}
                {hasPharmacyModule && <td className="p-4 font-mono text-xs text-rose-400">{log.expiry_date ? new Date(log.expiry_date).toLocaleDateString() : '-'}</td>}
                <td className="p-4 font-medium">{log.user}</td>
                <td className={`p-4 text-right font-bold ${
                    isTransfer ? 'text-indigo-400' :
                    isIncrease ? 'text-emerald-400' : 
                    'text-rose-400'
                  }`}>
                  {log.quantity_adjusted != null ? (isIncrease && !isTransfer ? '+' : '') + log.quantity_adjusted : '-'}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
