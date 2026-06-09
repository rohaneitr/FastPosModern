'use client';

import React from 'react';
import { useTranslation } from '@/lib/i18n';
import { useCurrency } from '@/lib/currency';

interface ProductGridProps {
  products: any[];
  isLoading: boolean;
  searchQuery: string;
  setSearchQuery: (q: string) => void;
  activeTab: 'all' | 'general' | 'pharmacy';
  setActiveTab: (tab: 'all' | 'general' | 'pharmacy') => void;
  hasPharmacyModule: boolean;
  registerIsOpen: boolean;
  isRegisterLoading: boolean;
  onProductSelect: (p: any) => void;
  onFetchAlternatives: (genericName: string) => void;
  searchRef: React.RefObject<HTMLInputElement | null>;
  onCloseRegisterRequest: () => void;
}

export function ProductGrid({
  products,
  isLoading,
  searchQuery,
  setSearchQuery,
  activeTab,
  setActiveTab,
  hasPharmacyModule,
  registerIsOpen,
  isRegisterLoading,
  onProductSelect,
  onFetchAlternatives,
  searchRef,
  onCloseRegisterRequest
}: ProductGridProps) {
  const { t } = useTranslation();
  const { format } = useCurrency();

  const filteredProducts = products.filter(p => {
    const matchesSearch = 
      p.name.toLowerCase().includes(searchQuery.toLowerCase()) || 
      (p.sku && p.sku.toLowerCase().includes(searchQuery.toLowerCase())) || 
      (p.generic_name && p.generic_name.toLowerCase().includes(searchQuery.toLowerCase()));
      
    const matchesTab = activeTab === 'all' 
      ? true 
      : activeTab === 'pharmacy' 
        ? (p.is_medicine || !!p.generic_name) 
        : (!p.is_medicine && !p.generic_name);
        
    return matchesSearch && matchesTab;
  });

  return (
    <div className="flex-1 flex flex-col gap-4">
      {/* Search & Filter Bar */}
      <div className="glass-card p-4 rounded-xl flex flex-col gap-4">
        <div className="flex gap-4 items-center justify-between">
          <div className="flex gap-4 flex-1 max-w-xl">
            <input 
              ref={searchRef}
              type="text" 
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder={t('pos.searchProducts') || 'Search (Press Ctrl+K)'} 
              className="flex-1 bg-background/50 border border-border rounded-lg px-4 py-2 outline-none focus:border-primary transition-colors disabled:opacity-50"
              disabled={!registerIsOpen}
            />
            <button 
              className="bg-surface border border-border px-4 py-2 rounded-lg hover:bg-white/5 transition-colors disabled:opacity-50" 
              disabled={!registerIsOpen}
            >
              Categories
            </button>
          </div>
          {registerIsOpen && (
            <button 
              onClick={onCloseRegisterRequest} 
              className="bg-rose-500/10 text-rose-400 border border-rose-500/20 px-4 py-2 rounded-lg hover:bg-rose-500/20 transition-colors font-bold text-sm"
            >
              Close Register
            </button>
          )}
        </div>
        {hasPharmacyModule && (
          <div className="flex gap-2">
            {(['all', 'general', 'pharmacy'] as const).map(tab => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`px-4 py-1.5 rounded-full text-sm font-bold transition-all border ${activeTab === tab ? 'bg-emerald-500/20 border-emerald-500/50 text-emerald-400 shadow-sm' : 'bg-surface border-border text-text-muted hover:text-white'}`}
              >
                {tab === 'all' ? 'All Items' : tab === 'general' ? 'General 🛒' : 'Pharmacy 💊'}
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Grid */}
      <div className="flex-1 overflow-y-auto pr-2 relative">
        {isLoading ? (
          <div className="flex justify-center items-center h-full text-text-muted">Loading products...</div>
        ) : (
          <div className="grid grid-cols-3 xl:grid-cols-4 gap-4 pb-12">
            {filteredProducts.map((p) => (
              <div 
                key={p.id} 
                onClick={() => onProductSelect(p)}
                className="glass-card rounded-xl p-4 flex flex-col items-center justify-center gap-3 cursor-pointer hover:border-primary/50 hover:shadow-[0_0_15px_rgba(59,130,246,0.3)] transition-all group relative"
              >
                <div className="text-4xl group-hover:scale-110 transition-transform duration-300">{p.image || '📦'}</div>
                <div className="text-center w-full">
                  <div className="font-medium line-clamp-1">{p.name} {hasPharmacyModule && (p.is_medicine || p.generic_name) ? '💊' : '🛒'}</div>
                  {hasPharmacyModule && p.generic_name && (
                    <div className="text-[10px] text-text-muted truncate mt-0.5">{p.generic_name}</div>
                  )}
                  <div className="text-sm text-primary font-semibold mt-1">
                    {format(parseFloat(p.price || p.sell_price_inc_tax || 0))}
                  </div>
                </div>
                {hasPharmacyModule && p.generic_name && (
                  <button 
                    onClick={(e) => { e.stopPropagation(); onFetchAlternatives(p.generic_name); }} 
                    className="absolute top-2 right-2 text-[10px] bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-2 py-0.5 rounded-full hover:bg-emerald-500/40 z-10"
                  >
                    Alternatives
                  </button>
                )}
              </div>
            ))}
            {products.length > 0 && filteredProducts.length === 0 && (
               <div className="col-span-3 text-center text-text-muted py-8">
                 {t('products.noProductsMatch') || 'No products match your search.'}
               </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
