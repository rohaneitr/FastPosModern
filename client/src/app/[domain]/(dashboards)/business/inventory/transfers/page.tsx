'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function StockTransfersPage() {
  const [transfers, setTransfers] = useState<any[]>([]);
  const [locations, setLocations] = useState<any[]>([]);
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  
  const [view, setView] = useState<'list' | 'create'>('list');
  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);

  // Create Transfer State
  const [form, setForm] = useState({
    from_location_id: '',
    to_location_id: '',
    notes: ''
  });
  const [items, setItems] = useState<any[]>([]);
  const [productSearch, setProductSearch] = useState('');
  
  // Serial Scan Modal State
  const [activeItemIndex, setActiveItemIndex] = useState<number | null>(null);
  const [serialInput, setSerialInput] = useState('');

  useEffect(() => {
    if (view === 'list') {
      fetchTransfers();
    } else {
      fetchLocations();
      fetchProducts();
    }
  }, [view]);

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({message, type});
    setTimeout(() => setToast(null), 3000);
  };

  const fetchTransfers = async () => {
    setLoading(true);
    try {
      const res = await api.get('/inventory/transfers');
      setTransfers(res.data.data || res.data || []);
    } catch (err) {
      showToast('Failed to load transfers', 'error');
    } finally {
      setLoading(false);
    }
  };

  const fetchLocations = async () => {
    try {
      const res = await api.get('/locations');
      setLocations(res.data.data || res.data || []);
    } catch (err) {}
  };

  const fetchProducts = async () => {
    try {
      const res = await api.get('/products');
      setProducts(res.data.data || res.data || []);
    } catch (err) {}
  };

  const handleUpdateStatus = async (id: number, status: string) => {
    if (!confirm(`Mark this transfer as ${status.replace('_', ' ')}?`)) return;
    try {
      await api.put(`/inventory/transfers/${id}/status`, { status });
      showToast('Status updated successfully', 'success');
      fetchTransfers();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to update status', 'error');
    }
  };

  const handleAddProduct = (p: any) => {
    setItems([...items, { product: p, product_id: p.id, quantity: 1, serial_numbers: [] }]);
    setProductSearch('');
  };

  const handleUpdateQuantity = (index: number, val: number) => {
    const newItems = [...items];
    newItems[index].quantity = val;
    // Reset serials if quantity changes to be safe, or just truncate
    if (newItems[index].product.has_serial_number && newItems[index].serial_numbers.length > val) {
      newItems[index].serial_numbers = newItems[index].serial_numbers.slice(0, val);
    }
    setItems(newItems);
  };

  const handleRemoveItem = (index: number) => {
    setItems(items.filter((_, i) => i !== index));
  };

  const handleAddSerial = (e: React.FormEvent) => {
    e.preventDefault();
    if (activeItemIndex === null || !serialInput.trim()) return;
    
    const newItems = [...items];
    const targetItem = newItems[activeItemIndex];
    
    if (targetItem.serial_numbers.includes(serialInput.trim())) {
      showToast('Serial already scanned', 'error');
      return;
    }
    
    if (targetItem.serial_numbers.length >= targetItem.quantity) {
      showToast('Required quantity of serials already met', 'error');
      return;
    }

    targetItem.serial_numbers.push(serialInput.trim());
    setItems(newItems);
    setSerialInput('');
  };

  const handleRemoveSerial = (itemIndex: number, serial: string) => {
    const newItems = [...items];
    newItems[itemIndex].serial_numbers = newItems[itemIndex].serial_numbers.filter((s: string) => s !== serial);
    setItems(newItems);
  };

  const handleCreateTransfer = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (items.length === 0) return showToast('Add at least one product', 'error');
    
    // Validate serials
    for (let item of items) {
      if (item.product.has_serial_number && item.serial_numbers.length !== item.quantity) {
        return showToast(`Please scan exact ${item.quantity} serials for ${item.product.name}`, 'error');
      }
    }

    try {
      await api.post('/inventory/transfers', {
        ...form,
        items: items.map(i => ({
          product_id: i.product_id,
          quantity: i.quantity,
          serial_numbers: i.serial_numbers
        }))
      });
      showToast('Transfer Initiated Successfully', 'success');
      setView('list');
      setForm({ from_location_id: '', to_location_id: '', notes: '' });
      setItems([]);
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to initiate transfer', 'error');
    }
  };

  const filteredProducts = products.filter(p => p.name.toLowerCase().includes(productSearch.toLowerCase()));

  return (
    <div className="flex flex-col h-full gap-6 animate-in fade-in pb-10 relative">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-rose-500/20 text-rose-300 border-rose-500/50'}`}>
          {toast.message}
        </div>
      )}

      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-indigo-400 uppercase tracking-widest">
            {view === 'list' ? 'Stock Transfers' : 'New Transfer'}
          </h1>
          <p className="text-text-muted mt-1">
            {view === 'list' ? 'Manage multi-branch logistics and serial tracking.' : 'Initiate a secure hardware stock movement.'}
          </p>
        </div>
        <div>
          {view === 'list' ? (
            <button onClick={() => setView('create')} className="bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-400 hover:to-indigo-400 text-white px-6 py-2.5 rounded-xl font-bold shadow-[0_0_15px_rgba(59,130,246,0.4)] transition-all transform hover:scale-105 active:scale-95 flex items-center gap-2">
              <span>+</span> Initiate Transfer
            </button>
          ) : (
            <button onClick={() => setView('list')} className="bg-surface hover:bg-surface/80 text-white px-6 py-2.5 rounded-xl font-bold border border-border transition-all flex items-center gap-2">
              Cancel
            </button>
          )}
        </div>
      </div>

      {view === 'list' && (
        <div className="glass-card rounded-2xl overflow-hidden border border-border shadow-xl">
          <div className="overflow-x-auto">
            <table className="w-full text-left whitespace-nowrap">
              <thead className="bg-surface border-b border-border text-xs uppercase tracking-wider font-bold text-text-muted">
                <tr>
                  <th className="px-6 py-4">Reference</th>
                  <th className="px-6 py-4">Source</th>
                  <th className="px-6 py-4">Destination</th>
                  <th className="px-6 py-4">Items Qty</th>
                  <th className="px-6 py-4 text-center">Status</th>
                  <th className="px-6 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border/50">
                {loading ? (
                  <tr><td colSpan={6} className="px-6 py-8 text-center text-text-muted">Loading...</td></tr>
                ) : transfers.length === 0 ? (
                  <tr><td colSpan={6} className="px-6 py-16 text-center text-text-muted">No transfers found.</td></tr>
                ) : (
                  transfers.map(t => (
                    <tr key={t.id} className="hover:bg-surface/30 transition-colors">
                      <td className="px-6 py-4 font-mono font-bold text-blue-400">{t.reference_no}</td>
                      <td className="px-6 py-4 font-medium text-white">{t.from_location?.name}</td>
                      <td className="px-6 py-4 font-medium text-white">{t.to_location?.name}</td>
                      <td className="px-6 py-4 font-bold">{t.total_items}</td>
                      <td className="px-6 py-4 text-center">
                        <span className={`px-3 py-1 rounded-full text-xs font-bold border ${
                          t.status === 'completed' ? 'bg-success/10 text-success border-success/20' :
                          t.status === 'in_transit' ? 'bg-amber-500/10 text-amber-500 border-amber-500/20' :
                          'bg-surface text-text-muted border-border'
                        }`}>
                          {t.status.toUpperCase().replace('_', ' ')}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-right">
                        {t.status === 'pending' && (
                          <button onClick={() => handleUpdateStatus(t.id, 'in_transit')} className="px-4 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-500 border border-amber-500/30 rounded-xl text-xs font-bold transition-all shadow-sm">
                            Ship Transfer
                          </button>
                        )}
                        {t.status === 'in_transit' && (
                          <button onClick={() => handleUpdateStatus(t.id, 'completed')} className="px-4 py-2 bg-success/10 hover:bg-success/20 text-success border border-success/30 rounded-xl text-xs font-bold transition-all shadow-sm">
                            Receive Stock
                          </button>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {view === 'create' && (
        <form onSubmit={handleCreateTransfer} className="flex gap-6">
          {/* Main Form Area */}
          <div className="flex-[2] flex flex-col gap-6">
            <div className="glass-card rounded-2xl border border-border p-6 shadow-xl space-y-4">
              <h2 className="text-xl font-bold text-white mb-4">Transfer Details</h2>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">From Location *</label>
                  <select required value={form.from_location_id} onChange={e => setForm({...form, from_location_id: e.target.value})} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-blue-500/50">
                    <option value="">Select Source...</option>
                    {locations.map(l => <option key={l.id} value={l.id}>{l.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">To Location *</label>
                  <select required value={form.to_location_id} onChange={e => setForm({...form, to_location_id: e.target.value})} className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-blue-500/50">
                    <option value="">Select Destination...</option>
                    {locations.map(l => <option key={l.id} value={l.id}>{l.name}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-bold text-text-muted uppercase tracking-wider mb-2">Courier / Logistics Note</label>
                <input type="text" value={form.notes} onChange={e => setForm({...form, notes: e.target.value})} placeholder="e.g. FedEx Tracking #1234, Bubble wrapped..." className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-blue-500/50" />
              </div>
            </div>

            <div className="glass-card rounded-2xl border border-border p-6 shadow-xl flex-1 flex flex-col min-h-[300px]">
              <h2 className="text-xl font-bold text-white mb-4">Transfer Manifest</h2>
              
              {items.length === 0 ? (
                <div className="flex-1 flex flex-col items-center justify-center text-text-muted border-2 border-dashed border-border rounded-xl p-8">
                  <span className="text-4xl mb-2">📦</span>
                  <p>Manifest is empty.</p>
                  <p className="text-sm">Search and select products from the right panel.</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {items.map((item, index) => (
                    <div key={index} className="bg-background border border-border rounded-xl p-4 relative flex flex-col gap-3">
                      <button type="button" onClick={() => handleRemoveItem(index)} className="absolute top-4 right-4 text-text-muted hover:text-rose-400">✕</button>
                      
                      <div className="flex gap-4">
                        <div className="flex-1">
                          <h3 className="font-bold text-white">{item.product.name}</h3>
                          {item.product.has_serial_number && (
                            <span className="inline-block mt-1 px-2 py-0.5 bg-rose-500/10 text-rose-400 text-[10px] font-bold uppercase rounded border border-rose-500/20">Serial Tracking Required</span>
                          )}
                        </div>
                        <div className="w-24">
                          <label className="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Qty</label>
                          <input type="number" min="1" value={item.quantity} onChange={e => handleUpdateQuantity(index, parseFloat(e.target.value) || 1)} className="w-full bg-surface border border-border rounded-lg px-3 py-2 text-white outline-none focus:border-blue-500/50" />
                        </div>
                      </div>

                      {item.product.has_serial_number && (
                        <div className="mt-2 p-3 bg-surface border border-border rounded-lg">
                          <div className="flex justify-between items-center mb-2">
                            <span className="text-xs font-bold text-text-muted">SCANNED SERIALS ({item.serial_numbers.length}/{item.quantity})</span>
                            <button type="button" onClick={() => setActiveItemIndex(index)} className="text-xs font-bold bg-blue-500/10 text-blue-400 hover:bg-blue-500/20 px-3 py-1.5 rounded-lg transition-colors border border-blue-500/30">
                              + Scan Serials
                            </button>
                          </div>
                          {item.serial_numbers.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                              {item.serial_numbers.map((sn: string) => (
                                <span key={sn} className="bg-background border border-border px-2 py-1 rounded text-xs font-mono text-emerald-400 flex items-center gap-1">
                                  {sn} <button type="button" onClick={() => handleRemoveSerial(index, sn)} className="text-text-muted hover:text-rose-400 ml-1">×</button>
                                </span>
                              ))}
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
            
            <div className="flex justify-end pt-2">
              <button type="submit" className="bg-gradient-to-r from-blue-500 to-indigo-500 hover:from-blue-400 hover:to-indigo-400 text-white px-8 py-4 rounded-xl font-black tracking-widest uppercase shadow-xl transition-all hover:scale-[1.02] active:scale-95">
                Initiate Transfer
              </button>
            </div>
          </div>

          {/* Product Search Panel */}
          <div className="flex-[1] bg-surface/50 border border-border rounded-2xl flex flex-col shadow-xl overflow-hidden h-[calc(100vh-140px)] sticky top-6">
            <div className="p-4 border-b border-border bg-background/50">
              <input 
                type="text" 
                placeholder="Search products..." 
                value={productSearch}
                onChange={e => setProductSearch(e.target.value)}
                className="w-full bg-background border border-border rounded-xl px-4 py-3 text-white outline-none focus:border-blue-500/50"
              />
            </div>
            <div className="flex-1 overflow-y-auto p-4 flex flex-col gap-2 custom-scrollbar">
              {filteredProducts.slice(0, 50).map(p => (
                <button 
                  key={p.id} 
                  type="button"
                  onClick={() => handleAddProduct(p)}
                  className="text-left bg-background border border-border hover:border-blue-500/50 rounded-xl p-3 transition-colors group"
                >
                  <div className="font-bold text-sm text-white group-hover:text-blue-400 line-clamp-2">{p.name}</div>
                  <div className="text-xs text-text-muted mt-1 opacity-70">SKU: {p.sku} | Stock: {p.stocks?.reduce((acc: number, s: any) => acc + parseFloat(s.qty_available), 0) || 0}</div>
                </button>
              ))}
              {filteredProducts.length === 0 && (
                <div className="text-center py-10 text-text-muted text-sm">No products found.</div>
              )}
            </div>
          </div>
        </form>
      )}

      {/* Serial Scan Modal */}
      {activeItemIndex !== null && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-surface border border-border w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden flex flex-col animate-in zoom-in-95">
            <div className="p-6 border-b border-border bg-background/50 flex justify-between items-center">
              <h2 className="text-xl font-bold text-white">Scan Serial Number</h2>
              <button onClick={() => setActiveItemIndex(null)} className="text-text-muted hover:text-white">✕</button>
            </div>
            <div className="p-6 bg-surface">
              <div className="mb-4 text-sm text-text-muted">
                Scanning for: <strong className="text-white">{items[activeItemIndex].product.name}</strong><br/>
                Required: {items[activeItemIndex].quantity} | Scanned: <strong className="text-emerald-400">{items[activeItemIndex].serial_numbers.length}</strong>
              </div>
              <form onSubmit={handleAddSerial} className="flex gap-2">
                <input 
                  type="text" 
                  autoFocus
                  placeholder="Scan or type IMEI/Serial..." 
                  value={serialInput}
                  onChange={e => setSerialInput(e.target.value)}
                  className="flex-1 bg-background border border-border rounded-xl px-4 py-3 text-white font-mono outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/30"
                />
                <button type="submit" className="bg-emerald-500 hover:bg-emerald-400 text-white px-6 py-3 rounded-xl font-bold shadow-lg transition-colors">
                  Add
                </button>
              </form>
              
              {items[activeItemIndex].serial_numbers.length > 0 && (
                <div className="mt-6">
                  <h3 className="text-xs font-bold text-text-muted uppercase tracking-wider mb-2">Scanned Items</h3>
                  <div className="flex flex-col gap-1 max-h-[40vh] overflow-y-auto custom-scrollbar pr-2">
                    {items[activeItemIndex].serial_numbers.map((sn: string) => (
                      <div key={sn} className="flex justify-between items-center bg-background border border-border px-4 py-2.5 rounded-lg">
                        <span className="font-mono text-emerald-400 font-bold">{sn}</span>
                        <button type="button" onClick={() => handleRemoveSerial(activeItemIndex, sn)} className="text-text-muted hover:text-rose-400">Remove</button>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
            <div className="p-4 border-t border-border bg-background/50 flex justify-end">
              <button type="button" onClick={() => setActiveItemIndex(null)} className="bg-surface border border-border hover:bg-surface/80 text-white px-6 py-2.5 rounded-xl font-bold transition-colors">
                Done Scanning
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
