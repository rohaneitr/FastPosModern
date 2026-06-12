'use client';

import React, { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import api from '@/lib/api';
import toast from 'react-hot-toast';

export default function InvoicePage() {
  const params = useParams();
  const router = useRouter();
  const [invoice, setInvoice] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchInvoice = async () => {
      try {
        // Use generic reporting endpoint
        const res = await api.get(`/invoices/${params.id}`);
        setInvoice(res.data);
      } catch (err: any) {
        toast.error('Failed to load invoice details.');
      } finally {
        setLoading(false);
      }
    };
    if (params.id) {
      fetchInvoice();
    }
  }, [params.id]);

  if (loading) return <div className="flex justify-center items-center h-64 text-text-muted">Loading Invoice...</div>;
  if (!invoice) return <div className="flex justify-center items-center h-64 text-red-400">Invoice not found</div>;

  return (
    <div className="flex flex-col gap-8 max-w-4xl mx-auto animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <button onClick={() => router.back()} className="text-text-muted hover:text-white flex items-center gap-2 transition-colors">
          <span>← Back</span>
        </button>
        <button onClick={() => window.print()} className="bg-primary hover:bg-emerald-600 text-white px-5 py-2 rounded-xl font-bold transition-all shadow-lg shadow-emerald-500/20">
          Print Receipt
        </button>
      </div>

      <div className="glass-card rounded-2xl p-10 bg-white text-gray-900 border-none shadow-2xl print:shadow-none print:p-0 print:bg-transparent">
        <div className="flex justify-between items-start border-b border-gray-200 pb-6 mb-6">
          <div>
            <h1 className="text-4xl font-black text-gray-900 mb-2">{invoice.business_name || 'FastPOS Business'}</h1>
            <p className="text-gray-500 text-sm">Invoice No: <span className="font-bold text-gray-900">{invoice.invoice_no}</span></p>
            <p className="text-gray-500 text-sm">Date: {new Date(invoice.transaction_date).toLocaleDateString()}</p>
            <p className="text-gray-500 text-sm">Cashier: {invoice.cashier_first} {invoice.cashier_last}</p>
          </div>
          <div className="text-right">
            <h2 className="text-xl font-bold uppercase text-gray-400">{invoice.status === 'quotation' ? 'Quotation' : 'Invoice'}</h2>
            <div className={`mt-2 inline-block px-3 py-1 rounded-full text-xs font-bold uppercase ${
              invoice.payment_status === 'paid' ? 'bg-green-100 text-green-700' :
              invoice.payment_status === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'
            }`}>
              {invoice.payment_status}
            </div>
            {invoice.sourcing_status === 'pending_parts' && (
              <div className="mt-2 ml-2 inline-block px-3 py-1 rounded-full text-xs font-bold uppercase bg-amber-100 text-amber-700">
                PENDING PARTS
              </div>
            )}
          </div>
        </div>

        <div className="mb-8">
          <h3 className="text-sm font-bold text-gray-400 uppercase tracking-wider mb-2">Billed To</h3>
          <p className="font-semibold text-lg">{invoice.customer_name || 'Walk-in Customer'}</p>
          {invoice.customer_email && <p className="text-gray-500">{invoice.customer_email}</p>}
        </div>

        <table className="w-full text-left mb-8">
          <thead>
            <tr className="border-b-2 border-gray-900 text-sm">
              <th className="py-3 font-bold">Item</th>
              <th className="py-3 font-bold text-right">Qty</th>
              <th className="py-3 font-bold text-right">Price</th>
              <th className="py-3 font-bold text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            {invoice.lines?.map((line: any, idx: number) => (
              <tr key={idx} className="border-b border-gray-200">
                <td className="py-4">
                  <div className="font-semibold">{line.product_name}</div>
                  {line.sku && <div className="text-xs text-gray-500">{line.sku}</div>}
                  {line.generic_name && <div className="text-xs text-rose-500 font-medium mt-1">💊 Generic: {line.generic_name}</div>}
                  {line.dosage_instructions && <div className="text-xs text-blue-500 font-medium">📋 Dosage: {line.dosage_instructions}</div>}
                  {line.doctor_name && <div className="text-xs text-purple-500 font-medium">🛡️ Rx: {line.doctor_name} (Patient: {line.patient_id})</div>}
                  {line.sourcing_status === 'pending_sourcing' && <div className="text-xs text-amber-600 font-bold mt-1">⚠️ SOURCING REQUIRED</div>}
                </td>
                <td className="py-4 text-right">{line.quantity}</td>
                <td className="py-4 text-right">${parseFloat(line.unit_price).toFixed(2)}</td>
                <td className="py-4 text-right font-bold">${parseFloat(line.total).toFixed(2)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <div className="flex justify-end">
          <div className="w-64">
            <div className="flex justify-between py-2 text-sm text-gray-600">
              <span>Subtotal</span>
              <span>${parseFloat(invoice.total_before_tax || invoice.final_total).toFixed(2)}</span>
            </div>
            <div className="flex justify-between py-2 text-sm text-gray-600 border-b border-gray-200">
              <span>Tax</span>
              <span>${parseFloat(invoice.tax_amount || 0).toFixed(2)}</span>
            </div>
            <div className="flex justify-between py-4 text-xl font-black text-gray-900">
              <span>Total</span>
              <span>${parseFloat(invoice.final_total).toFixed(2)}</span>
            </div>
          </div>
        </div>

        <div className="mt-12 text-center text-sm text-gray-500 border-t border-gray-200 pt-6">
          <p>Thank you for your business!</p>
        </div>
      </div>
    </div>
  );
}
