'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';

export default function CategoriesPage() {
  const [categories, setCategories] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState<any>(null);
  const [formData, setFormData] = useState({ name: '', description: '', parent_id: '' });

  useEffect(() => {
    fetchCategories();
  }, []);

  const fetchCategories = async () => {
    setLoading(true);
    try {
      const res = await api.get('/categories');
      setCategories(res.data.data || res.data || []);
    } catch (err: any) {
      console.error("Failed to fetch categories:", err);
      // Removed mock fallback to enforce real API
    } finally {
      setLoading(false);
    }
  };

  const handleAddCategory = (parentId: any = '') => {
    setEditingCategory(null);
    setFormData({ name: '', description: '', parent_id: parentId });
    setIsModalOpen(true);
  };

  const handleEditCategory = (cat: any) => {
    setEditingCategory(cat);
    setFormData({ name: cat.name, description: cat.description || '', parent_id: cat.parent_id || '' });
    setIsModalOpen(true);
  };

  const handleDeleteCategory = async (id: number) => {
    if (!confirm('Are you sure you want to delete this category?')) return;
    try {
      await api.delete(`/categories/${id}`);
      fetchCategories();
    } catch (e: any) {
      alert(`Failed to delete category: ${e.response?.data?.message || e.message}`);
    }
  };

  const handleSave = async () => {
    try {
      if (editingCategory) {
        await api.put(`/categories/${editingCategory.id}`, formData);
      } else {
        await api.post('/categories', formData);
      }
      setIsModalOpen(false);
      fetchCategories();
    } catch (e: any) {
      alert(`Failed to save category: ${e.response?.data?.message || e.message}`);
    }
  };

  const parentCategories = categories.filter(c => !c.parent_id);

  return (
    <div className="flex flex-col h-full gap-8 animate-in fade-in duration-500 pb-12">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-emerald-400 to-cyan-500">
            Category Management
          </h1>
          <p className="text-text-muted mt-1">Organize your products with deep taxonomy hierarchies.</p>
        </div>
        <button 
          onClick={() => handleAddCategory()}
          className="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-2 rounded-lg shadow-lg font-bold transition-colors"
        >
          + Add Root Category
        </button>
      </div>

      <div className="glass-card rounded-xl border border-border p-6 min-h-[400px]">
        {loading ? (
          <div className="text-center p-8 text-text-muted">Loading taxonomy...</div>
        ) : (
          <div className="flex flex-col gap-4">
            {parentCategories.length === 0 ? (
              <div className="text-center p-8 text-text-muted">No categories found. Create one above!</div>
            ) : (
              parentCategories.map(parent => {
                const subCats = categories.filter(c => c.parent_id == parent.id);
                return (
                  <div key={parent.id} className="bg-surface/30 border border-border rounded-xl p-4 transition-all hover:border-emerald-500/50">
                    <div className="flex justify-between items-center mb-4">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center text-emerald-400 font-bold text-lg">
                          {parent.name.charAt(0)}
                        </div>
                        <div>
                          <h3 className="font-bold text-lg">{parent.name}</h3>
                          <p className="text-xs text-text-muted">{parent.description || 'Root Category'}</p>
                        </div>
                      </div>
                      <div className="flex gap-2">
                        <button onClick={() => handleAddCategory(parent.id)} className="bg-surface border border-border px-3 py-1 rounded text-xs font-medium hover:bg-white/10">+ Sub-Category</button>
                        <button onClick={() => handleEditCategory(parent)} className="bg-surface border border-border px-3 py-1 rounded text-xs font-medium text-blue-400 hover:bg-white/10">Edit</button>
                        <button onClick={() => handleDeleteCategory(parent.id)} className="bg-surface border border-danger/30 px-3 py-1 rounded text-xs font-medium text-danger hover:bg-danger/10">Delete</button>
                      </div>
                    </div>

                    {/* Sub-Categories */}
                    {subCats.length > 0 && (
                      <div className="pl-14 flex flex-col gap-2">
                        {subCats.map(sub => (
                          <div key={sub.id} className="flex justify-between items-center bg-background/50 border border-border/50 p-3 rounded-lg hover:bg-surface transition-colors">
                            <div className="flex items-center gap-2">
                              <span className="text-text-muted text-xs">↳</span>
                              <span className="font-medium text-sm">{sub.name}</span>
                              <span className="text-xs text-text-muted ml-2">- {sub.description}</span>
                            </div>
                            <div className="flex gap-3">
                              <button onClick={() => handleEditCategory(sub)} className="text-xs text-blue-400 hover:text-blue-300 font-medium">Edit</button>
                              <button onClick={() => handleDeleteCategory(sub.id)} className="text-xs text-danger hover:text-danger/80 font-medium">Delete</button>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                );
              })
            )}
          </div>
        )}
      </div>

      {/* Add/Edit Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center z-50 animate-in fade-in">
          <div className="bg-surface border border-border p-6 rounded-2xl w-full max-w-md shadow-2xl">
            <h2 className="text-2xl font-bold mb-4">{editingCategory ? 'Edit Category' : 'Add New Category'}</h2>
            
            <div className="flex flex-col gap-4">
              <div>
                <label className="block text-sm text-text-muted mb-1">Parent Category</label>
                <select 
                  value={formData.parent_id} 
                  onChange={e => setFormData({...formData, parent_id: e.target.value})} 
                  className="w-full bg-background/50 border border-border rounded-lg p-2"
                >
                  <option value="">None (Root Category)</option>
                  {parentCategories.map(p => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
                <p className="text-xs text-text-muted mt-1">Leave as "None" to create a top-level category.</p>
              </div>

              <div>
                <label className="block text-sm text-text-muted mb-1">Category Name</label>
                <input 
                  value={formData.name} 
                  onChange={e => setFormData({...formData, name: e.target.value})} 
                  className="w-full bg-background/50 border border-border rounded-lg p-2" 
                  placeholder="e.g. Electronics" 
                />
              </div>

              <div>
                <label className="block text-sm text-text-muted mb-1">Description (Optional)</label>
                <textarea 
                  value={formData.description} 
                  onChange={e => setFormData({...formData, description: e.target.value})} 
                  className="w-full bg-background/50 border border-border rounded-lg p-2 h-20 resize-none" 
                  placeholder="Short description..."
                ></textarea>
              </div>

              <div className="mt-4 flex gap-3">
                <button onClick={handleSave} className="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white py-2 rounded-lg font-bold transition-colors shadow-lg shadow-emerald-500/20">
                  {editingCategory ? 'Update Category' : 'Save Category'}
                </button>
                <button onClick={() => setIsModalOpen(false)} className="flex-1 bg-surface border border-border py-2 rounded-lg font-medium text-white hover:bg-white/10 transition-colors">
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
