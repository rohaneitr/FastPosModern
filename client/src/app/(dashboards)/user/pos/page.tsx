'use client';

import React, { useState, useEffect } from 'react';
import api from '@/lib/api';
import { useCartStore } from '@/store/useCartStore';
import { useCurrency } from '@/lib/currency';

export default function POSPage() {
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Zustand Store
  const { items, taxRate, addItem, removeItem, updateQuantity, clearCart } = useCartStore();
  const { format } = useCurrency();

  // Mock products as fallback
  const mockProducts = [
    { id: 1, name: 'Wireless Headphones', price: 129.99, image: '🎧', category: 'Electronics' },
    { id: 2, name: 'Mechanical Keyboard', price: 149.50, image: '⌨️', category: 'Accessories' },
    { id: 3, name: 'USB-C Hub', price: 45.00, image: '🔌', category: 'Accessories' },
    { id: 4, name: 'Ergonomic Mouse', price: 79.99, image: '🖱️', category: 'Accessories' },
    { id: 5, name: '27" 4K Monitor', price: 349.00, image: '🖥️', category: 'Electronics' },
    { id: 6, name: 'Desk Mat', price: 24.99, image: '🔲', category: 'Office' },
  ];

  useEffect(() => {
    // Attempt to fetch real products from the Catalog Domain API
    api.get('/products')
      .then((res) => {
        // If successful and we have data, use it. Otherwise fallback to mock.
        if (res.data && res.data.data && res.data.data.length > 0) {
          setProducts(res.data.data);
        } else {
          setProducts(mockProducts);
        }
      })
      .catch((err) => {
        console.warn("API not reachable or returned error, using mock data.", err.message);
        setProducts(mockProducts);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  // Calculate Totals
  const subtotal = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const taxAmount = subtotal * taxRate;
  const total = subtotal + taxAmount;

  const handleCheckout = async () => {
    if (items.length === 0) return;
    
    // In a real app, payment_method and location_id would come from state/UI
    const payload = {
      location_id: 1, // fallback/default location
      payment_method: 'cash',
      tax_rate: taxRate,
      items: items.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity,
        price: item.price
      }))
    };

    try {
      const response = await api.post('/checkout', payload);
      alert(`Sale Successful! Invoice: ${response.data.invoice_no}`);
      clearCart();
    } catch (error: any) {
      console.error("Checkout failed", error);
      alert(`Checkout failed: ${error.response?.data?.message || error.message}`);
    }
  };

  return (
    <div className="flex h-full gap-4">
      
      {/* Left: Product Grid */}
      <div className="flex-1 flex flex-col gap-4">
        {/* Search & Filter Bar */}
        <div className="glass-card p-4 rounded-xl flex gap-4">
          <input 
            type="text" 
            placeholder="Search products by name or SKU..." 
            className="flex-1 bg-background/50 border border-border rounded-lg px-4 py-2 outline-none focus:border-primary transition-colors"
          />
          <button className="bg-surface border border-border px-4 py-2 rounded-lg hover:bg-white/5 transition-colors">
            Categories
          </button>
        </div>

        {/* Grid */}
        <div className="flex-1 overflow-y-auto pr-2">
          {loading ? (
            <div className="flex justify-center items-center h-full text-text-muted">Loading products...</div>
          ) : (
            <div className="grid grid-cols-3 xl:grid-cols-4 gap-4">
              {products.map((p) => (
                <div 
                  key={p.id} 
                  onClick={() => addItem(p)}
                  className="glass-card rounded-xl p-4 flex flex-col items-center justify-center gap-3 cursor-pointer hover:border-primary/50 hover:shadow-[0_0_15px_rgba(59,130,246,0.3)] transition-all group"
                >
                  <div className="text-4xl group-hover:scale-110 transition-transform duration-300">{p.image || '📦'}</div>
                  <div className="text-center">
                    <div className="font-medium line-clamp-1">{p.name}</div>
                    <div className="text-sm text-primary font-semibold">{format(parseFloat(p.price))}</div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Right: Cart/Register */}
      <div className="w-[400px] glass-card rounded-xl flex flex-col">
        {/* Cart Header */}
        <div className="p-4 border-b border-border flex justify-between items-center">
          <h2 className="font-semibold text-lg flex items-center gap-2">
            Current Sale 
            <span className="bg-primary/20 text-primary text-xs px-2 py-0.5 rounded-full">{items.length}</span>
          </h2>
          <button 
            onClick={clearCart}
            className="text-xs text-danger hover:bg-danger/10 px-2 py-1 rounded transition-colors"
          >
            Clear
          </button>
        </div>

        {/* Cart Items */}
        <div className="flex-1 p-4 overflow-y-auto flex flex-col gap-3">
          {items.length === 0 ? (
            <div className="flex-1 flex flex-col items-center justify-center text-text-muted">
              <div className="text-4xl mb-2 opacity-50">🛒</div>
              <p>Your cart is empty</p>
              <p className="text-xs mt-1 opacity-70">Click a product to add it</p>
            </div>
          ) : (
            items.map((item) => (
              <div key={item.id} className="bg-surface/80 border border-border rounded-lg p-3 flex flex-col gap-2">
                <div className="flex justify-between items-start">
                  <div className="font-medium truncate pr-2">{item.name}</div>
                  <button onClick={() => removeItem(item.id)} className="text-text-muted hover:text-danger text-sm">✕</button>
                </div>
                <div className="flex justify-between items-center mt-1">
                  <div className="flex items-center gap-3 bg-background/50 rounded-lg p-1 border border-border">
                    <button 
                      onClick={() => updateQuantity(item.id, item.quantity - 1)}
                      className="w-6 h-6 flex items-center justify-center rounded hover:bg-surface text-text-muted hover:text-white"
                    >-</button>
                    <span className="text-sm w-4 text-center">{item.quantity}</span>
                    <button 
                      onClick={() => updateQuantity(item.id, item.quantity + 1)}
                      className="w-6 h-6 flex items-center justify-center rounded hover:bg-surface text-text-muted hover:text-white"
                    >+</button>
                  </div>
                  <div className="font-semibold">{format(item.price * item.quantity)}</div>
                </div>
              </div>
            ))
          )}
        </div>

        {/* Cart Totals & Actions */}
        <div className="p-4 border-t border-border bg-surface/40 rounded-b-xl flex flex-col gap-3">
          <div className="flex justify-between text-sm text-text-muted">
            <span>Subtotal</span>
            <span>{format(subtotal)}</span>
          </div>
          <div className="flex justify-between text-sm text-text-muted">
            <span>Tax ({(taxRate * 100).toFixed(0)}%)</span>
            <span>{format(taxAmount)}</span>
          </div>
          <div className="flex justify-between font-bold text-2xl mt-2 pt-3 border-t border-border">
            <span>Total</span>
            <span className="text-success">{format(total)}</span>
          </div>
          
          <button 
            onClick={handleCheckout}
            disabled={items.length === 0}
            className={`w-full mt-4 font-bold py-4 rounded-xl transition-all shadow-lg text-lg
              ${items.length === 0 
                ? 'bg-surface text-text-muted cursor-not-allowed border border-border' 
                : 'bg-primary hover:bg-primary-hover text-white hover:shadow-[0_0_20px_rgba(59,130,246,0.4)]'
              }`}
          >
            Pay {format(total)}
          </button>
        </div>
      </div>
    </div>
  );
}
