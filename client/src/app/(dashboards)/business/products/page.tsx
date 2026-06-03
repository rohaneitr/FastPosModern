'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function AdvancedProductsPage() {
  const [activeTab, setActiveTab] = useState('list');
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');

  // Unified Product Form State
  const [selectedProductId, setSelectedProductId] = useState<number | null>(null);
  const [formData, setFormData] = useState({
    name: '',
    sku: '',
    type: 'single',
    price: '',
    brand_id: '',
    category_id: '',
    sub_category_id: '',
    variations: [{ name: '', sub_sku: '', price: '' }],
    combo_items: '',
    enable_sr_no: false,
    sr_no_prefix: '',
    enable_imei: false,
    imei_prefix: '',
    enable_warranty: false,
    warranty_duration: '',
    enable_expiry: false,
    expiry_duration: ''
  });

  useEffect(() => {
    fetchProducts();
  }, [activeTab]);

  const fetchProducts = async () => {
    if (activeTab !== 'list') return;
    setLoading(true);
    try {
      const res = await api.get('/products');
      if (res.data && res.data.data) {
        setProducts(res.data.data);
      }
    } catch (err) {
      console.warn("Failed to fetch products", err);
      if (products.length === 0) {
        setProducts([
          { id: 1, name: 'Premium Espresso Machine', sku: 'ES-100', type: 'single', unit: { short_name: 'Pc' }, price: 499.99 },
          { id: 2, name: 'Artisan Coffee Blend', sku: 'CB-200', type: 'variable', unit: { short_name: 'Kg' }, variations: [{ name: '250g', price: 10 }, { name: '1Kg', price: 35 }] },
          { id: 3, name: 'Barista Starter Kit', sku: 'BK-300', type: 'combo', unit: { short_name: 'Set' }, combo_items: 'Machine + Coffee' },
        ]);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleAddProduct = () => {
    setSelectedProductId(null);
    setFormData({ 
      name: '', sku: '', type: 'single', price: '', 
      brand_id: '', category_id: '', sub_category_id: '',
      variations: [{ name: '', sub_sku: '', price: '' }], combo_items: '',
      enable_sr_no: false, sr_no_prefix: '',
      enable_imei: false, imei_prefix: '',
      enable_warranty: false, warranty_duration: '',
      enable_expiry: false, expiry_duration: ''
    });
    setActiveTab('add');
  };

  const handleEditProduct = (p: any) => {
    setSelectedProductId(p.id);
    setFormData({
      name: p.name,
      sku: p.sku,
      type: p.type,
      price: p.price || '',
      brand_id: p.brand_id || '',
      category_id: p.category_id || '',
      sub_category_id: p.sub_category_id || '',
      variations: p.variations || [{ name: '', sub_sku: '', price: '' }],
      combo_items: p.combo_items || '',
      enable_sr_no: p.enable_sr_no || false,
      sr_no_prefix: p.sr_no_prefix || '',
      enable_imei: p.enable_imei || false,
      imei_prefix: p.imei_prefix || '',
      enable_warranty: p.enable_warranty || false,
      warranty_duration: p.warranty_duration || '',
      enable_expiry: p.enable_expiry || false,
      expiry_duration: p.expiry_duration || ''
    });
    setActiveTab('add');
  };

  const handleSaveProduct = async () => {
    try {
      const payload = { ...formData };
      if (selectedProductId) {
        // Mock Update
        setProducts(products.map(p => p.id === selectedProductId ? { ...p, ...payload } : p));
        alert('Product updated successfully!');
      } else {
        // Mock Create
        setProducts([{ id: Date.now(), ...payload }, ...products]);
        alert('Product created successfully!');
      }
      setActiveTab('list');
    } catch (err) {
      alert('Failed to save product');
    }
  };

  const updateVariation = (index: number, field: string, value: string) => {
    const newVars = [...formData.variations];
    newVars[index] = { ...newVars[index], [field]: value };
    setFormData({ ...formData, variations: newVars });
  };

  const handlePrintLabels = async () => {
    try {
      const payload = {
        products: [{ id: 1, labels_count: 5 }],
        print_settings: { barcode_type: 'C128', show_price: true }
      };
      const res = await api.post('/products/print-labels', payload);
      alert(`Successfully generated ${res.data.labels.length} labels for printing! Check console for raw layout.`);
      console.log('PRINT PAYLOAD:', res.data);
    } catch (err) {
      alert('Failed to print labels. Ensure API is running.');
    }
  };

  const tabs = [
    { id: 'list', label: 'All Products' },
    { id: 'labels', label: 'Print Labels' },
    { id: 'expiry', label: 'Expiry Tracking' },
  ];

  const filteredProducts = products.filter(p => 
    p.name.toLowerCase().includes(searchQuery.toLowerCase()) || 
    p.sku.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-pink-500 to-rose-400">
            Advanced Catalog
          </h1>
          <p className="text-text-muted mt-1">Manage variations, combos, label printing, and lot expiry.</p>
        </div>
        <button onClick={handleAddProduct} className="bg-rose-500 hover:bg-rose-600 text-white px-6 py-2 rounded-lg shadow-lg font-medium transition-colors">
          + Add Product
        </button>
      </div>

      {/* Tabs */}
      <div className="glass-card rounded-xl p-2 inline-flex self-start gap-2 flex-wrap">
        {tabs.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
              activeTab === tab.id 
                ? 'bg-rose-500 text-white shadow-md' 
                : 'text-text-muted hover:text-white hover:bg-white/5'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="glass-card rounded-xl border border-border p-6 min-h-[400px]">
        
        {activeTab === 'list' && (
          <div className="flex flex-col gap-4">
            {/* Search Bar */}
            <div className="flex justify-between items-center bg-surface/30 p-4 rounded-xl border border-border">
              <div className="relative w-full max-w-md">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-text-muted">🔍</span>
                <input 
                  type="text" 
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search products by Name or SKU..." 
                  className="w-full bg-background border border-border rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-rose-500/50 outline-none transition-all"
                />
              </div>
              <div className="text-sm text-text-muted font-medium">
                {filteredProducts.length} Product(s) Found
              </div>
            </div>

            <div className="overflow-x-auto rounded-xl border border-border bg-background/50">
              <table className="w-full text-left text-sm">
                <thead className="bg-surface/80 border-b border-border">
                  <tr>
                    <th className="p-4 font-semibold text-text-muted">Product Name</th>
                    <th className="p-4 font-semibold text-text-muted">SKU</th>
                    <th className="p-4 font-semibold text-text-muted">Product Type</th>
                    <th className="p-4 font-semibold text-text-muted">Details</th>
                    <th className="p-4 font-semibold text-text-muted text-center">Action</th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={5} className="p-8 text-center text-text-muted">Loading products...</td></tr>
                  ) : filteredProducts.length === 0 ? (
                    <tr><td colSpan={5} className="p-8 text-center text-text-muted font-medium">No products match your search.</td></tr>
                  ) : filteredProducts.map((p, i) => (
                    <tr key={i} className="border-b border-border/50 hover:bg-surface transition-colors">
                      <td className="p-4 font-bold text-white">{p.name}</td>
                      <td className="p-4 font-mono text-text-muted">{p.sku}</td>
                      <td className="p-4">
                        <span className={`px-2 py-1 rounded-full text-xs font-bold uppercase
                          ${p.type === 'single' ? 'bg-blue-500/20 text-blue-400' : p.type === 'variable' ? 'bg-purple-500/20 text-purple-400' : 'bg-orange-500/20 text-orange-400'}
                        `}>
                          {p.type}
                        </span>
                      </td>
                      <td className="p-4 text-text-muted">
                        {p.type === 'variable' && p.variations ? `${p.variations.length} Variations` : p.type === 'combo' ? p.combo_items : `Base Price: $${p.price}`}
                      </td>
                      <td className="p-4 text-center">
                        <button onClick={() => handleEditProduct(p)} className="bg-surface border border-border px-4 py-1.5 rounded-lg text-primary hover:bg-white/10 hover:border-primary/50 transition-all font-medium">
                          Edit
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {activeTab === 'add' && (
          <div className="animate-in slide-in-from-right-4 max-w-5xl mx-auto">
            <div className="flex justify-between items-center mb-6">
              <div>
                <h2 className="text-2xl font-bold text-white">{selectedProductId ? 'Edit Product' : 'Create New Product'}</h2>
                <p className="text-text-muted">Configure product details, classification, pricing, and tracking parameters.</p>
              </div>
              <div className="flex gap-3">
                <button onClick={() => setActiveTab('list')} className="bg-surface border border-border px-6 py-2 rounded-lg font-medium text-white hover:bg-white/10 transition-colors">
                  Cancel
                </button>
                <button onClick={handleSaveProduct} className="bg-rose-500 hover:bg-rose-600 text-white px-8 py-2 rounded-lg font-bold shadow-lg shadow-rose-500/20 transition-all">
                  {selectedProductId ? 'Update Product' : 'Save Product'}
                </button>
              </div>
            </div>
            
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              
              {/* Left Column: Basic Info */}
              <div className="lg:col-span-2 flex flex-col gap-6">
                
                {/* Section 1: Core Information */}
                <div className="bg-surface/30 border border-border rounded-xl p-6">
                  <h3 className="font-bold text-lg mb-4 text-white border-b border-border/50 pb-2">Core Information</h3>
                  <div className="grid grid-cols-2 gap-6 mb-4">
                    <div>
                      <label className="block text-sm font-medium text-text-muted mb-1.5">Product Name <span className="text-rose-500">*</span></label>
                      <input value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} className="w-full bg-background border border-border rounded-lg p-2.5 focus:ring-2 focus:ring-rose-500/50 outline-none transition-all" placeholder="e.g. Premium T-Shirt" />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-muted mb-1.5">SKU <span className="text-rose-500">*</span></label>
                      <input value={formData.sku} onChange={e => setFormData({...formData, sku: e.target.value})} className="w-full bg-background border border-border rounded-lg p-2.5 font-mono focus:ring-2 focus:ring-rose-500/50 outline-none transition-all" placeholder="TSHIRT-01" />
                    </div>
                  </div>

                  <div className="grid grid-cols-3 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-text-muted mb-1.5">Brand</label>
                      <select value={formData.brand_id} onChange={e => setFormData({...formData, brand_id: e.target.value})} className="w-full bg-background border border-border rounded-lg p-2.5 outline-none">
                        <option value="">Select Brand...</option>
                        <option value="1">Apple</option>
                        <option value="2">Samsung</option>
                        <option value="3">Nike</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-muted mb-1.5">Category</label>
                      <select value={formData.category_id} onChange={e => setFormData({...formData, category_id: e.target.value})} className="w-full bg-background border border-border rounded-lg p-2.5 outline-none">
                        <option value="">Select Category...</option>
                        <option value="1">Electronics</option>
                        <option value="2">Apparel</option>
                        <option value="3">Consumables</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-text-muted mb-1.5">Sub-Category</label>
                      <select value={formData.sub_category_id} onChange={e => setFormData({...formData, sub_category_id: e.target.value})} className="w-full bg-background border border-border rounded-lg p-2.5 outline-none disabled:opacity-50" disabled={!formData.category_id}>
                        <option value="">Select Sub-Category...</option>
                        <option value="11">Smartphones</option>
                        <option value="12">Laptops</option>
                        <option value="21">Men's Shirts</option>
                      </select>
                    </div>
                  </div>
                </div>

                {/* Section 2: Pricing & Type */}
                <div className="bg-surface/30 border border-border rounded-xl p-6">
                  <div className="flex justify-between items-center mb-4 border-b border-border/50 pb-2">
                    <h3 className="font-bold text-lg text-white">Product Configuration</h3>
                    <div className="w-48">
                      <select value={formData.type} onChange={e => setFormData({...formData, type: e.target.value})} className="w-full bg-rose-500/10 text-rose-400 border border-rose-500/30 rounded-lg p-1.5 text-sm font-bold outline-none cursor-pointer">
                        <option value="single">Single Product</option>
                        <option value="variable">Variable Product</option>
                        <option value="combo">Combo Pack</option>
                      </select>
                    </div>
                  </div>

                  {formData.type === 'single' && (
                    <div className="animate-in fade-in">
                      <label className="block text-sm font-medium text-text-muted mb-1.5">Base Price ($) <span className="text-rose-500">*</span></label>
                      <input value={formData.price} onChange={e => setFormData({...formData, price: e.target.value})} className="w-full max-w-[200px] bg-background border border-border rounded-lg p-2.5 font-mono text-lg focus:ring-2 focus:ring-rose-500/50 outline-none transition-all" placeholder="0.00" />
                    </div>
                  )}

                  {formData.type === 'variable' && (
                    <div className="animate-in fade-in">
                      <p className="text-sm text-text-muted mb-4">Define variations (e.g. Size, Color) and override prices/SKUs.</p>
                      <div className="flex flex-col gap-3">
                        {formData.variations.map((v, idx) => (
                          <div key={idx} className="flex gap-3 items-center bg-background p-2 rounded-lg border border-border/50">
                            <input value={v.name} onChange={e => updateVariation(idx, 'name', e.target.value)} className="flex-[2] bg-transparent border-none focus:ring-0 text-sm outline-none px-2" placeholder="Variation Name (e.g. Small - Red)" />
                            <div className="w-px h-6 bg-border"></div>
                            <input value={v.sub_sku} onChange={e => updateVariation(idx, 'sub_sku', e.target.value)} className="flex-1 bg-transparent border-none focus:ring-0 text-sm font-mono outline-none px-2" placeholder="Sub-SKU" />
                            <div className="w-px h-6 bg-border"></div>
                            <input value={v.price} onChange={e => updateVariation(idx, 'price', e.target.value)} className="w-24 bg-transparent border-none focus:ring-0 text-sm font-mono outline-none px-2" placeholder="Price ($)" />
                            <button onClick={() => setFormData({...formData, variations: formData.variations.filter((_, i) => i !== idx)})} className="p-2 text-danger hover:bg-danger/10 rounded-md transition-colors">
                              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                          </div>
                        ))}
                        <button onClick={() => setFormData({...formData, variations: [...formData.variations, { name: '', sub_sku: '', price: '' }]})} className="self-start text-sm text-rose-400 hover:text-rose-300 font-bold mt-2 px-3 py-1.5 rounded-lg border border-rose-500/30 hover:bg-rose-500/10 transition-colors">
                          + Add Variation
                        </button>
                      </div>
                    </div>
                  )}

                  {formData.type === 'combo' && (
                    <div className="animate-in fade-in">
                      <label className="block text-sm font-medium text-text-muted mb-1.5">Combo Items Definition</label>
                      <textarea value={formData.combo_items} onChange={e => setFormData({...formData, combo_items: e.target.value})} className="w-full bg-background border border-border rounded-lg p-3 h-24 focus:ring-2 focus:ring-rose-500/50 outline-none transition-all" placeholder="e.g. 1x Machine, 2x Coffee Beans"></textarea>
                    </div>
                  )}
                </div>
              </div>

              {/* Right Column: Advanced Tracking */}
              <div className="lg:col-span-1">
                <div className="bg-surface/30 border border-border rounded-xl p-6 sticky top-6">
                  <h3 className="font-bold text-lg mb-4 text-white border-b border-border/50 pb-2">Tracking Config</h3>
                  
                  <div className="flex flex-col gap-5">
                    {/* Serial Number */}
                    <div>
                      <label className="block text-sm font-medium text-text-muted mb-1.5">Serial Number</label>
                      <select value={formData.enable_sr_no ? 'true' : 'false'} onChange={e => setFormData({...formData, enable_sr_no: e.target.value === 'true'})} className="w-full bg-background border border-border rounded-lg p-2.5 text-sm outline-none">
                        <option value="false">Non Serial Number</option>
                        <option value="true">Serial Number Tracked</option>
                      </select>
                      {formData.enable_sr_no && (
                        <input value={formData.sr_no_prefix} onChange={e => setFormData({...formData, sr_no_prefix: e.target.value})} className="w-full mt-2 bg-background/50 border border-border rounded-lg p-2 text-sm font-mono animate-in slide-in-from-top-1" placeholder="Default Prefix" />
                      )}
                    </div>

                    {/* IMEI Number */}
                    <div>
                      <label className="block text-sm font-medium text-text-muted mb-1.5">IMEI</label>
                      <select value={formData.enable_imei ? 'true' : 'false'} onChange={e => setFormData({...formData, enable_imei: e.target.value === 'true'})} className="w-full bg-background border border-border rounded-lg p-2.5 text-sm outline-none">
                        <option value="false">Non IMEI Number</option>
                        <option value="true">IMEI Tracked</option>
                      </select>
                      {formData.enable_imei && (
                        <input value={formData.imei_prefix} onChange={e => setFormData({...formData, imei_prefix: e.target.value})} className="w-full mt-2 bg-background/50 border border-border rounded-lg p-2 text-sm font-mono animate-in slide-in-from-top-1" placeholder="Default Prefix" />
                      )}
                    </div>

                    {/* Warranty */}
                    <div>
                      <label className="block text-sm font-medium text-text-muted mb-1.5">Warranty</label>
                      <select value={formData.enable_warranty ? 'true' : 'false'} onChange={e => setFormData({...formData, enable_warranty: e.target.value === 'true'})} className="w-full bg-background border border-border rounded-lg p-2.5 text-sm outline-none">
                        <option value="false">Non Warranty</option>
                        <option value="true">Warranty Tracker</option>
                      </select>
                      {formData.enable_warranty && (
                        <input value={formData.warranty_duration} onChange={e => setFormData({...formData, warranty_duration: e.target.value})} className="w-full mt-2 bg-background/50 border border-border rounded-lg p-2 text-sm animate-in slide-in-from-top-1" placeholder="e.g. 1 Year" />
                      )}
                    </div>

                    {/* Expiry */}
                    <div>
                      <label className="block text-sm font-medium text-text-muted mb-1.5">Validity / Expiry</label>
                      <select value={formData.enable_expiry ? 'true' : 'false'} onChange={e => setFormData({...formData, enable_expiry: e.target.value === 'true'})} className="w-full bg-background border border-border rounded-lg p-2.5 text-sm outline-none">
                        <option value="false">Non Expiry</option>
                        <option value="true">Validity Tracked</option>
                      </select>
                      {formData.enable_expiry && (
                        <input value={formData.expiry_duration} onChange={e => setFormData({...formData, expiry_duration: e.target.value})} className="w-full mt-2 bg-background/50 border border-border rounded-lg p-2 text-sm animate-in slide-in-from-top-1" placeholder="e.g. 30 Days" />
                      )}
                    </div>
                  </div>

                </div>
              </div>

            </div>
          </div>
        )}

        {activeTab === 'labels' && (
          <div className="animate-in slide-in-from-right-4">
            <h2 className="text-xl font-bold mb-4">Barcode Label Printing</h2>
            <p className="text-text-muted mb-6">Select products to generate raw barcode payload for connected thermal printers.</p>
            
            <div className="bg-surface/30 border border-border p-6 rounded-xl flex flex-col items-center justify-center gap-4">
              <span className="text-4xl">🖨️</span>
              <p>Ready to generate thermal printer payload containing Names, SKUs, and Prices.</p>
              <button onClick={handlePrintLabels} className="bg-rose-500 hover:bg-rose-600 text-white px-8 py-3 rounded-lg font-bold shadow-lg shadow-rose-500/30">
                Generate 5 Test Labels
              </button>
            </div>
          </div>
        )}

        {activeTab === 'expiry' && (
          <div className="animate-in slide-in-from-right-4 text-center py-12">
            <span className="text-5xl mb-4 block">⏳</span>
            <h2 className="text-xl font-bold mb-2">Lot & Expiry Tracking</h2>
            <p className="text-text-muted max-w-md mx-auto">Track product stock by Lot Numbers and Expiration Dates. Alerts will automatically trigger when batches approach expiry.</p>
            <div className="mt-8 grid grid-cols-3 gap-4 max-w-2xl mx-auto text-left">
              <div className="bg-danger/10 border border-danger/20 p-4 rounded-lg">
                <div className="text-danger font-bold">Expired</div>
                <div className="text-2xl mt-1">12 Items</div>
              </div>
              <div className="bg-warning/10 border border-warning/20 p-4 rounded-lg">
                <div className="text-warning font-bold">Expiring &lt; 30 Days</div>
                <div className="text-2xl mt-1">45 Items</div>
              </div>
              <div className="bg-success/10 border border-success/20 p-4 rounded-lg">
                <div className="text-success font-bold">Healthy Stock</div>
                <div className="text-2xl mt-1">8,040 Items</div>
              </div>
            </div>
          </div>
        )}



      </div>
    </div>
  );
}
