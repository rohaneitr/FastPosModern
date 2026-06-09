'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';
import { useCurrency } from '@/lib/currency';

interface QuotationsModalProps {
  open: boolean;
  onClose: () => void;
  onApplyQuotation: (tx: any) => void;
}

export function QuotationsModal({ open, onClose, onApplyQuotation }: QuotationsModalProps) {
  const [quotations, setQuotations] = useState<any[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const { format } = useCurrency();

  useEffect(() => {
    if (open) {
      setIsLoading(true);
      api.get('/sales?status=quotation')
        .then(res => setQuotations(res.data?.data || []))
        .catch(() => toast.error('Failed to load quotations'))
        .finally(() => setIsLoading(false));
    }
  }, [open]);

  if (!open) return null;

  const handleApply = async (id: number) => {
    try {
      const res = await api.get(`/sales/${id}`);
      onApplyQuotation(res.data);
    } catch (err) {
      toast.error('Failed to apply quotation');
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4">
      <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-2xl shadow-2xl animate-in zoom-in-95 flex flex-col max-h-[80vh]">
        <div className="flex justify-between items-center mb-6 shrink-0">
          <div>
            <h2 className="text-xl font-bold text-white">Saved Quotations</h2>
            <p className="text-text-muted text-sm mt-1">Select a quotation to convert to a sale.</p>
          </div>
          <button onClick={onClose} className="text-text-muted hover:text-white transition-colors text-xl">✕</button>
        </div>
        
        <div className="bg-background/50 rounded-xl border border-border flex-1 overflow-y-auto">
          {isLoading ? (
             <div className="flex justify-center items-center py-12">
               <span className="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
             </div>
          ) : quotations.length === 0 ? (
             <div className="text-center text-text-muted py-12">No pending quotations found.</div>
          ) : (
            <table className="w-full text-left text-sm">
              <thead className="bg-surface/80 border-b border-border sticky top-0">
                <tr>
                  <th className="p-4 font-semibold text-text-muted">Quote #</th>
                  <th className="p-4 font-semibold text-text-muted">Date</th>
                  <th className="p-4 font-semibold text-text-muted">Customer</th>
                  <th className="p-4 font-semibold text-text-muted text-right">Total</th>
                  <th className="p-4 font-semibold text-text-muted text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                {quotations.map(q => (
                  <tr key={q.id} className="border-b border-border/50 hover:bg-surface transition-colors">
                    <td className="p-4 font-bold text-emerald-400">{q.invoice_no}</td>
                    <td className="p-4 text-text-muted">{new Date(q.created_at).toLocaleDateString()}</td>
                    <td className="p-4 text-white">{q.contact_name || 'Walk-in'}</td>
                    <td className="p-4 text-right font-mono text-white">{format(parseFloat(q.final_total))}</td>
                    <td className="p-4 text-center">
                      <button 
                        onClick={() => handleApply(q.id)} 
                        className="bg-primary/20 text-primary hover:bg-primary hover:text-white px-4 py-1.5 rounded-lg text-xs font-bold transition-colors border border-primary/30"
                      >
                        Convert
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}
