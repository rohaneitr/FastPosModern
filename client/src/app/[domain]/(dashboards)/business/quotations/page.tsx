'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useCurrency } from '@/lib/currency';

export default function QuotationsPage() {
  const [products, setProducts] = useState<any[]>([]);
  const [contacts, setContacts] = useState<any[]>([]);
  const [quotations, setQuotations] = useState<any[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [view, setView] = useState<'list' | 'create'>('list');

  // Quotation Cart State
  const [items, setItems] = useState<any[]>([]);
  const [contactId, setContactId] = useState('');
  const [taxRate, setTaxRate] = useState(0.10); // 10% demo
  const [isSaving, setIsSaving] = useState(false);

  const { format } = useCurrency();
  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);

  // Global Print Settings
  const [invoiceSettings, setInvoiceSettings] = useState({
    invoice_prefix: 'INV-',
    invoice_header_text: '',
    invoice_footer_text: 'Thank you for your business!',
    show_logo: true,
    show_address: true,
    show_tax_number: false,
    show_due_balance: true,
    show_barcode: true,
    paper_size: '80mm'
  });
  const [businessData, setBusinessData] = useState({ name: 'FastPOS', tax_number_1: '' });
  const [receiptData, setReceiptData] = useState<any>(null);

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  useEffect(() => {
    fetchQuotations();
    fetchProducts();
    fetchContacts();
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      const res = await api.get('/settings');
      if (res.data?.business) {
        setBusinessData({
          name: res.data.business.name || 'FastPOS',
          tax_number_1: res.data.business.tax_number_1 || ''
        });
        if (res.data.business.settings) {
          const parsed = typeof res.data.business.settings === 'string' ? JSON.parse(res.data.business.settings) : res.data.business.settings;
          setInvoiceSettings({
            invoice_prefix: parsed.invoice_prefix ?? 'INV-',
            invoice_header_text: parsed.invoice_header_text ?? '',
            invoice_footer_text: parsed.invoice_footer_text ?? 'Thank you for your business!',
            show_logo: parsed.show_logo ?? true,
            show_address: parsed.show_address ?? true,
            show_tax_number: parsed.show_tax_number ?? false,
            show_due_balance: parsed.show_due_balance ?? true,
            show_barcode: parsed.show_barcode ?? true,
            paper_size: parsed.paper_size ?? '80mm'
          });
        }
      }
    } catch(e) {}
  };

  const fetchQuotations = async () => {
    try {
      const res = await api.get('/sales?status=quotation');
      setQuotations(res.data?.data || []);
    } catch (err) {
    } finally {
      setIsLoading(false);
    }
  };

  const fetchProducts = async () => {
    try {
      const res = await api.get('/products');
      setProducts(res.data?.data || res.data || []);
    } catch (err) {
    }
  };

  const fetchContacts = async () => {
    try {
      const res = await api.get('/contacts?type=customer');
      setContacts(res.data?.data || res.data || []);
    } catch (err) {
    }
  };

  const addItem = (product: any) => {
    setItems(prev => {
      const existing = prev.find(i => i.product_id === product.id);
      if (existing) {
        return prev.map(i => i.product_id === product.id ? { ...i, quantity: i.quantity + 1 } : i);
      }
      return [...prev, { product_id: product.id, name: product.name, price: product.price, quantity: 1 }];
    });
  };

  const updateQuantity = (id: number, qty: number) => {
    if (qty < 1) return;
    setItems(prev => prev.map(i => i.product_id === id ? { ...i, quantity: qty } : i));
  };

  const removeItem = (id: number) => {
    setItems(prev => prev.filter(i => i.product_id !== id));
  };

  const subtotal = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const taxAmount = subtotal * taxRate;
  const total = subtotal + taxAmount;

  const handleSaveQuotation = async () => {
    if (items.length === 0) {
      showToast('Add at least one item', 'error');
      return;
    }
    setIsSaving(true);
    try {
      const userJson = localStorage.getItem('fastpos_user');
      let locationId = null;
      if (userJson) {
        const user = JSON.parse(userJson);
        locationId = user.current_location_id || user.business?.locations?.[0]?.id;
      }

      const payload = {
        location_id: locationId,
        payment_method: 'cash', // Not used for quote, but required by validation
        tax_rate: taxRate,
        contact_id: contactId || undefined,
        save_as_quotation: true,
        items: items.map(i => ({ product_id: i.product_id, quantity: i.quantity, price: i.price }))
      };

      const res = await api.post('/checkout', payload);
      showToast(`Quotation ${res.data.invoice_no} Saved!`, 'success');
      
      // Set Receipt Data for Printing
      setReceiptData({
        invoice_no: res.data.invoice_no,
        items: items,
        subtotal,
        taxAmount,
        total,
        paymentMethod: 'Quotation',
        amountPaid: 0,
        isQuotation: true
      });
      
      // Print Native
      setTimeout(() => window.print(), 500);

      setTimeout(() => {
        setView('list');
        setItems([]);
        setContactId('');
        fetchQuotations();
      }, 1000);
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to save quotation', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const handleSendEmail = async (id: number, customerEmail: string | undefined) => {
    const email = prompt("Enter customer email address:", customerEmail || "");
    if (!email) return;

    try {
      await api.post(`/sales/${id}/email`, { email });
      showToast('Email queued for delivery!', 'success');
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to send email', 'error');
    }
  };

  if (view === 'create') {
    return (
      <div className="flex h-full gap-4 relative animate-in fade-in">
        {toast && (
          <div className={`absolute top-4 right-4 px-4 py-3 rounded-xl shadow-2xl z-50 flex items-center gap-3 text-white animate-in slide-in-from-top-2 ${toast.type === 'success' ? 'bg-emerald-500' : 'bg-rose-500'}`}>
            <span>{toast.type === 'success' ? '✅' : '⚠️'}</span>
            <span className="font-semibold">{toast.message}</span>
          </div>
        )}
        
        {/* Left: Product Grid */}
        <div className="flex-[2] flex flex-col gap-4">
          <div className="flex justify-between items-center bg-surface border border-border p-4 rounded-xl">
            <h2 className="text-xl font-bold text-white">Create Quotation</h2>
            <button onClick={() => setView('list')} className="text-sm font-semibold text-text-muted hover:text-white bg-background border border-border px-4 py-2 rounded-lg">Cancel / Back</button>
          </div>
          <div className="flex-1 overflow-y-auto grid grid-cols-1 md:grid-cols-3 gap-3 pr-2 custom-scrollbar">
            {products.map(p => (
              <button key={p.id} onClick={() => addItem(p)} className="bg-surface border border-border rounded-xl p-4 text-left hover:border-primary/50 transition-all group flex flex-col h-32 relative overflow-hidden">
                <div className="font-bold text-white text-sm line-clamp-2 leading-tight group-hover:text-primary transition-colors">{p.name}</div>
                <div className="text-xs text-text-muted mt-1 opacity-70">{p.sku}</div>
                <div className="mt-auto pt-2 flex justify-between items-end">
                  <div className="font-mono text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded text-sm">{format(p.price)}</div>
                </div>
              </button>
            ))}
          </div>
        </div>

        {/* Right: Quotation Cart */}
        <div className="flex-1 bg-surface border border-border rounded-2xl flex flex-col overflow-hidden shadow-2xl">
          <div className="p-4 border-b border-border bg-background/50 flex justify-between items-center">
            <h2 className="font-bold text-white flex items-center gap-2"><span className="text-primary text-xl">📄</span> Estimate</h2>
            <button onClick={() => setItems([])} className="text-xs font-bold text-danger hover:underline">Clear</button>
          </div>
          
          <div className="flex-1 p-4 overflow-y-auto flex flex-col gap-3">
            {items.map(item => (
              <div key={item.product_id} className="bg-background border border-border rounded-lg p-3 flex flex-col gap-2">
                <div className="flex justify-between items-start">
                  <div className="font-medium truncate pr-2">{item.name}</div>
                  <button onClick={() => removeItem(item.product_id)} className="text-text-muted hover:text-danger text-sm">✕</button>
                </div>
                <div className="flex justify-between items-center mt-1">
                  <div className="flex items-center gap-3 bg-surface rounded-lg p-1 border border-border">
                    <button onClick={() => updateQuantity(item.product_id, item.quantity - 1)} className="w-6 h-6 rounded hover:bg-background text-text-muted hover:text-white">-</button>
                    <span className="text-sm w-4 text-center">{item.quantity}</span>
                    <button onClick={() => updateQuantity(item.product_id, item.quantity + 1)} className="w-6 h-6 rounded hover:bg-background text-text-muted hover:text-white">+</button>
                  </div>
                  <div className="font-semibold text-white">{format(item.price * item.quantity)}</div>
                </div>
              </div>
            ))}
          </div>

          <div className="p-4 border-t border-border bg-background/50">
            <div className="mb-4">
              <label className="block text-xs font-semibold text-text-muted uppercase mb-1">Customer</label>
              <select value={contactId} onChange={e => setContactId(e.target.value)} className="w-full bg-surface border border-border rounded-lg px-3 py-2 text-sm text-white outline-none">
                <option value="">Walk-in Customer</option>
                {contacts.map(c => <option key={c.id} value={c.id}>{c.name || `${c.first_name} ${c.last_name}`}</option>)}
              </select>
            </div>
            
            <div className="space-y-2 mb-4">
              <div className="flex justify-between text-sm text-text-muted"><span>Subtotal</span><span>{format(subtotal)}</span></div>
              <div className="flex justify-between text-sm text-text-muted"><span>Tax (10%)</span><span>{format(taxAmount)}</span></div>
              <div className="flex justify-between font-bold text-xl pt-2 border-t border-border/50 text-white"><span>Quote Total</span><span className="text-primary">{format(total)}</span></div>
            </div>

            <button onClick={handleSaveQuotation} disabled={items.length === 0 || isSaving} className="w-full font-bold py-3 bg-primary hover:bg-primary-hover text-white rounded-xl transition-all shadow-lg flex items-center justify-center gap-2">
              {isSaving ? <span className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span> : 'Save & Print Quotation'}
            </button>
          </div>
        </div>
      </div>
    );
  }

  // List View
  return (
    <div className="p-6 animate-in fade-in">
      {toast && (
        <div className={`fixed bottom-4 right-4 px-4 py-3 rounded-xl shadow-2xl z-50 flex items-center gap-3 text-white animate-in slide-in-from-bottom-2 ${toast.type === 'success' ? 'bg-emerald-500' : 'bg-rose-500'}`}>
          <span>{toast.type === 'success' ? '✅' : '⚠️'}</span>
          <span className="font-semibold">{toast.message}</span>
        </div>
      )}
      
      <div className="flex justify-between items-center mb-8">
        <div>
          <h1 className="text-2xl font-bold text-white">Quotations & Estimates</h1>
          <p className="text-text-muted text-sm mt-1">Manage saved customer quotes.</p>
        </div>
        <button onClick={() => window.location.href = `/${window.location.pathname.split('/')[1]}/business/quotations/pc-builder`} className="bg-primary hover:bg-primary-hover text-white font-bold px-6 py-3 rounded-xl transition-all shadow-lg shadow-primary/30 flex items-center gap-2">
          ⚡ Open PC Builder
        </button>
      </div>

      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        {isLoading ? (
          <div className="p-8 text-center text-text-muted">Loading...</div>
        ) : quotations.length === 0 ? (
          <div className="p-12 text-center text-text-muted">No quotations found.</div>
        ) : (
          <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted uppercase tracking-wider text-xs">Date</th>
      <th className="px-6 py-4 font-semibold text-text-muted uppercase tracking-wider text-xs">Quote No</th>
      <th className="px-6 py-4 font-semibold text-text-muted uppercase tracking-wider text-xs">Customer</th>
      <th className="px-6 py-4 font-semibold text-text-muted uppercase tracking-wider text-xs text-right">Total Amount</th>
      <th className="px-6 py-4 font-semibold text-text-muted text-right">Actions</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {quotations.map((item, index) => (
      <tr key={item.id} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{new Date(item.transaction_date).toLocaleDateString()}</td>
        <td className="px-6 py-4 text-primary font-medium">{item.invoice_no}</td>
        <td className="px-6 py-4 text-white font-medium">{item.customer_name || 'Walk-in'}</td>
        <td className="px-6 py-4 text-right font-bold text-emerald-400">{format(item.final_total)}</td>
        <td className="px-6 py-4 text-right">
          <div className="flex gap-2 justify-end">
            <button onClick={() => handleSendEmail(item.id, item.customer_email)} className="text-white hover:text-emerald-400 font-medium text-sm px-3 py-1 bg-surface border border-border rounded-lg shadow-sm hover:border-emerald-500/50 transition-all flex items-center gap-1">
              ✉️ Email
            </button>
            <button className="text-blue-400 hover:text-blue-300 font-medium text-sm px-3 py-1 bg-blue-500/10 rounded-lg">Print</button>
            <button onClick={() => window.location.href = `/${window.location.pathname.split('/')[1]}/user/pos?load_quotation=${item.id}`} className="text-emerald-400 hover:text-emerald-300 font-bold text-sm px-4 py-1 bg-emerald-500/10 border border-emerald-500/20 rounded-lg shadow-sm hover:shadow-emerald-500/20 transition-all">
              ⚡ Convert to Sale
            </button>
          </div>
        </td>
      </tr>
    ))}
  </tbody>
</table>
</div>
        )}
      </div>

      {/* Hidden Printable Receipt Area (Dynamic Designer Injected) */}
      {receiptData && (
        <div className="hidden print:block absolute top-0 left-0 bg-white text-black z-[9999] p-8 text-sm font-mono w-full min-h-screen">
          <div className={`mx-auto bg-white text-black relative z-10 ${invoiceSettings.paper_size === '80mm' ? 'w-[320px]' : 'w-[700px]'}`}>
            <div className="text-center mb-6">
              {invoiceSettings.show_logo && (
                <div className="w-16 h-16 border-2 border-black rounded-full mx-auto mb-3 flex items-center justify-center font-bold text-black uppercase tracking-widest">LOGO</div>
              )}
              <h1 className="font-bold text-2xl uppercase tracking-wider">{businessData.name}</h1>
              <p className="text-lg font-bold mt-3 uppercase border-b-2 border-t-2 border-dashed border-black py-1">ESTIMATE / QUOTATION</p>
            </div>
            <div className="text-sm mb-4 flex justify-between border-b-2 border-black pb-2">
              <div><p>Quote: <b>{receiptData.invoice_no}</b></p><p>Date: {new Date().toLocaleString()}</p></div>
            </div>
            <div className="w-full overflow-x-auto">

</div>
            <div className="border-t-2 border-black pt-4 w-full ml-auto md:w-2/3 lg:w-1/2 float-right clear-both">
              <div className="flex justify-between mb-1"><span>Subtotal:</span><span>{format(receiptData.subtotal)}</span></div>
              <div className="flex justify-between mb-1"><span>Tax:</span><span>{format(receiptData.taxAmount)}</span></div>
              <div className="flex justify-between font-bold text-2xl mt-2 border-t-2 border-black pt-2 mb-2"><span>Total:</span><span>{format(receiptData.total)}</span></div>
            </div>
            <div className="text-center mt-8 clear-both pt-8">
              {invoiceSettings.show_barcode && (
                <div className="mb-4 flex flex-col items-center">
                  <div className="w-48 h-12 bg-[repeating-linear-gradient(90deg,#000,#000_3px,transparent_3px,transparent_6px)]"></div>
                  <span className="text-xs font-mono mt-1 tracking-widest">{receiptData.invoice_no}</span>
                </div>
              )}
              {invoiceSettings.invoice_footer_text && <p className="text-sm whitespace-pre-wrap font-semibold italic">{invoiceSettings.invoice_footer_text}</p>}
            </div>
          </div>
          <style dangerouslySetInnerHTML={{__html: `@media print { body * { visibility: hidden; } .print\\:block, .print\\:block * { visibility: visible; } .print\\:block { position: absolute; left: 0; top: 0; } @page { size: auto; margin: 0mm; } }`}} />
        </div>
      )}
    </div>
  );
}
