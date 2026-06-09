"use client";

import React, { useState, useEffect, useCallback } from 'react';
import { Plus, Search, Loader2, Tags, Bookmark, Scale, FileEdit, Trash2, X, Check } from 'lucide-react';
import api from '@/lib/api';

// Interfaces
interface Category {
  id: number;
  name: string;
  description: string | null;
  status: string;
}

interface Brand {
  id: number;
  name: string;
  description: string | null;
}

interface Unit {
  id: number;
  name: string;
  short_name: string;
  allow_decimal: boolean | number;
}

type TabType = 'categories' | 'brands' | 'units';

export default function CatalogSettingsPage() {
  const [activeTab, setActiveTab] = useState<TabType>('categories');
  const [searchQuery, setSearchQuery] = useState('');
  
  const [categories, setCategories] = useState<Category[]>([]);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [units, setUnits] = useState<Unit[]>([]);
  
  const [isLoading, setIsLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [isAdmin, setIsAdmin] = useState(false);

  useEffect(() => {
    const storedUser = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');
    if (storedUser) {
      try {
        const parsed = JSON.parse(storedUser);
        const admin = parsed.roles?.some((r: any) => 
          ['BusinessAdmin', 'Manager', 'Admin', 'InventoryManager'].includes(r.name)
        );
        setIsAdmin(!!admin);
      } catch (e) {}
    }
  }, []);

  const [formData, setFormData] = useState({
    name: '',
    description: '',
    status: 'active',
    short_name: '',
    allow_decimal: false
  });

  const fetchData = useCallback(async (tab: TabType, search: string = '') => {
    setIsLoading(true);
    try {
      const response = await api.get(`/${tab}`, {
        params: { search }
      });
      const data = response.data.data;
      if (tab === 'categories') setCategories(data || []);
      else if (tab === 'brands') setBrands(data || []);
      else if (tab === 'units') setUnits(data || []);
    } catch (error) {
      console.error(`Failed to fetch ${tab}`, error);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      fetchData(activeTab, searchQuery);
    }, 400); // Debounce search
    return () => clearTimeout(timer);
  }, [activeTab, searchQuery, fetchData]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target;
    if (type === 'checkbox') {
      const checked = (e.target as HTMLInputElement).checked;
      setFormData(prev => ({ ...prev, [name]: checked }));
    } else {
      setFormData(prev => ({ ...prev, [name]: value }));
    }
  };

  const openModal = (item: any = null) => {
    if (item) {
      setEditingId(item.id);
      setFormData({
        name: item.name || '',
        description: item.description || '',
        status: item.status || 'active',
        short_name: item.short_name || '',
        allow_decimal: !!item.allow_decimal
      });
    } else {
      setEditingId(null);
      setFormData({
        name: '',
        description: '',
        status: 'active',
        short_name: '',
        allow_decimal: false
      });
    }
    setIsModalOpen(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      let payload: any = { name: formData.name };
      
      if (activeTab === 'categories') {
        payload.description = formData.description;
        payload.status = formData.status;
      } else if (activeTab === 'brands') {
        payload.description = formData.description;
      } else if (activeTab === 'units') {
        payload.short_name = formData.short_name;
        payload.allow_decimal = formData.allow_decimal;
      }

      if (editingId) {
        await api.put(`/${activeTab}/${editingId}`, payload);
      } else {
        await api.post(`/${activeTab}`, payload);
      }
      
      setIsModalOpen(false);
      fetchData(activeTab, searchQuery);
    } catch (error: any) {
      console.error(`Failed to save ${activeTab}`, error);
      alert(error.response?.data?.message || 'An error occurred while saving.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!window.confirm('Are you sure you want to delete this item?')) return;
    
    try {
      await api.delete(`/${activeTab}/${id}`);
      fetchData(activeTab, searchQuery);
    } catch (error: any) {
      console.error(`Failed to delete ${activeTab}`, error);
      if (error.response?.status === 400) {
        alert(error.response.data.message || 'Cannot delete this item because it is linked to existing products.');
      } else {
        alert('An error occurred while deleting.');
      }
    }
  };

  const getTabInfo = () => {
    switch (activeTab) {
      case 'categories': return { title: 'Category', icon: Tags, emptyText: 'No categories found.' };
      case 'brands': return { title: 'Brand', icon: Bookmark, emptyText: 'No brands found.' };
      case 'units': return { title: 'Unit', icon: Scale, emptyText: 'No units found.' };
    }
  };

  const { title, icon: TabIcon, emptyText } = getTabInfo();

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Tags className="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
            Catalog Setup
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Manage product categories, brands, and measurement units.
          </p>
        </div>
        
        <div className="flex items-center gap-3 w-full md:w-auto">
          <div className="relative flex-1 md:w-64">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <Search className="h-4 w-4 text-slate-400" />
            </div>
            <input
              type="text"
              placeholder={`Search ${activeTab}...`}
              className="block w-full pl-10 pr-3 py-2 border border-slate-300 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-shadow text-sm"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>
          {isAdmin && (
            <button 
              onClick={() => openModal()}
              className="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition-colors text-sm whitespace-nowrap shadow-sm hover:shadow"
            >
              <Plus className="w-4 h-4" />
              Add {title}
            </button>
          )}
        </div>
      </div>

      {/* Main Content Area */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        
        {/* Tab Navigation */}
        <div className="border-b border-slate-200 dark:border-slate-700">
          <nav className="flex -mb-px px-6 gap-6" aria-label="Tabs">
            <button
              onClick={() => { setActiveTab('categories'); setSearchQuery(''); }}
              className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 ${
                activeTab === 'categories'
                  ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                  : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300 dark:hover:border-slate-600'
              }`}
            >
              <Tags className="w-4 h-4" />
              Categories
            </button>
            <button
              onClick={() => { setActiveTab('brands'); setSearchQuery(''); }}
              className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 ${
                activeTab === 'brands'
                  ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                  : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300 dark:hover:border-slate-600'
              }`}
            >
              <Bookmark className="w-4 h-4" />
              Brands
            </button>
            <button
              onClick={() => { setActiveTab('units'); setSearchQuery(''); }}
              className={`whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 ${
                activeTab === 'units'
                  ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                  : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300 dark:hover:border-slate-600'
              }`}
            >
              <Scale className="w-4 h-4" />
              Units
            </button>
          </nav>
        </div>

        {/* Data Table */}
        <div className="overflow-x-auto min-h-[400px]">
          <table className="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead className="bg-slate-50 dark:bg-slate-800/50">
              <tr>
                <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                
                {activeTab === 'categories' && (
                  <>
                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Description</th>
                    <th scope="col" className="px-6 py-4 text-center text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                  </>
                )}
                
                {activeTab === 'brands' && (
                  <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Description</th>
                )}
                
                {activeTab === 'units' && (
                  <>
                    <th scope="col" className="px-6 py-4 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Short Name</th>
                    <th scope="col" className="px-6 py-4 text-center text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Decimals</th>
                  </>
                )}

                <th scope="col" className="px-6 py-4 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
              {isLoading ? (
                <tr>
                  <td colSpan={5} className="px-6 py-20 text-center">
                    <div className="flex flex-col items-center justify-center">
                      <Loader2 className="w-8 h-8 text-emerald-500 animate-spin mb-4" />
                      <p className="text-slate-500 dark:text-slate-400 text-sm">Loading {activeTab}...</p>
                    </div>
                  </td>
                </tr>
              ) : (activeTab === 'categories' ? categories.length : activeTab === 'brands' ? brands.length : units.length) === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-20 text-center">
                    <div className="flex flex-col items-center justify-center">
                      <div className="w-16 h-16 bg-slate-100 dark:bg-slate-700 rounded-full flex items-center justify-center mb-4 border border-slate-200 dark:border-slate-600">
                        <TabIcon className="w-8 h-8 text-slate-400" />
                      </div>
                      <h3 className="text-lg font-medium text-slate-900 dark:text-white mb-1">{emptyText}</h3>
                      <p className="text-slate-500 dark:text-slate-400 text-sm mb-6 max-w-sm">
                        {searchQuery ? "We couldn't find any matches for your search." : `Get started by adding your first ${title.toLowerCase()}.`}
                      </p>
                      {!searchQuery && isAdmin && (
                        <button 
                          onClick={() => openModal()}
                          className="flex items-center gap-2 px-4 py-2 bg-slate-900 dark:bg-white dark:text-slate-900 text-white rounded-lg font-medium transition-colors text-sm hover:opacity-90 shadow-sm"
                        >
                          <Plus className="w-4 h-4" />
                          Add {title}
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ) : (
                (activeTab === 'categories' ? categories : activeTab === 'brands' ? brands : units).map((item: any) => (
                  <tr key={item.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-slate-900 dark:text-white">{item.name}</div>
                    </td>
                    
                    {activeTab === 'categories' && (
                      <>
                        <td className="px-6 py-4">
                          <div className="text-sm text-slate-500 dark:text-slate-400 truncate max-w-xs">{item.description || '-'}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-center">
                          <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${item.status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400'}`}>
                            {item.status === 'active' ? 'Active' : 'Inactive'}
                          </span>
                        </td>
                      </>
                    )}
                    
                    {activeTab === 'brands' && (
                      <td className="px-6 py-4">
                        <div className="text-sm text-slate-500 dark:text-slate-400 truncate max-w-md">{item.description || '-'}</div>
                      </td>
                    )}
                    
                    {activeTab === 'units' && (
                      <>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <div className="text-sm text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 inline-block px-2 py-0.5 rounded font-mono">{item.short_name}</div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-center">
                          {item.allow_decimal ? (
                            <Check className="w-4 h-4 text-emerald-500 mx-auto" />
                          ) : (
                            <span className="text-slate-400">-</span>
                          )}
                        </td>
                      </>
                    )}

                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      {isAdmin && (
                        <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                          <button 
                            onClick={() => openModal(item)}
                            className="p-1.5 text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded transition-colors" 
                            title="Edit"
                          >
                            <FileEdit className="w-4 h-4" />
                          </button>
                          <button 
                            onClick={() => handleDelete(item.id)}
                            className="p-1.5 text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors" 
                            title="Delete"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Unified Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
          <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onClick={() => !isSubmitting && setIsModalOpen(false)}></div>
          <div className="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-200 border border-slate-200 dark:border-slate-700">
            <div className="flex justify-between items-center p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
              <h3 className="text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <TabIcon className="w-5 h-5 text-emerald-500" />
                {editingId ? `Edit ${title}` : `Add New ${title}`}
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
              <div className="space-y-4">
                {/* Common Name Field */}
                <div>
                  <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Name <span className="text-red-500">*</span></label>
                  <input 
                    required 
                    name="name" 
                    value={formData.name} 
                    onChange={handleInputChange} 
                    className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-emerald-500 focus:border-emerald-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors" 
                    placeholder={`E.g. ${activeTab === 'categories' ? 'Electronics' : activeTab === 'brands' ? 'Sony' : 'Kilogram'}`} 
                  />
                </div>
                
                {/* Description (Categories & Brands) */}
                {(activeTab === 'categories' || activeTab === 'brands') && (
                  <div>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Description</label>
                    <textarea 
                      name="description" 
                      value={formData.description} 
                      onChange={handleInputChange} 
                      rows={3}
                      className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-emerald-500 focus:border-emerald-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors" 
                      placeholder="Optional description..." 
                    />
                  </div>
                )}

                {/* Status (Categories) */}
                {activeTab === 'categories' && (
                  <div>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Status</label>
                    <select 
                      name="status" 
                      value={formData.status} 
                      onChange={handleInputChange} 
                      className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-emerald-500 focus:border-emerald-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors"
                    >
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>
                )}

                {/* Unit Specific Fields */}
                {activeTab === 'units' && (
                  <>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Short Name <span className="text-red-500">*</span></label>
                      <input 
                        required 
                        name="short_name" 
                        value={formData.short_name} 
                        onChange={handleInputChange} 
                        className="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-emerald-500 focus:border-emerald-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm transition-colors" 
                        placeholder="E.g. kg, pcs, box" 
                      />
                    </div>
                    <div className="flex items-center mt-4 pt-2">
                      <input 
                        type="checkbox"
                        id="allow_decimal"
                        name="allow_decimal" 
                        checked={formData.allow_decimal} 
                        onChange={handleInputChange} 
                        className="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-slate-300 rounded" 
                      />
                      <label htmlFor="allow_decimal" className="ml-2 block text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        Allow Decimal Values <span className="font-normal text-slate-500">(e.g. 1.5 kg)</span>
                      </label>
                    </div>
                  </>
                )}
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
                  className="flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium text-sm transition-colors shadow-sm disabled:opacity-70 disabled:cursor-not-allowed"
                >
                  {isSubmitting ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin" />
                      Saving...
                    </>
                  ) : 'Save'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
