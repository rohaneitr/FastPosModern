'use client';

import React from 'react';
import { useCurrency } from '@/lib/currency';

interface EODPrintTemplateProps {
  data: any;
}

/**
 * Hidden thermal print template for Z-Reports.
 * Only rendered when eodData is set, only visible during window.print().
 */
export function EODPrintTemplate({ data }: EODPrintTemplateProps) {
  const { format } = useCurrency();

  if (!data) return null;

  return (
    <div className="hidden print:block absolute top-0 left-0 bg-white text-black z-[9999] p-2 text-xs font-mono w-full min-h-screen">
      <div className="mx-auto bg-white text-black relative z-10 w-[80mm]">
        <div className="text-center mb-4">
          <h1 className="font-bold text-xl uppercase tracking-wider">END OF DAY REPORT</h1>
          <h2 className="font-bold text-md mt-1">Z-REPORT</h2>
          <p className="mt-2 text-sm">Date: {data.date}</p>
          <p className="text-sm">Printed: {new Date().toLocaleString()}</p>
          <p className="text-xs mt-1 border-b-2 border-black pb-2 border-dashed">
            Cashier: {JSON.parse(localStorage.getItem('fastpos_user') || '{}')?.name || 'Admin'}
          </p>
        </div>

        <div className="w-full mb-4">
          <div className="flex justify-between font-bold mb-1 border-b border-dashed border-gray-400 pb-1">
            <span>GROSS SALES</span>
            <span>{format(data.total_sales)}</span>
          </div>
          <div className="flex justify-between font-bold mb-1 border-b border-dashed border-gray-400 pb-1">
            <span>TOTAL RETURNS</span>
            <span>{format(data.total_returns)}</span>
          </div>
          <div className="flex justify-between font-bold text-lg mb-3 border-b-2 border-black pb-2 mt-2">
            <span>NET SALES</span>
            <span>{format(data.net_sales)}</span>
          </div>

          <h3 className="font-bold text-center border-b border-black pb-1 mb-2">COLLECTED TENDERS</h3>

          <div className="flex justify-between mb-1">
            <span>CASH</span>
            <span>{format(data.collected.cash)}</span>
          </div>
          <div className="flex justify-between mb-1">
            <span>MOBILE BANKING</span>
            <span>{format(data.collected.mobile)}</span>
          </div>
          <div className="flex justify-between mb-1">
            <span>CARD / BANK</span>
            <span>{format(data.collected.card)}</span>
          </div>
          <div className="flex justify-between text-red-500 mb-1 border-b border-dashed border-gray-400 pb-1">
            <span>(-) OP EXPENSES</span>
            <span>{format(data.cash_expenses || 0)}</span>
          </div>
          <div className="flex justify-between font-bold text-xl mt-2 border-b-2 border-black pb-2">
            <span>TOTAL DEPOSIT</span>
            <span>{format(data.collected.total)}</span>
          </div>
        </div>

        <div className="text-center mt-8 pt-8">
          <div className="w-48 border-t-2 border-black mx-auto pt-1 text-sm font-bold">
            Manager Signature
          </div>
        </div>
      </div>
      <style dangerouslySetInnerHTML={{__html: `
        @media print {
          body { background-color: white !important; color: black !important; }
          body * { visibility: hidden; }
          .print\\:block, .print\\:block * { visibility: visible; }
          .print\\:block { position: absolute; left: 0; top: 0; width: 100% !important; background: white !important; color: black !important; padding: 0 !important; margin: 0 !important; }
          @page { size: 80mm auto; margin: 0; }
        }
      `}} />
    </div>
  );
}
