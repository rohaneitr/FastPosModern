'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useCurrency } from '@/lib/currency';
import { useRouter } from 'next/navigation';

const CATEGORY_SLOTS = [
  { id: 'cpu', label: 'Processor (CPU)', icon: '🧠', keyword: 'Processor' },
  { id: 'mobo', label: 'Motherboard', icon: '🛹', keyword: 'Motherboard' },
  { id: 'ram', label: 'Memory (RAM)', icon: '🎞️', keyword: 'RAM' },
  { id: 'storage', label: 'Storage (SSD/HDD)', icon: '💾', keyword: 'SSD' },
  { id: 'gpu', label: 'Graphics Card (GPU)', icon: '🎮', keyword: 'Graphics' },
  { id: 'psu', label: 'Power Supply', icon: '⚡', keyword: 'Power' },
  { id: 'casing', label: 'Casing', icon: '🖥️', keyword: 'Casing' },
  { id: 'cooler', label: 'CPU Cooler', icon: '❄️', keyword: 'Cooler' },
];

export default function PcBuilderPage() {
  const router = useRouter();
  const { format } = useCurrency();
  const [products, setProducts] = useState<any[]>([]);
  const [contacts, setContacts] = useState<any[]>([]);
  const [buildItems, setBuildItems] = useState<Record<string, any>>({});
  const [activeSlot, setActiveSlot] = useState<any>(null);
  const [searchTerm, setSearchTerm] = useState('');
  
  const [contactId, setContactId] = useState('');
  const [isSaving, setIsSaving] = useState(false);
  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);

  const [receiptData, setReceiptData] = useState<any>(null);
  const [businessData, setBusinessData] = useState({ name: 'FastPOS', mobile: '' });

  useEffect(() => {
    // Check SaaS Active Modules Guard
    const userJson = localStorage.getItem('fastpos_user');
    if (userJson) {
      const user = JSON.parse(userJson);
      if (user.business?.active_modules && !user.business.active_modules.includes('pc_builder')) {
        router.replace('/business');
        return;
      }
    }
    fetchProducts();
    fetchContacts();
    fetchSettings();
  }, [router]);

  const fetchSettings = async () => {
    try {
      const res = await api.get('/settings');
      if (res.data?.business) {
        setBusinessData({
          name: res.data.business.name || 'FastPOS',
          mobile: res.data.business.mobile || ''
        });
      }
    } catch(e) {}
  };

  const fetchProducts = async () => {
    try {
      const res = await api.get('/products');
      setProducts(res.data?.data || res.data || []);
    } catch (err) { }
  };

  const fetchContacts = async () => {
    try {
      const res = await api.get('/contacts?type=customer');
      setContacts(res.data?.data || res.data || []);
    } catch (err) { }
  };

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const handleSelectProduct = (product: any) => {
    setBuildItems(prev => ({ ...prev, [activeSlot.id]: { ...product, quantity: 1 } }));
    setActiveSlot(null);
    setSearchTerm('');
  };

  const removeSlot = (slotId: string) => {
    setBuildItems(prev => {
      const copy = { ...prev };
      delete copy[slotId];
      return copy;
    });
  };

  const buildArray = Object.values(buildItems);
  const subtotal = buildArray.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const taxAmount = subtotal * 0.10; // 10% Demo
  const total = subtotal + taxAmount;

  const handleSaveQuotation = async () => {
    if (buildArray.length === 0) return showToast('Please select at least one component', 'error');

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
        payment_method: 'cash',
        tax_rate: 0.10,
        contact_id: contactId || undefined,
        save_as_quotation: true,
        items: buildArray.map(i => ({ product_id: i.id, quantity: i.quantity, price: i.price }))
      };

      const res = await api.post('/checkout', payload);
      
      const customer = contacts.find(c => c.id.toString() === contactId);

      setReceiptData({
        invoice_no: res.data.invoice_no,
        items: buildArray,
        subtotal,
        taxAmount,
        total,
        customerName: customer ? customer.name : 'Walk-in Customer'
      });
      
      showToast('Quotation Saved!', 'success');
      setTimeout(() => window.print(), 500);

    } catch (err: any) {
      showToast('Failed to save quotation', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  // Filter products based on active slot keyword and search term
  const filteredProducts = activeSlot 
    ? products.filter(p => 
        (p.category?.name?.toLowerCase().includes(activeSlot.keyword.toLowerCase()) || 
         p.name.toLowerCase().includes(activeSlot.keyword.toLowerCase()) ||
         p.name.toLowerCase().includes(searchTerm.toLowerCase())) &&
        (searchTerm ? p.name.toLowerCase().includes(searchTerm.toLowerCase()) : true)
      )
    : [];

  return (
    <div className="flex h-full gap-6 animate-in fade-in pb-10">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-[9999] px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-rose-500/20 text-rose-300 border-rose-500/50'}`}>
          {toast.message}
        </div>
      )}

      {/* Main PC Builder Area */}
      <div className="flex-[3] flex flex-col gap-4 overflow-y-auto custom-scrollbar pr-4">
        <div>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-400 uppercase tracking-widest">
            PC Builder Engine
          </h1>
          <p className="text-text-muted mt-1">Configure a custom PC build and generate a professional quotation.</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
          {CATEGORY_SLOTS.map(slot => {
            const selected = buildItems[slot.id];
            return (
              <div key={slot.id} className={`p-4 rounded-2xl border transition-all ${selected ? 'bg-surface/80 border-primary/50' : 'bg-surface/30 border-border hover:border-primary/30'} flex flex-col gap-3 relative overflow-hidden`}>
                {selected && <div className="absolute top-0 right-0 w-20 h-20 bg-primary/10 blur-[30px] rounded-full pointer-events-none"></div>}
                
                <div className="flex justify-between items-center z-10">
                  <div className="flex items-center gap-2">
                    <span className="text-2xl">{slot.icon}</span>
                    <span className="font-bold text-text-muted uppercase text-xs tracking-wider">{slot.label}</span>
                  </div>
                  {selected && (
                    <button onClick={() => removeSlot(slot.id)} className="text-text-muted hover:text-rose-400 transition-colors">✕</button>
                  )}
                </div>

                <div className="z-10">
                  {selected ? (
                    <div className="flex flex-col gap-1">
                      <h3 className="font-bold text-white text-lg leading-tight line-clamp-2">{selected.name}</h3>
                      <div className="font-mono text-emerald-400 font-bold">{format(selected.price)}</div>
                    </div>
                  ) : (
                    <button 
                      onClick={() => { setActiveSlot(slot); setSearchTerm(''); }}
                      className="w-full py-4 rounded-xl border-2 border-dashed border-border hover:border-primary/50 hover:bg-primary/5 transition-all text-text-muted hover:text-white font-semibold flex items-center justify-center gap-2"
                    >
                      <span>+</span> Choose {slot.keyword}
                    </button>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Build Summary / Cart Sidebar */}
      <div className="flex-[1.5] bg-surface/50 border border-border rounded-3xl flex flex-col shadow-2xl relative overflow-hidden">
        <div className="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
        <div className="p-6 border-b border-border">
          <h2 className="text-xl font-bold text-white mb-4">Build Summary</h2>
          
          <div className="mb-2">
            <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">Customer / Client</label>
            <select value={contactId} onChange={e => setContactId(e.target.value)} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary transition-colors cursor-pointer">
              <option value="">Walk-in Customer</option>
              {contacts.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
        </div>

        <div className="flex-1 p-6 overflow-y-auto flex flex-col gap-3 custom-scrollbar">
          {buildArray.length === 0 ? (
            <div className="text-center text-text-muted mt-10">
              <div className="text-4xl mb-2">💻</div>
              <p>Your build is empty.</p>
              <p className="text-sm mt-1">Start by adding a processor.</p>
            </div>
          ) : (
            buildArray.map((item: any) => (
              <div key={item.id} className="flex justify-between items-center">
                <div className="flex-1 min-w-0 pr-4">
                  <div className="font-bold text-sm text-white truncate">{item.name}</div>
                  <div className="text-xs text-text-muted">Qty: {item.quantity}</div>
                </div>
                <div className="font-mono text-white font-bold">{format(item.price)}</div>
              </div>
            ))
          )}
        </div>

        <div className="p-6 bg-background/50 border-t border-border">
          <div className="space-y-2 mb-6">
            <div className="flex justify-between text-sm text-text-muted"><span>Subtotal</span><span className="font-mono">{format(subtotal)}</span></div>
            <div className="flex justify-between text-sm text-text-muted"><span>Estimated Tax (10%)</span><span className="font-mono">{format(taxAmount)}</span></div>
            <div className="flex justify-between font-black text-2xl pt-3 border-t border-border/50 text-white mt-2">
              <span>Total</span><span className="text-primary font-mono">{format(total)}</span>
            </div>
          </div>
          <button 
            onClick={handleSaveQuotation}
            disabled={buildArray.length === 0 || isSaving}
            className="w-full bg-primary hover:bg-primary-hover disabled:opacity-50 text-white font-bold py-4 rounded-2xl transition-all shadow-lg flex items-center justify-center gap-2 uppercase tracking-wider text-sm"
          >
            {isSaving ? <span className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span> : 'Generate Quotation'}
          </button>
        </div>
      </div>

      {/* Component Selection Modal */}
      {activeSlot && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-3xl w-full max-w-4xl h-[80vh] shadow-2xl flex flex-col animate-in zoom-in-95 overflow-hidden">
            <div className="p-6 border-b border-border bg-background/50 flex justify-between items-center">
              <div>
                <h2 className="text-2xl font-bold text-white flex items-center gap-2">{activeSlot.icon} Select {activeSlot.label}</h2>
                <p className="text-text-muted text-sm mt-1">Showing compatible components from inventory.</p>
              </div>
              <button onClick={() => setActiveSlot(null)} className="w-10 h-10 rounded-full bg-surface border border-border flex items-center justify-center text-text-muted hover:text-white transition-colors">✕</button>
            </div>
            
            <div className="p-6 border-b border-border">
              <input 
                type="text" 
                placeholder="Search by brand, model, or specs..." 
                value={searchTerm}
                onChange={e => setSearchTerm(e.target.value)}
                autoFocus
                className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-primary transition-colors text-lg"
              />
            </div>

            <div className="flex-1 overflow-y-auto p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 custom-scrollbar">
              {filteredProducts.length === 0 ? (
                <div className="col-span-full py-12 text-center text-text-muted">
                  No {activeSlot.keyword} found matching your search.
                </div>
              ) : (
                filteredProducts.map(p => (
                  <button key={p.id} onClick={() => handleSelectProduct(p)} className="bg-background border border-border hover:border-primary/50 rounded-2xl p-5 text-left transition-all group flex flex-col h-40">
                    <div className="font-bold text-white leading-snug line-clamp-2 group-hover:text-primary transition-colors">{p.name}</div>
                    <div className="text-xs text-text-muted mt-2 opacity-70">Stock: {p.stocks?.[0]?.qty_available || 0}</div>
                    <div className="mt-auto flex justify-between items-center">
                      <div className="font-mono text-emerald-400 font-bold text-lg">{format(p.price)}</div>
                      <div className="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">↓</div>
                    </div>
                  </button>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* Hidden A4 Print Layout */}
      {receiptData && (
        <div className="hidden print:block absolute top-0 left-0 bg-white text-black z-[9999] w-[210mm] min-h-[297mm] p-[20mm] font-sans box-border">
          <div className="flex justify-between items-start border-b-2 border-black pb-8 mb-8">
            <div>
              <h1 className="text-4xl font-black uppercase tracking-widest text-black">{businessData.name}</h1>
              <p className="text-gray-600 mt-2 font-medium">Custom PC Quotation</p>
              <p className="text-gray-600 font-medium">Phone: {businessData.mobile}</p>
            </div>
            <div className="text-right">
              <h2 className="text-3xl font-light text-gray-400 uppercase tracking-widest mb-2">Estimate</h2>
              <p className="font-bold text-lg">#{receiptData.invoice_no}</p>
              <p className="text-gray-600 mt-1">Date: {new Date().toLocaleDateString()}</p>
              <p className="text-rose-600 font-bold mt-1">Valid Until: {new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toLocaleDateString()}</p>
            </div>
          </div>

          <div className="mb-8">
            <h3 className="font-bold text-gray-500 uppercase tracking-wider text-sm mb-2">Prepared For:</h3>
            <p className="font-bold text-xl">{receiptData.customerName}</p>
          </div>

          <table className="w-full text-left border-collapse mb-8">
            <thead>
              <tr className="border-b-2 border-black">
                <th className="py-3 font-bold uppercase tracking-wider text-sm">Component</th>
                <th className="py-3 font-bold uppercase tracking-wider text-sm">Description</th>
                <th className="py-3 font-bold uppercase tracking-wider text-sm text-center">Qty</th>
                <th className="py-3 font-bold uppercase tracking-wider text-sm text-right">Price</th>
                <th className="py-3 font-bold uppercase tracking-wider text-sm text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              {receiptData.items.map((item: any, idx: number) => {
                // Find matching slot for nice category name
                const slotId = Object.keys(buildItems).find(k => buildItems[k].id === item.id);
                const category = CATEGORY_SLOTS.find(s => s.id === slotId)?.label || 'Component';
                
                return (
                  <tr key={idx} className="border-b border-gray-200">
                    <td className="py-4 font-bold text-sm text-gray-600">{category}</td>
                    <td className="py-4 font-semibold max-w-[250px]">{item.name}</td>
                    <td className="py-4 text-center">{item.quantity}</td>
                    <td className="py-4 text-right">{format(item.price)}</td>
                    <td className="py-4 text-right font-bold">{format(item.price * item.quantity)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>

          <div className="flex justify-end">
            <div className="w-1/2">
              <div className="flex justify-between py-2 text-gray-600 font-medium">
                <span>Subtotal</span>
                <span>{format(receiptData.subtotal)}</span>
              </div>
              <div className="flex justify-between py-2 text-gray-600 font-medium">
                <span>Tax (10%)</span>
                <span>{format(receiptData.taxAmount)}</span>
              </div>
              <div className="flex justify-between py-4 border-t-2 border-black mt-2">
                <span className="font-black text-2xl uppercase tracking-wider">Total</span>
                <span className="font-black text-2xl">{format(receiptData.total)}</span>
              </div>
            </div>
          </div>

          <div className="absolute bottom-[20mm] left-[20mm] right-[20mm] text-center border-t border-gray-300 pt-4">
            <p className="text-gray-500 text-sm font-medium italic">
              "This is a price estimate, subject to stock availability and market price fluctuations."
            </p>
          </div>
        </div>
      )}
      
      <style dangerouslySetInnerHTML={{__html: `
        @media print { 
          body * { visibility: hidden; } 
          .print\\:block, .print\\:block * { visibility: visible; } 
          .print\\:block { position: absolute; left: 0; top: 0; } 
          @page { size: A4; margin: 0; } 
          html, body { background: white !important; }
        }
      `}} />
    </div>
  );
}
