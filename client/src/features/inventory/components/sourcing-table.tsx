import React from 'react';

interface SourcingItem {
  line_id: string;
  invoice_no: string;
  transaction_date: string;
  product_name: string;
  sku: string;
  quantity: number;
  unit_price: string;
}

interface SourcingTableProps {
  logs: SourcingItem[];
  isLoading: boolean;
}

export function SourcingTable({ logs, isLoading }: SourcingTableProps) {
  if (isLoading) {
    return <div className="p-8 text-center text-text-muted">Loading pending sourcing...</div>;
  }

  if (!logs || logs.length === 0) {
    return (
      <div className="p-12 text-center flex flex-col items-center">
        <div className="h-16 w-16 bg-emerald-500/10 rounded-full flex items-center justify-center mb-4">
          <span className="text-2xl">✅</span>
        </div>
        <h3 className="text-lg font-bold text-white mb-2">All Parts Sourced</h3>
        <p className="text-text-muted max-w-sm">There are currently no items pending external sourcing.</p>
      </div>
    );
  }

  return (
    <div className="w-full overflow-x-auto">
      <table className="w-full text-left text-sm">
        <thead className="bg-surface/50 border-b border-border">
          <tr>
            <th className="p-4 font-semibold text-text-muted">Date</th>
            <th className="p-4 font-semibold text-text-muted">Invoice No</th>
            <th className="p-4 font-semibold text-text-muted">Product / SKU</th>
            <th className="p-4 font-semibold text-text-muted text-right">Quantity Needed</th>
            <th className="p-4 font-semibold text-text-muted text-right">Unit Price</th>
            <th className="p-4 font-semibold text-text-muted text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          {logs.map((log) => (
            <tr key={log.line_id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
              <td className="p-4 font-mono text-xs">{new Date(log.transaction_date).toLocaleDateString()}</td>
              <td className="p-4 font-mono text-xs text-blue-400">{log.invoice_no}</td>
              <td className="p-4">
                <div className="font-semibold text-white">{log.product_name}</div>
                <div className="text-xs text-text-muted mt-0.5">{log.sku}</div>
              </td>
              <td className="p-4 text-right font-bold text-amber-400">{log.quantity}</td>
              <td className="p-4 text-right">${parseFloat(log.unit_price).toFixed(2)}</td>
              <td className="p-4 text-center">
                <span className="px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-500 border border-amber-500/20">
                  Pending
                </span>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
