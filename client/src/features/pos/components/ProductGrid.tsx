'use client';

import React, { useMemo } from 'react';
import { Search, AlertCircle, Plus } from 'lucide-react';
import clsx from 'clsx';
import type { Product } from '../hooks/usePOSTerminal';

// ── ProductCard Skeleton ───────────────────────────────────────────────────

function ProductGridSkeleton() {
  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      {Array.from({ length: 15 }).map((_, i) => (
        <div key={i} className="bg-slate-100 rounded-xl p-4 h-32 animate-pulse border border-slate-200 flex flex-col justify-between">
          <div className="w-2/3 h-4 bg-slate-200 rounded" />
          <div className="flex justify-between items-end">
            <div className="w-1/3 h-5 bg-slate-200 rounded" />
            <div className="w-8 h-8 bg-slate-200 rounded-full" />
          </div>
        </div>
      ))}
    </div>
  );
}

// ── ProductGrid ────────────────────────────────────────────────────────────

interface ProductGridProps {
  products: Product[];
  searchTerm: string;
  isLoading: boolean;
  hasError: boolean;
  onAddItem: (product: Product) => void;
}

export function ProductGrid({ products, searchTerm, isLoading, hasError, onAddItem }: ProductGridProps) {
  const filtered = useMemo(() => {
    if (!searchTerm) return products;
    return products.filter(p => p.name.toLowerCase().includes(searchTerm.toLowerCase()));
  }, [products, searchTerm]);

  if (isLoading) return <ProductGridSkeleton />;

  if (hasError) {
    return (
      <div className="flex flex-col items-center justify-center h-full text-slate-500">
        <AlertCircle className="w-12 h-12 text-rose-500 mb-3" />
        <p className="text-lg font-medium text-slate-800">Failed to load catalog</p>
        <p className="text-sm">Please check your connection and try again.</p>
      </div>
    );
  }

  if (filtered.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center h-full text-slate-500">
        <div className="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
          <Search className="w-8 h-8 text-slate-400" />
        </div>
        <p className="text-lg font-medium text-slate-800">No products found</p>
        <p className="text-sm">Try adjusting your search terms.</p>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
      {filtered.map(product => (
        <button
          key={product.id}
          onClick={() => onAddItem(product)}
          className="group flex flex-col justify-between text-left bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all active:scale-95 duration-150 h-32 relative overflow-hidden"
        >
          <div className="z-10">
            <h3 className="font-semibold text-slate-800 leading-tight line-clamp-2">{product.name}</h3>
            {product.stock <= 5 && (
              <span className="text-xs font-medium text-rose-500 mt-1 inline-block">
                Only {product.stock} left
              </span>
            )}
          </div>
          <div className="flex items-center justify-between mt-2 z-10 w-full">
            <span className="font-bold text-indigo-600">${parseFloat(product.price).toFixed(2)}</span>
            <div className="w-11 h-11 rounded-full bg-slate-50 flex items-center justify-center group-hover:bg-indigo-50 text-slate-400 group-hover:text-indigo-600 transition-colors">
              <Plus className="w-5 h-5" />
            </div>
          </div>
          {/* Subtle hover gradient */}
          <div className="absolute -bottom-4 -right-4 w-16 h-16 bg-gradient-to-br from-indigo-50 to-transparent rounded-full opacity-0 group-hover:opacity-100 transition-opacity" />
        </button>
      ))}
    </div>
  );
}
