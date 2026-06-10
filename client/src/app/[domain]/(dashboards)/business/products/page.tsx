"use client";

import React, { useState, useEffect, useCallback } from 'react';
import { Plus, Search, Loader2, Package, Tag, Hash, FileEdit, Trash2, X } from 'lucide-react';
import api from '@/lib/api';
import { FeatureGate } from '@/components/common/FeatureGate';

// Interfaces
interface Category {
  id: number;
  name: string;
}

interface Brand {
  id: number;
  name: string;
}

interface Unit {
  id: number;
  name: string;
  short_name: string;
}

interface Product {
  id: number;
  name: string;
  sku: string;
  barcode_type: string;
  category_id: number | null;
  brand_id: number | null;
  unit_id: number | null;
  purchase_price: number;
  selling_price: number;
  alert_quantity: number;
  current_stock: number;
  is_active: boolean;
  image_path: string | null;
  category?: Category | null;
  brand?: Brand | null;
  unit?: Unit | null;
}

interface PaginationMeta {
  current_page: number;
  last_page: number;
  total: number;
}

export default function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [page, setPage] = useState(1);
  
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  // For dropdowns
  const [categories, setCategories] = useState<Category[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [units, setUnits] = useState<Unit[]>([]);
  
  // Form state
  const [formData, setFormData] = useState({
    name: '',
    sku: '',
    category_id: '',
    brand_id: '',
    unit_id: '',
    purchase_price: '',
    selling_price: '',
    alert_quantity: '0',
    current_stock: '0'
  });

  const fetchProducts = useCallback(async (search = '', pageNum = 1) => {
    setIsLoading(true);
    try {
      const response = await api.get('/products', {
        params: { search, page: pageNum }
      });
      // Handle the nested data structure typical of Laravel paginators
      const data = response.data.data;
      if (data && Array.isArray(data.data)) {
        setProducts(data.data);
        setMeta({
          current_page: data.current_page,
          last_page: data.last_page,
          total: data.total
        });
      } else {
        setProducts(data || []);
      }
    } catch (error) {
    } finally {
      setIsLoading(false);
    }
  }, []);

  const fetchDependencies = async () => {
    try {
      // Intentionally suppressing errors for these endpoints if they don't exist yet
      const [catRes, brandRes, unitRes] = await Promise.all([
        api.get('/categories').catch(() => ({ data: { data: [] } })),
        api.get('/brands').catch(() => ({ data: { data: [] } })),
        api.get('/units').catch(() => ({ data: { data: [] } }))
      ]);
      setCategories(catRes.data?.data || []);
      setBrands(brandRes.data?.data || []);
      setUnits(unitRes.data?.data || []);
    } catch (error) {
    }
  };

  useEffect(() => {
    fetchDependencies();
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      fetchProducts(searchQuery, page);
    }, 500); // Debounce search
    return () => clearTimeout(timer);
  }, [searchQuery, page, fetchProducts]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      await api.post('/products', {
        name: formData.name,
        sku: formData.sku || null,
        category_id: formData.category_id ? parseInt(formData.category_id) : null,
        brand_id: formData.brand_id ? parseInt(formData.brand_id) : null,
        unit_id: formData.unit_id ? parseInt(formData.unit_id) : null,
        purchase_price: parseFloat(formData.purchase_price),
        selling_price: parseFloat(formData.selling_price),
        alert_quantity: parseInt(formData.alert_quantity) || 0,
        current_stock: parseFloat(formData.current_stock) || 0
      });
      setIsModalOpen(false);
      setFormData({
        name: '', sku: '', category_id: '', brand_id: '', unit_id: '',
        purchase_price: '', selling_price: '', alert_quantity: '0', current_stock: '0'
      });
      fetchProducts(searchQuery, 1);
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to save product. Please check your inputs.';
      alert(errorMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header section */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Package className="w-6 h-6 text-blue-600 dark:text-blue-400" />
            Inventory Products
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Manage your product catalog, pricing, and stock levels.
          </p>
        </div>
        
        <div className="flex items-center gap-3 w-full md:w-auto">
          <div className="relative flex-1 md:w-64">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <Search className="h-4 w-4 text-slate-400" />
            </div>
            <input
              type="text"
              placeholder="Search by name or SKU..."
              className="block w-full pl-10 pr-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow text-sm"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>
            <FeatureGate permission="product.create">
              <button 
                onClick={() => setIsModalOpen(true)}
                className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors text-sm whitespace-nowrap shadow-sm hover:shadow"
              >
                <Plus className="w-4 h-4" />
                Add New Product
              </button>
            </FeatureGate>
          </div>
        </div>

      {/* Data Table section */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead className="bg-slate-50 dark:bg-slate-800/50">
              <tr>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Product Info</th>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">SKU</th>
                <th scope="col" className="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pricing</th>
                <th scope="col" className="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Stock</th>
                <th scope="col" className="px-6 py-4 text-center text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                <th scope="col" className="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
              {isLoading ? (
                <tr>
                  <td colSpan={6} className="px-6 py-12 text-center">
                    <div className="flex flex-col items-center justify-center">
                      <Loader2 className="w-8 h-8 text-blue-500 animate-spin mb-4" />
                      <p className="text-slate-500 dark:text-slate-400 text-sm">Loading products...</p>
                    </div>
                  </td>
                </tr>
              ) : products.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-6 py-16 text-center">
                    <div className="flex flex-col items-center justify-center">
                      <div className="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4 border border-slate-200 dark:border-slate-700">
                        <Package className="w-8 h-8 text-slate-400" />
                      </div>
                      <h3 className="text-lg font-medium text-slate-900 dark:text-white mb-1">No products found</h3>
                      <p className="text-slate-500 dark:text-slate-400 text-sm mb-6 max-w-sm">
                        {searchQuery ? "We couldn't find any products matching your search." : "Get started by adding your first product to the inventory."}
                      </p>
                      {!searchQuery && (
                        <FeatureGate permission="product.create">
                          <button 
                            onClick={() => setIsModalOpen(true)}
                            className="flex items-center gap-2 px-4 py-2 bg-slate-900 dark:bg-white dark:text-slate-900 text-white rounded-lg font-medium transition-colors text-sm hover:opacity-90 shadow-sm"
                          >
                            <Plus className="w-4 h-4" />
                            Add Product
                          </button>
                        </FeatureGate>
                      )}
                    </div>
                  </td>
                </tr>
              ) : (
                products.map((product) => (
                  <tr key={product.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="flex-shrink-0 h-10 w-10 bg-slate-100 dark:bg-slate-700 rounded-md flex items-center justify-center border border-slate-200 dark:border-slate-600">
                          {product.image_path ? (
                            <img src={product.image_path} alt={product.name} className="h-10 w-10 rounded-md object-cover" />
                          ) : (
                            <Tag className="h-5 w-5 text-slate-400" />
                          )}
                        </div>
                        <div className="ml-4">
                          <div className="text-sm font-medium text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                            {product.name}
                          </div>
                          <div className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {product.category?.name || 'Uncategorized'} • {product.brand?.name || 'No Brand'}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-300 font-mono">
                        <Hash className="w-3.5 h-3.5 text-slate-400" />
                        {product.sku}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right">
                      <div className="text-sm text-slate-900 dark:text-white font-medium">${parseFloat(product.selling_price.toString()).toFixed(2)}</div>
                      <div className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Cost: ${parseFloat(product.purchase_price.toString()).toFixed(2)}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right">
                      <div className="text-sm font-medium text-slate-900 dark:text-white">
                        {product.current_stock} <span className="text-slate-500 font-normal text-xs">{product.unit?.short_name || 'qty'}</span>
                      </div>
                      {product.current_stock <= product.alert_quantity && (
                        <div className="text-xs text-red-600 dark:text-red-400 font-medium mt-0.5">Low Stock</div>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-center">
                      <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${product.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400'}`}>
                        {product.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <FeatureGate permission="product.update">
                          <button className="p-1.5 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded transition-colors" title="Edit">
                            <FileEdit className="w-4 h-4" />
                          </button>
                        </FeatureGate>
                        <FeatureGate permission="product.delete">
                          <button className="p-1.5 text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors" title="Delete">
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </FeatureGate>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        
        {/* Pagination */}
        {!isLoading && meta && meta.last_page > 1 && (
          <div className="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <span className="text-sm text-slate-500 dark:text-slate-400">
              Showing page <span className="font-medium text-slate-900 dark:text-white">{meta.current_page}</span> of <span className="font-medium text-slate-900 dark:text-white">{meta.last_page}</span>
            </span>
            <div className="flex gap-2">
              <button 
                onClick={() => setPage(p => Math.max(1, p - 1))}
                disabled={page === 1}
                className="px-3 py-1 border border-slate-300 dark:border-slate-600 rounded text-sm disabled:opacity-50 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
              >
                Previous
              </button>
              <button 
                onClick={() => setPage(p => Math.min(meta.last_page, p + 1))}
                disabled={page === meta.last_page}
                className="px-3 py-1 border border-slate-300 dark:border-slate-600 rounded text-sm disabled:opacity-50 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Add Product Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
          <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onClick={() => !isSubmitting && setIsModalOpen(false)}></div>
          <div className="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200 border border-slate-200 dark:border-slate-700">
            <div className="flex justify-between items-center p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
              <h3 className="text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <Package className="w-5 h-5 text-blue-500" />
                Add New Product
              </h3>
              <button 
                onClick={() => !isSubmitting && setIsModalOpen(false)}
                disabled={isSubmitting}
                className="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 p-1 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors disabled:opacity-50"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            
            <form onSubmit={handleSubmit} className="p-6">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div className="sm:col-span-2">
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Product Name <span className="text-red-500">*</span></label>
                  <input 
                    required 
                    name="name" 
                    value={formData.name} 
                    onChange={handleInputChange} 
                    className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors" 
                    placeholder="E.g. Wireless Mouse" 
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">SKU Code</label>
                  <input 
                    name="sku" 
                    value={formData.sku} 
                    onChange={handleInputChange} 
                    className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors placeholder:text-slate-400" 
                    placeholder="Auto-generated if empty" 
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Unit <span className="text-red-500">*</span></label>
                  <select 
                    required
                    name="unit_id" 
                    value={formData.unit_id} 
                    onChange={handleInputChange} 
                    className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors"
                  >
                    <option value="" disabled>Select Unit</option>
                    {units.map(u => <option key={u.id} value={u.id}>{u.name} ({u.short_name})</option>)}
                    {units.length === 0 && <option value="1">Pieces (Mock)</option>}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Category</label>
                  <select 
                    name="category_id" 
                    value={formData.category_id} 
                    onChange={handleInputChange} 
                    className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors"
                  >
                    <option value="">No Category</option>
                    {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Brand</label>
                  <select 
                    name="brand_id" 
                    value={formData.brand_id} 
                    onChange={handleInputChange} 
                    className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors"
                  >
                    <option value="">No Brand</option>
                    {brands.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Purchase Price <span className="text-red-500">*</span></label>
                  <div className="relative rounded-lg shadow-sm">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span className="text-slate-500 sm:text-sm">$</span>
                    </div>
                    <input 
                      type="number" 
                      step="0.01" 
                      min="0"
                      required
                      name="purchase_price" 
                      value={formData.purchase_price} 
                      onChange={handleInputChange} 
                      className="block w-full pl-7 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors" 
                      placeholder="0.00" 
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Selling Price <span className="text-red-500">*</span></label>
                  <div className="relative rounded-lg shadow-sm">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <span className="text-slate-500 sm:text-sm">$</span>
                    </div>
                    <input 
                      type="number" 
                      step="0.01" 
                      min="0"
                      required
                      name="selling_price" 
                      value={formData.selling_price} 
                      onChange={handleInputChange} 
                      className="block w-full pl-7 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors" 
                      placeholder="0.00" 
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Current Stock</label>
                  <input 
                    type="number" 
                    step="0.01" 
                    name="current_stock" 
                    value={formData.current_stock} 
                    onChange={handleInputChange} 
                    className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors" 
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Alert Quantity (Low Stock)</label>
                  <input 
                    type="number" 
                    name="alert_quantity" 
                    value={formData.alert_quantity} 
                    onChange={handleInputChange} 
                    className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors" 
                  />
                </div>
              </div>
              
              <div className="mt-8 pt-5 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                <button 
                  type="button" 
                  onClick={() => setIsModalOpen(false)}
                  disabled={isSubmitting}
                  className="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 font-medium text-sm transition-colors shadow-sm disabled:opacity-50"
                >
                  Cancel
                </button>
                <button 
                  type="submit" 
                  disabled={isSubmitting}
                  className="flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition-colors shadow-sm disabled:opacity-70 disabled:cursor-not-allowed"
                >
                  {isSubmitting ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin" />
                      Saving...
                    </>
                  ) : 'Save Product'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
