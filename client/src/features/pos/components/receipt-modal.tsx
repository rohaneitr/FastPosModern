'use client';

import React from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';

interface ReceiptModalProps {
  receiptData: any;
  onClose: () => void;
}

export function ReceiptModal({ receiptData, onClose }: ReceiptModalProps) {
  if (!receiptData) return null;

  const handlePrint = () => {
    window.print();
  };

  const handleEmail = async () => {
    const email = prompt("Enter customer email address:", "");
    if (!email) return;
    try {
      await api.post(`/sales/${receiptData.transaction_id}/email`, { email });
      toast.success('Email queued for delivery!');
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to send email');
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4 hide-on-print">
      <div className="bg-surface border border-border rounded-2xl p-8 w-full max-w-md shadow-2xl animate-in zoom-in-95 text-center flex flex-col items-center">
        <span className="text-6xl mb-4">✅</span>
        <h2 className="text-2xl font-bold text-white mb-2">Sale Successful!</h2>
        <p className="text-emerald-400 font-mono text-xl mb-6">{receiptData.invoice_no}</p>
        
        <div className="flex flex-col gap-3 w-full">
          <button 
            onClick={handlePrint} 
            className="w-full bg-surface border border-border hover:bg-white/5 text-white font-bold py-3 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2"
          >
            🖨️ Print Receipt
          </button>
          <button 
            onClick={handleEmail} 
            className="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2"
          >
            ✉️ Send via Email
          </button>
          <button 
            onClick={onClose} 
            className="w-full mt-4 text-text-muted hover:text-white font-medium py-2 transition-colors"
          >
            Start New Sale
          </button>
        </div>
      </div>
    </div>
  );
}
