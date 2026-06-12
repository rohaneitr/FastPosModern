'use client';

import React, { useState } from 'react';
import { useTranslation } from '@/lib/i18n';
import api from '@/lib/api';
import toast from 'react-hot-toast';

interface RegisterGateProps {
  isOpen: boolean;
  onRegisterOpened: () => void;
}

export function RegisterGate({ isOpen, onRegisterOpened }: RegisterGateProps) {
  const { t } = useTranslation();
  const [openingCash, setOpeningCash] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (isOpen) return null; // Only show if register is NOT open

  const handleOpenRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      await api.post('/register/open', { opening_amount: parseFloat(openingCash) });
      toast.success('Register opened successfully!');
      onRegisterOpened();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to open register');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="absolute inset-0 z-10 bg-background/80 backdrop-blur-sm flex items-center justify-center rounded-xl border border-border">
      <div className="bg-surface border border-border p-6 rounded-2xl w-full max-w-md shadow-2xl text-center">
        <span className="text-5xl mb-4 block">🔒</span>
        <h2 className="text-2xl font-bold text-white mb-2">{t('pos.registerClosed') || 'Register Closed'}</h2>
        <p className="text-text-muted text-sm mb-6">
          You must open your cash register to start processing transactions for this session.
        </p>
        <form onSubmit={handleOpenRegister} className="flex flex-col gap-4 text-left">
          <div>
            <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">
              Opening Float / Cash
            </label>
            <div className="relative">
              <span className="absolute left-4 top-1/2 -translate-y-1/2 text-text-muted font-bold">$</span>
              <input
                type="number"
                step="0.01"
                min="0"
                required
                value={openingCash}
                onChange={e => setOpeningCash(e.target.value)}
                className="w-full bg-background border border-border rounded-xl pl-8 pr-4 py-3 text-white font-mono text-lg outline-none focus:border-emerald-500 transition-colors"
                placeholder="0.00"
              />
            </div>
          </div>
          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 rounded-xl shadow-lg shadow-emerald-500/20 disabled:opacity-50 transition-all flex justify-center items-center gap-2"
          >
            {isSubmitting ? (
              <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
            ) : null}
            Open Register
          </button>
        </form>
      </div>
    </div>
  );
}
