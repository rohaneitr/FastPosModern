'use client';

import React, { useState } from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';

interface CloseRegisterModalProps {
  open: boolean;
  onClose: () => void;
  registerStatus: any;
  onRegisterClosed: () => void;
}

export function CloseRegisterModal({ open, onClose, registerStatus, onRegisterClosed }: CloseRegisterModalProps) {
  const [countedCash, setCountedCash] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!open) return null;

  const handleCloseRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      const res = await api.post('/register/close', { counted_cash: parseFloat(countedCash) });
      toast.success(`Register closed. Discrepancy: $${res.data.discrepancy}`);
      onRegisterClosed();
      onClose();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to close register');
    } finally {
      setIsSubmitting(false);
    }
  };

  const expectedCash = parseFloat(registerStatus?.expected_cash || 0);
  const discrepancy = countedCash ? parseFloat(countedCash) - expectedCash : 0;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4">
      <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-md shadow-2xl animate-in zoom-in-95">
         <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-bold text-white">Close Register Session</h2>
          <button onClick={onClose} className="text-text-muted hover:text-white transition-colors text-xl">✕</button>
        </div>
        
        <div className="bg-background/50 rounded-xl p-4 border border-border mb-6">
          <div className="flex justify-between text-sm mb-2">
            <span className="text-text-muted">Opening Float:</span>
            <span className="font-mono text-white">${parseFloat(registerStatus?.register?.opening_amount || 0).toFixed(2)}</span>
          </div>
          <div className="flex justify-between text-sm mb-2">
            <span className="text-text-muted">Cash Sales & Dues Today:</span>
            <span className="font-mono text-emerald-400">+ ${parseFloat(registerStatus?.cash_sales || 0).toFixed(2)}</span>
          </div>
          <div className="flex justify-between text-sm mb-2">
            <span className="text-text-muted">Cash Expenses Today:</span>
            <span className="font-mono text-rose-400">- ${parseFloat(registerStatus?.cash_expenses || 0).toFixed(2)}</span>
          </div>
          <div className="border-t border-border/50 my-2"></div>
          <div className="flex justify-between font-bold">
            <span className="text-white">Expected Cash in Drawer:</span>
            <span className="font-mono text-white">${expectedCash.toFixed(2)}</span>
          </div>
        </div>

        <form onSubmit={handleCloseRegister} className="flex flex-col gap-4">
          <div>
            <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">Counted Cash *</label>
            <div className="relative">
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold">$</span>
              <input 
                type="number" 
                step="0.01" 
                min="0" 
                required 
                value={countedCash} 
                onChange={e => setCountedCash(e.target.value)}
                className="w-full bg-background border border-border rounded-xl pl-8 pr-4 py-3 text-white font-mono text-lg outline-none focus:border-rose-500 transition-colors"
                placeholder="0.00" 
              />
            </div>
            {countedCash && (
              <p className={`text-xs font-bold mt-2 ${discrepancy < 0 ? 'text-rose-500' : 'text-emerald-500'}`}>
                Discrepancy: ${discrepancy.toFixed(2)}
              </p>
            )}
          </div>
          <div className="flex gap-3 mt-2">
            <button 
              type="button" 
              onClick={onClose} 
              className="flex-1 py-3 bg-background border border-border rounded-xl font-medium text-text-muted hover:text-white transition-colors"
            >
              Cancel
            </button>
            <button 
              type="submit" 
              disabled={isSubmitting} 
              className="flex-[2] bg-rose-500 hover:bg-rose-600 text-white rounded-xl font-bold transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-lg shadow-rose-500/20"
            >
              {isSubmitting && <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"/>}
              Confirm Close Register
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
