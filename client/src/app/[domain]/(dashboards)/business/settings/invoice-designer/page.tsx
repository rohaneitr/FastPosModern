'use client';

import React, { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';

export default function InvoiceDesignerPage() {
  const router = useRouter();
  
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);

  const [settings, setSettings] = useState({
    invoice_prefix: 'INV-',
    invoice_header_text: '',
    invoice_footer_text: 'Thank you for your business!',
    show_logo: true,
    show_address: true,
    show_tax_number: false,
    show_due_balance: true,
    show_barcode: true,
    paper_size: '80mm' as '80mm' | 'a4'
  });

  const [businessData, setBusinessData] = useState({ name: 'My Super Store', tax_number_1: 'VAT-123456' });

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    setLoading(true);
    try {
      const res = await api.get('/settings');
      if (res.data?.business) {
        setBusinessData({
          name: res.data.business.name || 'My Super Store',
          tax_number_1: res.data.business.tax_number_1 || 'VAT-123456'
        });
        
        if (res.data.business.settings) {
          const parsed = typeof res.data.business.settings === 'string' 
            ? JSON.parse(res.data.business.settings) 
            : res.data.business.settings;
          
          setSettings({
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
    } catch (e) {
    } finally {
      setLoading(false);
    }
  };

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.post('/settings/invoice', settings);
      showToast('Invoice design saved successfully!', 'success');
    } catch (e: any) {
      showToast('Failed to save settings.', 'error');
    } finally {
      setSaving(false);
    }
  };

  const updateSetting = (key: keyof typeof settings, value: any) => {
    setSettings(prev => ({ ...prev, [key]: value }));
  };

  if (loading) {
    return <div className="p-12 flex justify-center"><div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div></div>;
  }

  // MOCK DATA FOR PREVIEW
  const mockItems = [
    { name: 'Wireless Mouse', qty: 2, price: 25.00 },
    { name: 'Mechanical Keyboard', qty: 1, price: 85.00 },
  ];
  const subtotal = 135.00;
  const tax = 0.00;
  const total = 135.00;
  const paid = 100.00;
  const due = 35.00;

  return (
    <div className="flex flex-col h-full gap-6 animate-in fade-in duration-500 pb-12">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-red-500/20 text-red-300 border-red-500/50'}`}>
          {toast.type === 'success' ? '✓' : '✕'} {toast.message}
        </div>
      )}

      <div className="flex justify-between items-start">
        <div>
          <button onClick={() => router.back()} className="text-sm text-text-muted hover:text-white mb-2 block transition-colors">&larr; Back to Settings</button>
          <h1 className="text-3xl font-black text-white">Dynamic Invoice Designer</h1>
          <p className="text-text-muted mt-1">Configure the look and feel of your thermal receipts and A4 invoices.</p>
        </div>
        <button 
          onClick={handleSave} 
          disabled={saving}
          className="bg-primary hover:bg-primary-hover text-white px-6 py-2.5 rounded-xl font-bold shadow-lg shadow-primary/30 transition-all flex items-center gap-2"
        >
          {saving ? 'Saving...' : '💾 Save Design'}
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-4 items-start">
        {/* Controls Column */}
        <div className="glass-card rounded-2xl border border-white/5 p-6 flex flex-col gap-6">
          <h2 className="text-xl font-bold border-b border-border pb-4">Configuration</h2>
          
          <div className="flex flex-col gap-5">
            <div>
              <label className="block text-sm font-semibold text-text-muted mb-2">Paper Format</label>
              <div className="flex gap-4">
                <label className={`flex-1 flex flex-col items-center p-4 border rounded-xl cursor-pointer transition-all ${settings.paper_size === '80mm' ? 'bg-primary/20 border-primary shadow-lg shadow-primary/20' : 'bg-surface border-border hover:bg-white/5'}`}>
                  <input type="radio" className="hidden" checked={settings.paper_size === '80mm'} onChange={() => updateSetting('paper_size', '80mm')} />
                  <span className="text-3xl mb-2">🧾</span>
                  <span className="font-semibold text-sm text-center">80mm POS Receipt</span>
                </label>
                <label className={`flex-1 flex flex-col items-center p-4 border rounded-xl cursor-pointer transition-all ${settings.paper_size === 'a4' ? 'bg-primary/20 border-primary shadow-lg shadow-primary/20' : 'bg-surface border-border hover:bg-white/5'}`}>
                  <input type="radio" className="hidden" checked={settings.paper_size === 'a4'} onChange={() => updateSetting('paper_size', 'a4')} />
                  <span className="text-3xl mb-2">📄</span>
                  <span className="font-semibold text-sm text-center">A4 Standard Invoice</span>
                </label>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-semibold text-text-muted mb-2">Invoice Prefix</label>
                <input 
                  type="text" 
                  value={settings.invoice_prefix} 
                  onChange={e => updateSetting('invoice_prefix', e.target.value)}
                  className="w-full bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary"
                  placeholder="e.g. INV-"
                />
              </div>
            </div>

            <div className="space-y-4 pt-4 border-t border-border">
              <h3 className="text-sm font-semibold text-text-muted uppercase tracking-wider">Visibility Toggles</h3>
              {[
                { key: 'show_logo', label: 'Show Store Logo' },
                { key: 'show_address', label: 'Show Store Address' },
                { key: 'show_tax_number', label: 'Show Tax/VAT Number' },
                { key: 'show_due_balance', label: 'Show Customer Due Balance' },
                { key: 'show_barcode', label: 'Show Invoice Barcode/QR' },
              ].map(toggle => (
                <label key={toggle.key} className="flex items-center justify-between cursor-pointer group">
                  <span className="text-white group-hover:text-primary transition-colors font-medium">{toggle.label}</span>
                  <div className={`w-12 h-6 rounded-full transition-colors relative ${settings[toggle.key as keyof typeof settings] ? 'bg-emerald-500' : 'bg-surface border border-border'}`}>
                    <div className={`absolute top-1 w-4 h-4 rounded-full bg-white transition-all shadow-md ${settings[toggle.key as keyof typeof settings] ? 'left-7' : 'left-1'}`}></div>
                  </div>
                  <input 
                    type="checkbox" 
                    className="hidden" 
                    checked={settings[toggle.key as keyof typeof settings] as boolean} 
                    onChange={e => updateSetting(toggle.key as keyof typeof settings, e.target.checked)} 
                  />
                </label>
              ))}
            </div>

            <div className="space-y-4 pt-4 border-t border-border">
              <h3 className="text-sm font-semibold text-text-muted uppercase tracking-wider">Custom Text</h3>
              <div>
                <label className="block text-sm font-semibold text-text-muted mb-2">Header Message (Optional)</label>
                <input 
                  type="text" 
                  value={settings.invoice_header_text} 
                  onChange={e => updateSetting('invoice_header_text', e.target.value)}
                  className="w-full bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary"
                  placeholder="e.g. Tax Invoice"
                />
              </div>
              <div>
                <label className="block text-sm font-semibold text-text-muted mb-2">Footer / Terms & Conditions</label>
                <textarea 
                  value={settings.invoice_footer_text} 
                  onChange={e => updateSetting('invoice_footer_text', e.target.value)}
                  className="w-full bg-background border border-border rounded-lg px-4 py-2.5 text-white outline-none focus:border-primary min-h-[100px]"
                  placeholder="e.g. Goods once sold cannot be returned..."
                />
              </div>
            </div>
          </div>
        </div>

        {/* Live Preview Column */}
        <div className="flex flex-col items-center">
          <h2 className="text-xl font-bold mb-6 text-text-muted flex items-center gap-2">
            <span className="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
            Live Preview
          </h2>
          
          <div className="bg-gradient-to-b from-gray-900 to-black p-8 rounded-3xl w-full flex justify-center shadow-2xl border border-white/10 relative overflow-hidden">
            <div className="absolute inset-0 opacity-10 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-primary to-transparent"></div>
            
            {/* The Mock Receipt */}
            <div className={`bg-white text-black relative z-10 shadow-2xl transition-all duration-300 ${settings.paper_size === '80mm' ? 'w-[320px] p-6' : 'w-[500px] p-10'}`}>
              
              {/* Header Section */}
              <div className="text-center mb-6">
                {settings.show_logo && (
                  <div className="w-16 h-16 bg-gray-200 rounded-full mx-auto mb-3 flex items-center justify-center font-bold text-gray-400">LOGO</div>
                )}
                <h1 className="font-bold text-2xl">{businessData.name}</h1>
                {settings.show_address && (
                  <p className="text-xs text-gray-600 mt-1">123 Main Street, Tech Park<br/>City, State 12345</p>
                )}
                {settings.show_tax_number && (
                  <p className="text-xs text-gray-600 mt-1">VAT/Tax No: {businessData.tax_number_1}</p>
                )}
                {settings.invoice_header_text && (
                  <p className="text-sm font-bold mt-3 uppercase border-b border-t border-dashed border-gray-400 py-1">{settings.invoice_header_text}</p>
                )}
              </div>

              {/* Meta Data */}
              <div className="text-xs mb-4 flex justify-between">
                <div>
                  <p>Invoice: <b>{settings.invoice_prefix}10042</b></p>
                  <p>Date: {new Date().toLocaleDateString()}</p>
                </div>
                <div className="text-right">
                  <p>Customer: John Doe</p>
                </div>
              </div>

              {/* Items Table */}
              <div className="mb-4">
                <div className="w-full overflow-x-auto">
<table className="w-full text-left whitespace-nowrap">
  <thead className="bg-surface/50 border-b border-border">
    <tr>
      <th className="px-6 py-4 font-semibold text-text-muted capitalize">qty</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">name</th>
              <th className="px-6 py-4 font-semibold text-text-muted capitalize">price</th>
              <th className="px-6 py-4 font-semibold text-text-muted text-right">total</th>
    </tr>
  </thead>
  <tbody className="divide-y divide-border">
    {(mockItems || [])?.length > 0 ? (
      (mockItems || []).map((item: any, index: number) => (
      <tr key={index} className="hover:bg-surface/30 transition-colors group">
        <td className="px-6 py-4 text-white font-medium">{item.qty}</td>
                <td className="px-6 py-4 text-white font-medium">{item.name}</td>
                <td className="px-6 py-4 text-white font-medium">${item.price.toFixed(2)}</td>
                <td className="px-6 py-4 text-right">${(item.qty * item.price).toFixed(2)}</td>
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

              {/* Totals */}
              <div className="flex justify-end mb-6">
                <div className="w-2/3 text-sm">
                  <div className="flex justify-between py-1"><span>Subtotal:</span><span>${subtotal.toFixed(2)}</span></div>
                  <div className="flex justify-between py-1 border-b border-gray-300"><span>Tax:</span><span>${tax.toFixed(2)}</span></div>
                  <div className="flex justify-between py-2 font-bold text-lg"><span>Total:</span><span>${total.toFixed(2)}</span></div>
                  <div className="flex justify-between py-1 text-xs"><span>Paid:</span><span>${paid.toFixed(2)}</span></div>
                  <div className="flex justify-between py-1 text-xs font-bold"><span>Due:</span><span>${due.toFixed(2)}</span></div>
                </div>
              </div>

              {/* Customer Due Balance Toggle */}
              {settings.show_due_balance && (
                <div className="mb-6 p-3 bg-gray-100 rounded text-center border border-gray-200">
                  <p className="text-xs text-gray-500 uppercase tracking-wide">Previous Due Balance</p>
                  <p className="font-bold text-lg text-red-600">$150.00</p>
                </div>
              )}

              {/* Footer & Barcode */}
              <div className="text-center mt-8">
                {settings.show_barcode && (
                  <div className="mb-4 flex flex-col items-center">
                    <div className="w-48 h-12 bg-[repeating-linear-gradient(90deg,#000,#000_2px,transparent_2px,transparent_4px)]"></div>
                    <span className="text-[10px] font-mono mt-1">{settings.invoice_prefix}10042</span>
                  </div>
                )}
                {settings.invoice_footer_text && (
                  <p className="text-xs text-gray-500 whitespace-pre-wrap">{settings.invoice_footer_text}</p>
                )}
              </div>
            </div>
            {/* End Mock Receipt */}
          </div>
        </div>
      </div>
    </div>
  );
}
