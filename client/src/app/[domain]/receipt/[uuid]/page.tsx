'use client';

import React, { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import api from '@/lib/api';

export default function DigitalReceiptPage() {
  const params = useParams();
  const invoiceNo = params.uuid as string;
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!invoiceNo) return;
    api.get(`/public/receipt/${invoiceNo}`)
      .then(res => setData(res.data))
      .catch(err => setError('Receipt not found or invalid.'))
      .finally(() => setLoading(false));
  }, [invoiceNo]);

  if (loading) return <div className="min-h-screen flex items-center justify-center text-gray-500">Loading receipt...</div>;
  if (error || !data) return <div className="min-h-screen flex items-center justify-center text-red-500 font-bold">{error}</div>;

  const { transaction, lines, payments } = data;
  const settings = transaction.settings ? JSON.parse(transaction.settings) : {};
  
  const amountPaid = payments.reduce((sum: number, p: any) => sum + parseFloat(p.amount), 0);
  const isPaid = amountPaid >= parseFloat(transaction.final_total);

  return (
    <div className="min-h-screen bg-gray-100 flex items-center justify-center p-4">
      <div className="bg-white text-black p-6 shadow-2xl w-full max-w-[80mm] min-h-[500px] font-mono text-sm relative">
        
        {/* Status Badge */}
        <div className={`absolute top-4 right-4 border-2 px-2 py-1 font-bold transform rotate-12 text-xs uppercase tracking-widest ${isPaid ? 'border-green-600 text-green-600' : 'border-red-600 text-red-600'}`}>
          {isPaid ? 'PAID' : 'DUE'}
        </div>

        {/* Header Section */}
        <div className="text-center mb-6 mt-4">
          {settings.show_logo && (
            <div className="w-12 h-12 border-2 border-black rounded-full mx-auto mb-3 flex items-center justify-center font-bold text-black uppercase tracking-widest text-xs">LOGO</div>
          )}
          <h1 className="font-bold text-xl uppercase tracking-wider">{transaction.business_name}</h1>
          {settings.show_address && (
            <p className="text-xs text-black mt-1">123 Main Street, Tech Park<br/>City, State 12345</p>
          )}
          {settings.invoice_header_text && (
            <p className="text-sm font-bold mt-3 uppercase border-b-2 border-t-2 border-dashed border-black py-1">{settings.invoice_header_text}</p>
          )}
        </div>

        {/* Meta Data */}
        <div className="text-xs mb-4 flex justify-between border-b-2 border-black pb-2">
          <div>
            <p>Invoice: <b>{transaction.invoice_no}</b></p>
            <p>Date: {new Date(transaction.transaction_date).toLocaleString()}</p>
          </div>
        </div>

        {/* Items */}
        <div className="w-full">
          <table className="w-full text-left font-mono border-collapse border-b-2 border-black mb-4 text-xs">
            <thead>
              <tr className="border-b border-black">
                <th className="p-1 uppercase font-bold">Item</th>
                <th className="p-1 uppercase text-center font-bold">Qty</th>
                <th className="p-1 uppercase text-right font-bold">Price</th>
                <th className="p-1 uppercase text-right font-bold">Total</th>
              </tr>
            </thead>
            <tbody>
              {lines.map((item: any, index: number) => (
                <tr key={index} className="border-b border-dashed border-gray-400">
                  <td className="p-1 leading-tight">{item.name}</td>
                  <td className="p-1 text-center leading-tight">{parseFloat(item.quantity)}</td>
                  <td className="p-1 text-right leading-tight">{parseFloat(item.unit_price).toFixed(2)}</td>
                  <td className="p-1 text-right leading-tight">{(parseFloat(item.quantity) * parseFloat(item.unit_price)).toFixed(2)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Totals */}
        <div className="border-t-2 border-black pt-2 w-full md:w-3/4 float-right clear-both text-xs">
          <div className="flex justify-between mb-1">
            <span>Subtotal:</span>
            <span>{parseFloat(transaction.total_before_tax).toFixed(2)}</span>
          </div>
          <div className="flex justify-between mb-1">
            <span>Tax:</span>
            <span>{parseFloat(transaction.tax_amount).toFixed(2)}</span>
          </div>
          <div className="flex justify-between mb-1">
            <span>Discount:</span>
            <span>{parseFloat(transaction.discount_amount || 0).toFixed(2)}</span>
          </div>
          <div className="flex justify-between font-bold text-lg mt-2 border-t-2 border-black pt-2 mb-2">
            <span>Total:</span>
            <span>{parseFloat(transaction.final_total).toFixed(2)}</span>
          </div>
          <div className="flex justify-between text-xs font-semibold">
            <span>Amount Paid:</span>
            <span>{amountPaid.toFixed(2)}</span>
          </div>
        </div>
        <div className="clear-both"></div>

        {/* Footer */}
        <div className="text-center mt-8 pt-6 border-t border-dashed border-black">
          <div className="mb-4 flex flex-col items-center">
            <div className="w-40 h-8 bg-[repeating-linear-gradient(90deg,#000,#000_2px,transparent_2px,transparent_4px)] opacity-80"></div>
            <span className="text-[10px] mt-1 tracking-widest">{transaction.invoice_no}</span>
          </div>
          {settings.invoice_footer_text && (
            <p className="text-xs whitespace-pre-wrap font-semibold italic">{settings.invoice_footer_text}</p>
          )}
          <p className="text-[10px] mt-6 opacity-50">Powered by FastPOS Modern</p>
        </div>

        {/* Print Button */}
        <button onClick={() => window.print()} className="mt-8 w-full bg-black text-white font-bold py-3 rounded uppercase tracking-wider print:hidden shadow-lg active:scale-95 transition-transform">
          Print Receipt
        </button>
      </div>

      <style dangerouslySetInnerHTML={{__html: `
        @media print {
          body { background-color: white !important; margin: 0; padding: 0; }
          body * { visibility: hidden; }
          .max-w-\\[80mm\\] { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: none !important; }
          .max-w-\\[80mm\\], .max-w-\\[80mm\\] * { visibility: visible; }
          @page { margin: 0; }
        }
      `}} />
    </div>
  );
}
