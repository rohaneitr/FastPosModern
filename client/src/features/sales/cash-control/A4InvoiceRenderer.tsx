'use client';

import React from 'react';

interface SerialData {
    serial_number: string;
}

interface InvoiceItem {
    id: number;
    product_name: string;
    quantity: number;
    unit_price: string;
    unit_price_inc_tax: string;
    item_tax: string;
    subtotal: string;
    serials?: SerialData[];
}

interface InvoiceData {
    invoice_no: string;
    transaction_date: string;
    document_type: 'Invoice' | 'ProformaInvoice' | 'Quotation';
    total_before_tax: string;
    tax_amount: string;
    discount_amount: string;
    final_total: string;
    amount_due: string;
    payment_status: string;
    items: InvoiceItem[];
    customer?: {
        name: string;
        mobile: string;
        address: string;
    };
    business: {
        name: string;
        address: string;
        phone: string;
        email: string;
    };
}

interface A4InvoiceRendererProps {
    invoice: InvoiceData;
    onClose: () => void;
}

export function A4InvoiceRenderer({ invoice, onClose }: A4InvoiceRendererProps) {
    const handlePrint = () => {
        window.print();
    };

    const getDocumentTitle = () => {
        switch (invoice.document_type) {
            case 'ProformaInvoice': return 'PROFORMA INVOICE';
            case 'Quotation': return 'QUOTATION';
            case 'Invoice':
            default: return 'TAX INVOICE';
        }
    };

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/80 backdrop-blur-sm overflow-y-auto py-10">
            {/* The Print Media Firewall */}
            <style dangerouslySetInnerHTML={{__html: `
                @media print {
                    @page { size: A4 portrait; margin: 15mm; }
                    body * { visibility: hidden; }
                    .a4-print-zone, .a4-print-zone * { visibility: visible; }
                    .a4-print-zone { 
                        position: absolute; 
                        left: 0; 
                        top: 0; 
                        width: 100%; 
                        padding: 0;
                        background: white;
                    }
                    table { width: 100%; border-collapse: collapse; }
                    thead { display: table-header-group; }
                    tfoot { display: table-footer-group; }
                    tr.avoid-break { page-break-inside: avoid; }
                    
                    /* Hide modal UI controls during physical print */
                    .no-print { display: none !important; }
                }
            `}} />

            <div className="w-full max-w-[210mm] bg-white shadow-2xl relative min-h-[297mm]">
                
                {/* Floating Actions Strip (No Print) */}
                <div className="absolute top-0 left-full ml-4 flex flex-col space-y-3 no-print">
                    <button onClick={handlePrint} className="bg-indigo-600 text-white p-3 rounded-full shadow-lg hover:bg-indigo-700 transition" title="Print A4 Document">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                    </button>
                    <button onClick={onClose} className="bg-gray-800 text-white p-3 rounded-full shadow-lg hover:bg-gray-900 transition" title="Close">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* The Physical A4 Render Grid */}
                <div className="a4-print-zone p-[15mm] text-gray-900 text-sm">
                    {/* Corporate Header */}
                    <div className="flex justify-between items-start border-b-2 border-gray-900 pb-6 mb-6">
                        <div>
                            <h1 className="text-3xl font-black tracking-tighter text-gray-900">{invoice.business.name}</h1>
                            <p className="text-gray-600 mt-2 whitespace-pre-wrap">{invoice.business.address}</p>
                            <p className="text-gray-600">Tel: {invoice.business.phone}</p>
                            <p className="text-gray-600">Email: {invoice.business.email}</p>
                        </div>
                        <div className="text-right">
                            <h2 className="text-2xl font-bold uppercase tracking-widest text-indigo-700">{getDocumentTitle()}</h2>
                            <div className="mt-4 flex flex-col items-end">
                                <p className="font-semibold text-lg">#{invoice.invoice_no}</p>
                                <p className="text-gray-500">Date: {new Date(invoice.transaction_date).toLocaleDateString()}</p>
                                {invoice.document_type === 'Invoice' && (
                                    <span className={`mt-2 px-3 py-1 rounded text-xs font-bold uppercase ${
                                        invoice.payment_status === 'paid' ? 'bg-green-100 text-green-800' : 
                                        invoice.payment_status === 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'
                                    }`}>
                                        STATUS: {invoice.payment_status}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Client Information Block */}
                    <div className="mb-8 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                        <h3 className="font-bold text-xs uppercase tracking-widest text-gray-500 mb-2">Billed To</h3>
                        <p className="font-bold text-gray-900 text-lg">{invoice.customer?.name || 'Walk-in Customer'}</p>
                        {invoice.customer?.mobile && <p className="text-gray-600">Mobile: {invoice.customer.mobile}</p>}
                        {invoice.customer?.address && <p className="text-gray-600">Address: {invoice.customer.address}</p>}
                    </div>

                    {/* Line Items Table */}
                    <table className="w-full text-left mb-8">
                        <thead>
                            <tr className="border-b-2 border-gray-900 bg-gray-50 text-gray-900 uppercase text-xs tracking-wider">
                                <th className="py-3 px-2">Description & Serials</th>
                                <th className="py-3 px-2 text-center">Qty</th>
                                <th className="py-3 px-2 text-right">Unit Price</th>
                                <th className="py-3 px-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {invoice.items.map((item, idx) => (
                                <tr key={idx} className="avoid-break group">
                                    <td className="py-4 px-2">
                                        <p className="font-bold text-gray-900">{item.product_name}</p>
                                        
                                        {/* Relational Serialization Rendering Block */}
                                        {item.serials && item.serials.length > 0 && (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                <span className="text-xs text-gray-500 flex items-center">S/N:</span>
                                                {item.serials.map((s, sIdx) => (
                                                    <span key={sIdx} className="bg-gray-100 border border-gray-300 text-gray-700 px-2 py-0.5 rounded text-xs font-mono tracking-tight">
                                                        {s.serial_number}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </td>
                                    <td className="py-4 px-2 text-center font-medium">{item.quantity}</td>
                                    <td className="py-4 px-2 text-right">${parseFloat(item.unit_price).toFixed(2)}</td>
                                    <td className="py-4 px-2 text-right font-bold text-gray-900">${parseFloat(item.subtotal).toFixed(2)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {/* Financial Summaries */}
                    <div className="flex justify-end avoid-break">
                        <div className="w-1/2 border-t border-gray-300 pt-4">
                            <div className="flex justify-between py-1">
                                <span className="text-gray-600 font-medium">Subtotal:</span>
                                <span className="font-semibold text-gray-900">${parseFloat(invoice.total_before_tax).toFixed(2)}</span>
                            </div>
                            {parseFloat(invoice.discount_amount) > 0 && (
                                <div className="flex justify-between py-1">
                                    <span className="text-gray-600 font-medium">Discount:</span>
                                    <span className="font-semibold text-red-600">-${parseFloat(invoice.discount_amount).toFixed(2)}</span>
                                </div>
                            )}
                            <div className="flex justify-between py-1">
                                <span className="text-gray-600 font-medium">Tax/VAT:</span>
                                <span className="font-semibold text-gray-900">${parseFloat(invoice.tax_amount).toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between py-3 mt-2 border-t-2 border-gray-900">
                                <span className="text-lg font-bold text-gray-900">Grand Total:</span>
                                <span className="text-xl font-black text-indigo-700">${parseFloat(invoice.final_total).toFixed(2)}</span>
                            </div>
                            
                            {invoice.document_type === 'Invoice' && parseFloat(invoice.amount_due) > 0 && (
                                <div className="flex justify-between py-2 mt-2 bg-red-50 px-3 rounded-lg border border-red-100">
                                    <span className="font-bold text-red-800 uppercase tracking-wider text-xs">Amount Due:</span>
                                    <span className="font-bold text-red-800">${parseFloat(invoice.amount_due).toFixed(2)}</span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Executive Footer Signatures */}
                    <div className="mt-20 flex justify-between items-end border-t border-gray-200 pt-8 avoid-break">
                        <div className="w-1/3 text-center">
                            <div className="border-b border-gray-400 pb-8 mb-2"></div>
                            <p className="text-xs text-gray-500 uppercase tracking-widest">Customer Signature</p>
                        </div>
                        <div className="w-1/3 text-center">
                            <div className="border-b border-gray-400 pb-8 mb-2"></div>
                            <p className="text-xs text-gray-500 uppercase tracking-widest">Authorized Signature</p>
                        </div>
                    </div>

                    {/* Disclaimers */}
                    <div className="mt-8 text-center text-xs text-gray-400 avoid-break">
                        <p>This is a computer generated document. No signature is strictly required if printed directly from the FastPOS System.</p>
                        {invoice.document_type === 'Quotation' && (
                            <p className="mt-1 font-bold text-gray-500">This quotation is valid for 30 days from the date of issue.</p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
