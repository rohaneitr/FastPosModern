'use client';

import React, { useState, useEffect, useMemo } from 'react';
import api from '@/lib/api';
import { Plus, Trash, Package, Users, CheckCircle, Clock } from 'lucide-react';

export default function PurchasesSuppliersPage() {
  const [activeTab, setActiveTab] = useState<'purchases' | 'suppliers'>('purchases');
  
  // Data states
  const [purchases, setPurchases] = useState<any[]>([]);
  const [suppliers, setSuppliers] = useState<any[]>([]);
  const [products, setProducts] = useState<any[]>([]);
  
  // UI states
  const [loading, setLoading] = useState(true);
  const [isPurchaseModalOpen, setIsPurchaseModalOpen] = useState(false);
  const [isSupplierModalOpen, setIsSupplierModalOpen] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [toast, setToast] = useState<{message: string, type: 'success'|'error'} | null>(null);

  // Forms
  const [purchaseForm, setPurchaseForm] = useState({
    contact_id: '',
    reference_no: '',
    purchase_date: new Date().toISOString().split('T')[0],
    status: 'pending',
    note: '',
    lines: [{ product_id: '', quantity: '1', purchase_price: '0' }]
  });

  const [supplierForm, setSupplierForm] = useState({
    name: '',
    phone: '',
    email: '',
    address: '',
    is_active: true
  });

  const showToast = (message: string, type: 'success'|'error') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 4000);
  };

  useEffect(() => { 
    fetchData();
  }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [purchasesRes, suppliersRes, productsRes] = await Promise.all([
        api.get('/purchases').catch(() => ({ data: { data: [] } })),
        api.get('/suppliers').catch(() => ({ data: { data: [] } })),
        api.get('/products').catch(() => ({ data: { data: [] } }))
      ]);
      setPurchases(purchasesRes.data?.data || []);
      setSuppliers(suppliersRes.data?.data || []);
      setProducts(productsRes.data?.data || []);
    } catch (err) {
      showToast('Failed to load initial data.', 'error');
    } finally {
      setLoading(false);
    }
  };

  // ----- PURCHASE LOGIC -----
  const openPurchaseModal = () => {
    setPurchaseForm({
      contact_id: '',
      reference_no: '',
      purchase_date: new Date().toISOString().split('T')[0],
      status: 'pending',
      note: '',
      lines: [{ product_id: '', quantity: '1', purchase_price: '0' }]
    });
    setIsPurchaseModalOpen(true);
  };

  const updatePurchaseLine = (index: number, field: string, value: string) => {
    const newLines = [...purchaseForm.lines];
    newLines[index] = { ...newLines[index], [field]: value };
    setPurchaseForm({ ...purchaseForm, lines: newLines });
  };

  const addPurchaseLine = () => {
    setPurchaseForm({
      ...purchaseForm,
      lines: [...purchaseForm.lines, { product_id: '', quantity: '1', purchase_price: '0' }]
    });
  };

  const removePurchaseLine = (index: number) => {
    const newLines = purchaseForm.lines.filter((_, i) => i !== index);
    setPurchaseForm({ ...purchaseForm, lines: newLines });
  };

  const grandTotal = useMemo(() => {
    return purchaseForm.lines.reduce((total, line) => {
      const qty = parseFloat(line.quantity) || 0;
      const price = parseFloat(line.purchase_price) || 0;
      return total + (qty * price);
    }, 0);
  }, [purchaseForm.lines]);

  const handleSavePurchase = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);
    try {
      await api.post('/purchases', purchaseForm);
      showToast('Purchase order created successfully!', 'success');
      setIsPurchaseModalOpen(false);
      fetchData(); // refresh data
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to create purchase.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const handleDeletePurchase = async (id: number) => {
    if (!confirm('Are you sure you want to delete this purchase?')) return;
    try {
      await api.delete(`/purchases/${id}`);
      showToast('Purchase deleted.', 'success');
      fetchData();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to delete purchase.', 'error');
    }
  };

  // ----- SUPPLIER LOGIC -----
  const openSupplierModal = () => {
    setSupplierForm({
      name: '',
      phone: '',
      email: '',
      address: '',
      is_active: true
    });
    setIsSupplierModalOpen(true);
  };

  const handleSaveSupplier = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSaving(true);
    try {
      await api.post('/suppliers', supplierForm);
      showToast('Supplier created successfully!', 'success');
      setIsSupplierModalOpen(false);
      fetchData();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to create supplier.', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const handleDeleteSupplier = async (id: number) => {
    if (!confirm('Are you sure you want to delete this supplier?')) return;
    try {
      await api.delete(`/suppliers/${id}`);
      showToast('Supplier deleted.', 'success');
      fetchData();
    } catch (err: any) {
      showToast(err.response?.data?.message || 'Failed to delete supplier.', 'error');
    }
  };

  return (
    <div className="flex flex-col h-full gap-6 animate-in fade-in duration-500 relative p-6">
      {toast && (
        <div className={`fixed top-8 left-1/2 -translate-x-1/2 z-[100] px-6 py-3 rounded-full shadow-2xl font-semibold flex items-center gap-3 animate-in slide-in-from-top-10 backdrop-blur-md border
          ${toast.type === 'success' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50' : 'bg-red-500/20 text-red-300 border-red-500/50'}`}>
          {toast.type === 'success' ? '✓' : '✕'} {toast.message}
        </div>
      )}

      <div className="flex justify-between items-end">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-primary to-emerald-400">
            Purchases & Suppliers
          </h1>
          <p className="text-text-muted mt-1">Manage suppliers, purchase orders, and receive stock.</p>
        </div>
        <div className="flex gap-3">
          <button onClick={openSupplierModal} className="bg-surface hover:bg-surface-hover border border-border text-white px-5 py-2.5 rounded-xl shadow-lg font-semibold transition-all flex items-center gap-2">
            <Users size={18} /> Add Supplier
          </button>
          <button onClick={openPurchaseModal} className="bg-primary hover:bg-primary-hover text-white px-5 py-2.5 rounded-xl shadow-lg font-semibold transition-all flex items-center gap-2">
            <Plus size={18} /> New Purchase
          </button>
        </div>
      </div>

      {/* TABS */}
      <div className="flex gap-4 border-b border-border">
        <button 
          onClick={() => setActiveTab('purchases')}
          className={`pb-3 font-semibold text-lg transition-colors border-b-2 flex items-center gap-2 ${activeTab === 'purchases' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-white'}`}
        >
          <Package size={20} /> Purchases
        </button>
        <button 
          onClick={() => setActiveTab('suppliers')}
          className={`pb-3 font-semibold text-lg transition-colors border-b-2 flex items-center gap-2 ${activeTab === 'suppliers' ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-white'}`}
        >
          <Users size={20} /> Suppliers
        </button>
      </div>

      {/* TAB CONTENT */}
      <div className="glass-card rounded-2xl overflow-hidden border border-border bg-surface/50">
        
        {/* PURCHASES TAB */}
        {activeTab === 'purchases' && (
          <div className="overflow-x-auto w-full">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface/80 border-b border-border">
                <tr>
                  <th className="p-4 font-semibold text-text-muted">Date</th>
                  <th className="p-4 font-semibold text-text-muted">Ref No.</th>
                  <th className="p-4 font-semibold text-text-muted">Supplier</th>
                  <th className="p-4 font-semibold text-text-muted">Status</th>
                  <th className="p-4 font-semibold text-text-muted text-right">Grand Total</th>
                  <th className="p-4 font-semibold text-text-muted text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={6} className="p-8 text-center text-text-muted">Loading...</td></tr>
                ) : purchases.length === 0 ? (
                  <tr><td colSpan={6} className="p-8 text-center text-text-muted">No purchases found.</td></tr>
                ) : (
                  purchases.map(p => (
                    <tr key={p.id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                      <td className="p-4">{new Date(p.purchase_date).toLocaleDateString()}</td>
                      <td className="p-4 font-medium text-white">{p.reference_no}</td>
                      <td className="p-4 text-white">{p.contact?.name || 'Unknown'}</td>
                      <td className="p-4">
                        <span className={`px-2.5 py-1 rounded-full text-xs font-bold uppercase flex items-center gap-1.5 w-max
                          ${p.status === 'received' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}
                        `}>
                          {p.status === 'received' ? <CheckCircle size={14} /> : <Clock size={14} />}
                          {p.status}
                        </span>
                      </td>
                      <td className="p-4 text-right font-semibold text-white">${parseFloat(p.grand_total).toFixed(2)}</td>
                      <td className="p-4 text-center">
                        <button onClick={() => handleDeletePurchase(p.id)} className="text-red-400 hover:text-red-300 hover:bg-red-400/10 p-2 rounded-lg transition-colors">
                          <Trash size={16} />
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}

        {/* SUPPLIERS TAB */}
        {activeTab === 'suppliers' && (
          <div className="overflow-x-auto w-full">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface/80 border-b border-border">
                <tr>
                  <th className="p-4 font-semibold text-text-muted">Name</th>
                  <th className="p-4 font-semibold text-text-muted">Phone</th>
                  <th className="p-4 font-semibold text-text-muted">Email</th>
                  <th className="p-4 font-semibold text-text-muted">Status</th>
                  <th className="p-4 font-semibold text-text-muted text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan={5} className="p-8 text-center text-text-muted">Loading...</td></tr>
                ) : suppliers.length === 0 ? (
                  <tr><td colSpan={5} className="p-8 text-center text-text-muted">No suppliers found.</td></tr>
                ) : (
                  suppliers.map(s => (
                    <tr key={s.id} className="border-b border-border/50 hover:bg-white/5 transition-colors">
                      <td className="p-4 font-medium text-white">{s.name}</td>
                      <td className="p-4 text-text-muted">{s.phone || 'N/A'}</td>
                      <td className="p-4 text-text-muted">{s.email || 'N/A'}</td>
                      <td className="p-4">
                        <span className={`px-2 py-1 rounded-full text-xs font-bold uppercase
                          ${s.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}
                        `}>
                          {s.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="p-4 text-center">
                        <button onClick={() => handleDeleteSupplier(s.id)} className="text-red-400 hover:text-red-300 hover:bg-red-400/10 p-2 rounded-lg transition-colors">
                          <Trash size={16} />
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* --- PURCHASE MODAL --- */}
      {isPurchaseModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-3xl shadow-2xl animate-in zoom-in-95 max-h-[90vh] overflow-y-auto flex flex-col gap-6">
            <div className="flex justify-between items-center border-b border-border pb-4">
              <h2 className="text-2xl font-bold text-white flex items-center gap-2"><Package className="text-primary"/> Create Purchase Order</h2>
              <button type="button" onClick={() => setIsPurchaseModalOpen(false)} className="text-text-muted hover:text-white transition-colors p-1 bg-surface-hover rounded-lg">✕</button>
            </div>
            
            <form onSubmit={handleSavePurchase} className="flex flex-col gap-6">
              
              {/* Header Info */}
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="lg:col-span-2">
                  <label className="block text-sm font-semibold text-text-muted mb-1.5">Supplier *</label>
                  <select required value={purchaseForm.contact_id} onChange={e => setPurchaseForm({...purchaseForm, contact_id: e.target.value})}
                    className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-primary/50 transition-all">
                    <option value="">Select supplier...</option>
                    {suppliers.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-semibold text-text-muted mb-1.5">Date *</label>
                  <input required type="date" value={purchaseForm.purchase_date} onChange={e => setPurchaseForm({...purchaseForm, purchase_date: e.target.value})}
                    className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-primary/50 transition-all" />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-text-muted mb-1.5">Status *</label>
                  <select required value={purchaseForm.status} onChange={e => setPurchaseForm({...purchaseForm, status: e.target.value})}
                    className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-primary/50 transition-all">
                    <option value="pending">Pending</option>
                    <option value="received">Received (Updates Stock)</option>
                  </select>
                </div>
              </div>

              {/* Dynamic Line Builder */}
              <div className="bg-background border border-border rounded-xl p-4 flex flex-col gap-4">
                <div className="flex justify-between items-center">
                  <label className="block text-sm font-semibold text-text-muted">Line Items *</label>
                  <button type="button" onClick={addPurchaseLine} className="text-xs font-bold bg-primary/20 text-primary px-3 py-1.5 rounded-lg hover:bg-primary hover:text-white transition-colors flex items-center gap-1">
                    <Plus size={14} /> Add Line
                  </button>
                </div>

                <div className="flex flex-col gap-3">
                  {purchaseForm.lines.map((line, idx) => {
                    const subtotal = (parseFloat(line.quantity) || 0) * (parseFloat(line.purchase_price) || 0);
                    return (
                      <div key={idx} className="flex flex-wrap md:flex-nowrap gap-3 items-center bg-surface border border-border/50 rounded-lg p-3 relative group">
                        <div className="flex-1 min-w-[200px]">
                          <select required value={line.product_id} onChange={e => updatePurchaseLine(idx, 'product_id', e.target.value)}
                            className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm text-white outline-none focus:border-primary/50">
                            <option value="">Select Product...</option>
                            {products.map(p => <option key={p.id} value={p.id}>{p.name} (SKU: {p.sku})</option>)}
                          </select>
                        </div>
                        <div className="w-24">
                          <input required type="number" min="0.01" step="0.01" value={line.quantity} onChange={e => updatePurchaseLine(idx, 'quantity', e.target.value)}
                            className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm font-mono text-white outline-none focus:border-primary/50"
                            placeholder="Qty" />
                        </div>
                        <div className="w-32">
                          <input required type="number" min="0" step="0.01" value={line.purchase_price} onChange={e => updatePurchaseLine(idx, 'purchase_price', e.target.value)}
                            className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm font-mono text-white outline-none focus:border-primary/50"
                            placeholder="Unit Price" />
                        </div>
                        <div className="w-32 text-right font-mono text-emerald-400 font-semibold text-sm">
                          ${subtotal.toFixed(2)}
                        </div>
                        {purchaseForm.lines.length > 1 && (
                          <button type="button" onClick={() => removePurchaseLine(idx)} className="text-red-400 hover:text-red-300 p-2 md:opacity-0 group-hover:opacity-100 transition-opacity">
                            <Trash size={16} />
                          </button>
                        )}
                      </div>
                    );
                  })}
                </div>

                <div className="flex justify-end pt-4 border-t border-border mt-2">
                  <div className="text-right">
                    <p className="text-sm text-text-muted font-semibold uppercase tracking-wider mb-1">Grand Total</p>
                    <p className="text-3xl font-bold text-white font-mono">${grandTotal.toFixed(2)}</p>
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-sm font-semibold text-text-muted mb-1.5">Internal Note</label>
                <textarea rows={2} value={purchaseForm.note} onChange={e => setPurchaseForm({...purchaseForm, note: e.target.value})}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-primary/50 transition-all"
                  placeholder="Optional notes..."></textarea>
              </div>

              <div className="flex justify-end gap-3 pt-4 border-t border-border">
                <button type="button" onClick={() => setIsPurchaseModalOpen(false)} className="px-6 py-2.5 bg-surface border border-border rounded-xl font-semibold text-text-muted hover:text-white transition-colors">Cancel</button>
                <button type="submit" disabled={isSaving || purchaseForm.lines.length === 0} className="px-8 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl font-bold transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                  {isSaving ? 'Saving...' : 'Confirm Purchase'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* --- SUPPLIER MODAL --- */}
      {isSupplierModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md animate-in fade-in p-4">
          <div className="bg-surface border border-border rounded-2xl p-6 w-full max-w-md shadow-2xl animate-in zoom-in-95">
            <div className="flex justify-between items-center border-b border-border pb-4 mb-6">
              <h2 className="text-xl font-bold text-white flex items-center gap-2"><Users className="text-primary" size={20}/> New Supplier</h2>
              <button type="button" onClick={() => setIsSupplierModalOpen(false)} className="text-text-muted hover:text-white transition-colors p-1 bg-surface-hover rounded-lg">✕</button>
            </div>
            
            <form onSubmit={handleSaveSupplier} className="flex flex-col gap-4">
              <div>
                <label className="block text-sm font-semibold text-text-muted mb-1.5">Name *</label>
                <input required type="text" value={supplierForm.name} onChange={e => setSupplierForm({...supplierForm, name: e.target.value})}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-primary/50 transition-all" />
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-text-muted mb-1.5">Phone</label>
                  <input type="text" value={supplierForm.phone} onChange={e => setSupplierForm({...supplierForm, phone: e.target.value})}
                    className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-primary/50 transition-all" />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-text-muted mb-1.5">Email</label>
                  <input type="email" value={supplierForm.email} onChange={e => setSupplierForm({...supplierForm, email: e.target.value})}
                    className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-primary/50 transition-all" />
                </div>
              </div>

              <div>
                <label className="block text-sm font-semibold text-text-muted mb-1.5">Address</label>
                <textarea rows={2} value={supplierForm.address} onChange={e => setSupplierForm({...supplierForm, address: e.target.value})}
                  className="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-white outline-none focus:border-primary/50 transition-all"></textarea>
              </div>

              <div className="flex items-center gap-2 mt-2">
                <input type="checkbox" id="is_active" checked={supplierForm.is_active} onChange={e => setSupplierForm({...supplierForm, is_active: e.target.checked})}
                  className="w-4 h-4 rounded border-border bg-background text-primary focus:ring-primary/50" />
                <label htmlFor="is_active" className="text-sm font-semibold text-white">Active Supplier</label>
              </div>

              <div className="flex justify-end gap-3 pt-4 border-t border-border mt-4">
                <button type="button" onClick={() => setIsSupplierModalOpen(false)} className="px-5 py-2.5 bg-surface border border-border rounded-xl font-semibold text-text-muted hover:text-white transition-colors">Cancel</button>
                <button type="submit" disabled={isSaving} className="px-6 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl font-bold transition-all disabled:opacity-50">
                  {isSaving ? 'Saving...' : 'Save Supplier'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
