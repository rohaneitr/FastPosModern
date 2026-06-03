'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function InventoryPage() {
  const [activeTab, setActiveTab] = useState('overview');
  const [stocks, setStocks] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');

  // Adjustment & Transfer States
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalType, setModalType] = useState<'adjust' | 'transfer' | null>(null);
  const [selectedProduct, setSelectedProduct] = useState<any>(null);

  useEffect(() => {
    fetchStock();
  }, []);

  const fetchStock = async () => {
    setLoading(true);
    try {
      const res = await api.get('/inventory/stock');
      if (res.data && res.data.data) {
        setStocks(res.data.data);
      }
    } catch (err) {
      console.warn("Failed to fetch stock", err);
      // Fallback Mock Data
      setStocks([
        { id: 1, product_name: 'Premium Espresso Machine', sku: 'ES-100', location_name: 'Main Store', qty_available: '45.00' },
        { id: 2, product_name: 'Artisan Coffee Blend', sku: 'CB-200', location_name: 'Warehouse B', qty_available: '8.00' },
        { id: 3, product_name: 'Barista Starter Kit', sku: 'BK-300', location_name: 'Main Store', qty_available: '0.00' },
        { id: 4, product_name: 'Coffee Grinder Pro', sku: 'CG-400', location_name: 'Warehouse A', qty_available: '120.00' },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const handleOpenModal = (type: 'adjust' | 'transfer', product: any = null) => {
    setModalType(type);
    setSelectedProduct(product);
    setIsModalOpen(true);
  };

  const tabs = [
    { id: 'overview', label: 'Stock Overview' },
    { id: 'adjustments', label: 'Stock Adjustments' },
    { id: 'transfers', label: 'Stock Transfers' },
  ];

  const filteredStocks = stocks.filter(s => 
    s.product_name.toLowerCase().includes(searchQuery.toLowerCase()) || 
    s.sku.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">
            Inventory Management
          </h1>
          <p className="text-text-muted mt-1">Track real-time stock levels, adjust discrepancies, and transfer inventory.</p>
        </div>
        <div className="flex gap-3">
          <button onClick={() => handleOpenModal('transfer')} className="bg-surface border border-border hover:bg-white/5 text-white px-6 py-2 rounded-lg font-medium transition-colors">
            + Transfer Stock
          </button>
          <button onClick={() => handleOpenModal('adjust')} className="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2 rounded-lg shadow-lg shadow-emerald-500/20 font-medium transition-colors">
            + Adjust Stock
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="glass-card rounded-xl p-2 inline-flex self-start gap-2 flex-wrap border border-border">
        {tabs.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`px-6 py-2.5 rounded-lg text-sm font-bold transition-all ${
              activeTab === tab.id 
                ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' 
                : 'text-text-muted hover:text-white hover:bg-white/5'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="glass-card rounded-xl border border-border p-6 min-h-[500px]">
        
        {/* OVERVIEW TAB */}
        {activeTab === 'overview' && (
          <div className="flex flex-col gap-6 animate-in slide-in-from-right-4">
            
            {/* Search & Filters */}
            <div className="flex gap-4 items-center bg-surface/30 p-4 rounded-xl border border-border">
              <div className="relative w-full max-w-md">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted">🔍</span>
                <input 
                  type="text" 
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search by Product Name or SKU..." 
                  className="w-full bg-background border border-border rounded-lg pl-10 pr-4 py-2.5 focus:ring-2 focus:ring-emerald-500/50 outline-none transition-all"
                />
              </div>
              <select className="bg-background border border-border rounded-lg px-4 py-2.5 text-text-muted outline-none focus:ring-2 focus:ring-emerald-500/50">
                <option value="">All Locations</option>
                <option value="main">Main Store</option>
                <option value="warehouse">Warehouse A</option>
              </select>
              <div className="ml-auto text-sm font-medium px-4 py-2 bg-emerald-500/10 text-emerald-400 rounded-lg border border-emerald-500/20">
                Total Value: ৳14,250.00
              </div>
            </div>

            {/* Stock Table */}
            <div className="overflow-x-auto rounded-xl border border-border bg-background/50">
              <table className="w-full text-left text-sm">
                <thead className="bg-surface/80 border-b border-border">
                  <tr>
                    <th className="p-4 font-semibold text-text-muted">Product</th>
                    <th className="p-4 font-semibold text-text-muted">SKU</th>
                    <th className="p-4 font-semibold text-text-muted">Location</th>
                    <th className="p-4 font-semibold text-text-muted text-right">Qty Available</th>
                    <th className="p-4 font-semibold text-text-muted text-center">Status</th>
                    <th className="p-4 font-semibold text-text-muted text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={6} className="p-8 text-center text-text-muted">Loading stock data...</td></tr>
                  ) : filteredStocks.length === 0 ? (
                    <tr><td colSpan={6} className="p-8 text-center text-text-muted">No products found.</td></tr>
                  ) : (
                    filteredStocks.map(s => {
                      const qty = parseFloat(s.qty_available);
                      let statusColor = 'text-emerald-400 bg-emerald-500/20 border-emerald-500/30';
                      let statusText = 'In Stock';
                      if (qty <= 0) {
                        statusColor = 'text-danger bg-danger/20 border-danger/30';
                        statusText = 'Out of Stock';
                      } else if (qty < 10) {
                        statusColor = 'text-amber-400 bg-amber-500/20 border-amber-500/30';
                        statusText = 'Low Stock';
                      }

                      return (
                        <tr key={s.id} className="border-b border-border/50 hover:bg-surface transition-colors">
                          <td className="p-4 font-bold text-white">{s.product_name}</td>
                          <td className="p-4 font-mono text-text-muted">{s.sku || 'N/A'}</td>
                          <td className="p-4 text-primary font-medium">{s.location_name}</td>
                          <td className="p-4 text-right font-mono text-lg text-white">{qty}</td>
                          <td className="p-4 text-center">
                            <span className={`px-2.5 py-1 rounded-full text-xs font-bold uppercase border ${statusColor}`}>
                              {statusText}
                            </span>
                          </td>
                          <td className="p-4 text-center">
                            <div className="flex gap-2 justify-center">
                              <button onClick={() => handleOpenModal('adjust', s)} className="px-3 py-1 bg-surface border border-border rounded text-xs font-medium text-emerald-400 hover:bg-emerald-500/10">Adjust</button>
                              <button onClick={() => handleOpenModal('transfer', s)} className="px-3 py-1 bg-surface border border-border rounded text-xs font-medium text-blue-400 hover:bg-blue-500/10">Transfer</button>
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* ADJUSTMENTS TAB */}
        {activeTab === 'adjustments' && (
          <div className="animate-in slide-in-from-right-4">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold text-white">Recent Adjustments</h2>
              <button onClick={() => handleOpenModal('adjust')} className="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-lg shadow-emerald-500/20">+ New Adjustment</button>
            </div>
            
            <div className="rounded-xl border border-border bg-background/50 overflow-hidden">
              <table className="w-full text-left text-sm">
                <thead className="bg-surface/80 border-b border-border">
                  <tr>
                    <th className="p-4 font-semibold text-text-muted">Date</th>
                    <th className="p-4 font-semibold text-text-muted">Ref No.</th>
                    <th className="p-4 font-semibold text-text-muted">Location</th>
                    <th className="p-4 font-semibold text-text-muted">Type</th>
                    <th className="p-4 font-semibold text-text-muted">Total Qty</th>
                    <th className="p-4 font-semibold text-text-muted">Reason</th>
                  </tr>
                </thead>
                <tbody>
                  <tr className="border-b border-border/50 hover:bg-surface">
                    <td className="p-4 text-text-muted">02 Jun 2026</td>
                    <td className="p-4 font-mono text-white">ADJ-2026-001</td>
                    <td className="p-4">Main Store</td>
                    <td className="p-4"><span className="text-danger font-bold">Decrease (-)</span></td>
                    <td className="p-4 font-mono">2.00</td>
                    <td className="p-4 text-text-muted">Damaged during transit</td>
                  </tr>
                  <tr className="hover:bg-surface">
                    <td className="p-4 text-text-muted">01 Jun 2026</td>
                    <td className="p-4 font-mono text-white">ADJ-2026-002</td>
                    <td className="p-4">Warehouse A</td>
                    <td className="p-4"><span className="text-success font-bold">Increase (+)</span></td>
                    <td className="p-4 font-mono">5.00</td>
                    <td className="p-4 text-text-muted">Found during inventory audit</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* TRANSFERS TAB */}
        {activeTab === 'transfers' && (
          <div className="animate-in slide-in-from-right-4">
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-xl font-bold text-white">Stock Transfers</h2>
              <button onClick={() => handleOpenModal('transfer')} className="bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-lg shadow-emerald-500/20">+ New Transfer</button>
            </div>
            
            <div className="rounded-xl border border-border bg-background/50 overflow-hidden">
              <table className="w-full text-left text-sm">
                <thead className="bg-surface/80 border-b border-border">
                  <tr>
                    <th className="p-4 font-semibold text-text-muted">Date</th>
                    <th className="p-4 font-semibold text-text-muted">Ref No.</th>
                    <th className="p-4 font-semibold text-text-muted">From Location</th>
                    <th className="p-4 font-semibold text-text-muted">To Location</th>
                    <th className="p-4 font-semibold text-text-muted">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr className="border-b border-border/50 hover:bg-surface">
                    <td className="p-4 text-text-muted">02 Jun 2026</td>
                    <td className="p-4 font-mono text-white">TRF-2026-091</td>
                    <td className="p-4 font-medium">Warehouse A</td>
                    <td className="p-4 font-medium text-primary">Main Store</td>
                    <td className="p-4"><span className="bg-warning/20 text-warning px-2 py-1 rounded text-xs font-bold border border-warning/30">IN TRANSIT</span></td>
                  </tr>
                  <tr className="hover:bg-surface">
                    <td className="p-4 text-text-muted">28 May 2026</td>
                    <td className="p-4 font-mono text-white">TRF-2026-090</td>
                    <td className="p-4 font-medium">Main Store</td>
                    <td className="p-4 font-medium text-primary">Warehouse B</td>
                    <td className="p-4"><span className="bg-success/20 text-success px-2 py-1 rounded text-xs font-bold border border-success/30">COMPLETED</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        )}

      </div>

      {/* Action Modals */}
      {isModalOpen && modalType === 'adjust' && (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 animate-in fade-in">
          <div className="bg-surface border border-border p-6 rounded-2xl w-full max-w-lg shadow-2xl">
            <h2 className="text-2xl font-bold mb-4 text-white">Adjust Stock</h2>
            
            <div className="flex flex-col gap-4">
              <div>
                <label className="block text-sm text-text-muted mb-1">Product</label>
                <input value={selectedProduct ? `${selectedProduct.product_name} (${selectedProduct.sku})` : ''} className="w-full bg-background border border-border rounded-lg p-2.5 text-white" placeholder="Search or select product..." readOnly={!!selectedProduct} />
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm text-text-muted mb-1">Location</label>
                  <select className="w-full bg-background border border-border rounded-lg p-2.5 text-white">
                    <option>{selectedProduct ? selectedProduct.location_name : 'Select Location...'}</option>
                    <option>Main Store</option>
                    <option>Warehouse A</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm text-text-muted mb-1">Adjustment Type</label>
                  <select className="w-full bg-background border border-border rounded-lg p-2.5 text-white">
                    <option value="decrease">Decrease (-)</option>
                    <option value="increase">Increase (+)</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm text-text-muted mb-1">Quantity to Adjust</label>
                  <input type="number" className="w-full bg-background border border-border rounded-lg p-2.5 text-white font-mono" placeholder="0.00" />
                </div>
                <div>
                  <label className="block text-sm text-text-muted mb-1">Current Qty</label>
                  <input type="text" value={selectedProduct ? selectedProduct.qty_available : '0.00'} className="w-full bg-background/50 border border-border rounded-lg p-2.5 text-text-muted font-mono" readOnly />
                </div>
              </div>

              <div>
                <label className="block text-sm text-text-muted mb-1">Reason / Note</label>
                <textarea className="w-full bg-background border border-border rounded-lg p-2.5 text-white h-20" placeholder="e.g. Found damaged during stock check"></textarea>
              </div>

              <div className="mt-4 flex gap-3">
                <button onClick={() => { alert('Stock Adjusted!'); setIsModalOpen(false); }} className="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white py-2.5 rounded-lg font-bold transition-colors shadow-lg shadow-emerald-500/20">
                  Save Adjustment
                </button>
                <button onClick={() => setIsModalOpen(false)} className="flex-1 bg-surface border border-border py-2.5 rounded-lg font-medium text-white hover:bg-white/10 transition-colors">
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {isModalOpen && modalType === 'transfer' && (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 animate-in fade-in">
          <div className="bg-surface border border-border p-6 rounded-2xl w-full max-w-lg shadow-2xl">
            <h2 className="text-2xl font-bold mb-4 text-white">Transfer Stock</h2>
            
            <div className="flex flex-col gap-4">
              <div>
                <label className="block text-sm text-text-muted mb-1">Product</label>
                <input value={selectedProduct ? `${selectedProduct.product_name} (${selectedProduct.sku})` : ''} className="w-full bg-background border border-border rounded-lg p-2.5 text-white" placeholder="Search or select product..." readOnly={!!selectedProduct} />
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm text-text-muted mb-1">From Location</label>
                  <select className="w-full bg-background border border-border rounded-lg p-2.5 text-white">
                    <option>{selectedProduct ? selectedProduct.location_name : 'Select Origin...'}</option>
                    <option>Main Store</option>
                    <option>Warehouse A</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm text-text-muted mb-1">To Location</label>
                  <select className="w-full bg-background border border-border rounded-lg p-2.5 text-white">
                    <option>Select Destination...</option>
                    <option>Warehouse B</option>
                    <option>Main Store</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-sm text-text-muted mb-1">Transfer Quantity</label>
                <input type="number" className="w-full bg-background border border-border rounded-lg p-2.5 text-white font-mono" placeholder="0.00" />
              </div>

              <div>
                <label className="block text-sm text-text-muted mb-1">Shipping Note / Reference</label>
                <input type="text" className="w-full bg-background border border-border rounded-lg p-2.5 text-white" placeholder="Optional reference" />
              </div>

              <div className="mt-4 flex gap-3">
                <button onClick={() => { alert('Transfer Initiated!'); setIsModalOpen(false); }} className="flex-1 bg-blue-500 hover:bg-blue-600 text-white py-2.5 rounded-lg font-bold transition-colors shadow-lg shadow-blue-500/20">
                  Initiate Transfer
                </button>
                <button onClick={() => setIsModalOpen(false)} className="flex-1 bg-surface border border-border py-2.5 rounded-lg font-medium text-white hover:bg-white/10 transition-colors">
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

    </div>
  );
}
