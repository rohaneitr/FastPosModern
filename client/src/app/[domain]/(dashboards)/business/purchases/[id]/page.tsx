'use client';

import React, { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import api from '@/lib/api';

export default function PurchaseDetailPage() {
  const params = useParams();
  const router = useRouter();
  const purchaseId = params.id as string;

  const [purchase, setPurchase] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  
  // Return Modal State
  const [isReturnModalOpen, setIsReturnModalOpen] = useState(false);
  const [returnLines, setReturnLines] = useState<any[]>([]);
  const [isProcessingReturn, setIsProcessingReturn] = useState(false);

  useEffect(() => {
    if (purchaseId) {
      fetchPurchase();
    }
  }, [purchaseId]);

  const fetchPurchase = async () => {
    try {
      setLoading(true);
      const res = await api.get(`/purchases/${purchaseId}`);
      setPurchase(res.data);
      
      const initialLines = res.data.lines?.map((line: any) => ({
        product_id: line.product_id,
        name: line.product_name,
        purchased_qty: Number(line.quantity),
        return_qty: 0,
        unit_price: Number(line.unit_price),
        purchased_serials: res.data.serials?.filter((s:any) => s.product_id === line.product_id).map((s:any) => s.serial_number) || [],
        return_serials: []
      })) || [];
      setReturnLines(initialLines);
      
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to load purchase details.');
    } finally {
      setLoading(false);
    }
  };

  const handleReturnQtyChange = (index: number, val: number) => {
    const updated = [...returnLines];
    if (val < 0) val = 0;
    if (val > updated[index].purchased_qty) val = updated[index].purchased_qty;
    updated[index].return_qty = val;
    if (updated[index].return_serials.length > val) {
      updated[index].return_serials = updated[index].return_serials.slice(0, val);
    }
    setReturnLines(updated);
  };

  const toggleSerialReturn = (index: number, serial: string) => {
    const updated = [...returnLines];
    const item = updated[index];
    if (item.return_serials.includes(serial)) {
      item.return_serials = item.return_serials.filter((s:string) => s !== serial);
    } else {
      if (item.return_serials.length < item.return_qty) {
        item.return_serials.push(serial);
      } else {
        alert(`You can only select ${item.return_qty} serials for return. Increase return quantity first.`);
      }
    }
    setReturnLines(updated);
  };

  const calculateReturnTotal = () => {
    return returnLines.reduce((sum, line) => sum + (line.return_qty * line.unit_price), 0);
  };

  const handleProcessReturn = async (e: React.FormEvent) => {
    e.preventDefault();
    
    const validLines = returnLines.filter(l => l.return_qty > 0);
    if (validLines.length === 0) {
      alert("Please select at least one item to return.");
      return;
    }
    
    for (const l of validLines) {
      if (l.purchased_serials.length > 0 && l.return_serials.length !== l.return_qty) {
        alert(`Please select exactly ${l.return_qty} serial numbers for ${l.name}.`);
        return;
      }
    }

    setIsProcessingReturn(true);
    try {
      const payload = {
        transaction_id: purchase.id,
        return_amount: calculateReturnTotal(),
        lines: validLines.map(l => ({
          product_id: l.product_id,
          quantity: l.return_qty,
          serial_numbers: l.return_serials
        }))
      };
      
      await api.post('/purchases/return', payload);
      alert('Purchase return processed successfully! Stock decremented and serials removed.');
      setIsReturnModalOpen(false);
      fetchPurchase();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to process return');
    } finally {
      setIsProcessingReturn(false);
    }
  };

  if (loading) return <div className="p-8 flex justify-center"><div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div></div>;
  if (error || !purchase) return <div className="p-8 text-rose-500">{error || 'Purchase not found'}</div>;

  return (
    <div className="flex flex-col gap-6 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-start">
        <div>
          <button onClick={() => router.back()} className="text-sm text-text-muted hover:text-white mb-2 block transition-colors">&larr; Back to Purchases</button>
          <h1 className="text-3xl font-black text-white flex items-center gap-3">
            Purchase Order {purchase.invoice_no}
          </h1>
          <p className="text-text-muted mt-1">{new Date(purchase.transaction_date).toLocaleString()} • {purchase.supplier?.name || 'Walk-in Supplier'}</p>
        </div>
        <div className="flex gap-3">
          <button onClick={() => window.print()} className="glass bg-white/5 hover:bg-white/10 px-5 py-2 rounded-xl text-sm font-semibold transition-all">
            🖨️ Print
          </button>
          <button onClick={() => setIsReturnModalOpen(true)} className="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2 rounded-xl text-sm font-bold shadow-lg shadow-amber-500/20 transition-all flex items-center gap-2">
            ↩ Process Return (DOA)
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="glass-card p-5 rounded-2xl border border-white/5">
          <span className="text-text-muted text-xs uppercase font-bold tracking-wider block mb-1">Total Amount</span>
          <span className="text-3xl font-black text-white">${Number(purchase.final_total).toFixed(2)}</span>
        </div>
        <div className="glass-card p-5 rounded-2xl border border-white/5">
          <span className="text-text-muted text-xs uppercase font-bold tracking-wider block mb-1">Payment Status</span>
          <span className={`text-2xl font-black ${purchase.payment_status === 'paid' ? 'text-emerald-400' : 'text-rose-400'} uppercase`}>{purchase.payment_status}</span>
        </div>
        <div className="glass-card p-5 rounded-2xl border border-white/5">
          <span className="text-text-muted text-xs uppercase font-bold tracking-wider block mb-1">Status</span>
          <span className="text-3xl font-black text-cyan-400 uppercase">{purchase.status}</span>
        </div>
      </div>

      <div className="glass-card rounded-2xl border border-white/5 overflow-hidden">
        <div className="p-4 bg-white/5 border-b border-border">
          <h2 className="font-semibold">Purchased Items</h2>
        </div>
        <div className="overflow-x-auto">
          <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">name</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">details</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">status</th>
              <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {returnLines?.length > 0 ? (
      returnLines.map((line, index) => (
      <tr key={line.id || index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{line.name}</td>
                <td className="px-6 py-4 text-white font-medium">{line.details}</td>
                <td className="px-6 py-4 text-white font-medium">{line.status}</td>
                <td className="px-6 py-4 text-right"><button className="text-rose-500 hover:text-rose-400 font-medium text-sm">View</button></td>
      </tr>
    ))) : (
      <tr>
        <td colSpan={10} className="px-6 py-8 text-center text-text-muted">No records found.</td>
      </tr>
    )}
  </tbody>
</table>
</div>
        </div>
      </div>

      {isReturnModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
          <div className="glass-card w-full max-w-2xl rounded-2xl p-0 shadow-2xl border border-white/10 flex flex-col max-h-[90vh]">
            <div className="p-6 border-b border-border flex justify-between items-center bg-white/5">
              <h2 className="text-xl font-bold flex items-center gap-2">↩ Return to Supplier</h2>
              <button onClick={() => setIsReturnModalOpen(false)} className="text-text-muted hover:text-white transition-colors">✕</button>
            </div>
            
            <div className="p-6 overflow-y-auto flex-1 flex flex-col gap-6">
              <div>
                <h3 className="text-sm font-semibold text-text-muted uppercase tracking-wider mb-3">Select Items to Return</h3>
                <div className="flex flex-col gap-3">
                  {returnLines.map((line, idx) => (
                    <div key={idx} className="bg-background/50 border border-border p-4 rounded-xl">
                      <div className="flex justify-between items-center mb-2">
                        <div className="font-medium">{line.name}</div>
                        <div className="flex items-center gap-3">
                          <span className="text-xs text-text-muted">Purchased: {line.purchased_qty}</span>
                          <div className="flex items-center bg-surface border border-border rounded-lg overflow-hidden">
                            <button onClick={() => handleReturnQtyChange(idx, line.return_qty - 1)} className="px-3 py-1 bg-white/5 hover:bg-white/10">-</button>
                            <span className="px-3 py-1 font-mono">{line.return_qty}</span>
                            <button onClick={() => handleReturnQtyChange(idx, line.return_qty + 1)} className="px-3 py-1 bg-white/5 hover:bg-white/10">+</button>
                          </div>
                        </div>
                      </div>
                      
                      {line.return_qty > 0 && line.purchased_serials.length > 0 && (
                        <div className="mt-3 pt-3 border-t border-border/50">
                          <span className="text-xs text-amber-400 font-semibold mb-2 block">Select {line.return_qty} serial(s) to return:</span>
                          <div className="flex flex-wrap gap-2">
                            {line.purchased_serials.map((s: string) => (
                              <button 
                                key={s}
                                onClick={() => toggleSerialReturn(idx, s)}
                                className={`px-2 py-1 rounded text-xs font-mono border transition-all
                                  ${line.return_serials.includes(s) 
                                    ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 shadow-[0_0_10px_rgba(251,191,36,0.2)]' 
                                    : 'bg-surface text-text-muted border-border hover:bg-white/10'}`}
                              >
                                {s}
                              </button>
                            ))}
                          </div>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </div>

              <div className="bg-primary/10 border border-primary/20 p-4 rounded-xl">
                <h3 className="text-sm font-semibold text-primary mb-3">Return Summary</h3>
                <div className="flex justify-between font-mono text-lg">
                  <span>Total Return Value:</span>
                  <span className="font-bold">${calculateReturnTotal().toFixed(2)}</span>
                </div>
              </div>
            </div>

            <div className="p-6 border-t border-border bg-white/5 flex justify-end gap-3 rounded-b-2xl">
              <button onClick={() => setIsReturnModalOpen(false)} className="px-5 py-2.5 rounded-lg text-text-muted hover:text-white hover:bg-white/5 transition-colors font-medium">Cancel</button>
              <button 
                onClick={handleProcessReturn} 
                disabled={isProcessingReturn || calculateReturnTotal() <= 0}
                className="px-6 py-2.5 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white rounded-lg font-bold shadow-lg shadow-amber-600/20 transition-all flex items-center gap-2"
              >
                {isProcessingReturn ? 'Processing...' : 'Confirm Return'}
              </button>
            </div>
          </div>
        </div>
      )}

      <style jsx global>{`
        @media print {
          body * { visibility: hidden; }
          .glass-card, .glass-card * { visibility: visible; }
          .glass-card { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; background: transparent; }
        }
      `}</style>
    </div>
  );
}
