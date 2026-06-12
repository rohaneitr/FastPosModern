'use client';

import React from 'react';
import { ShoppingCart, AlertCircle, Minus, Plus, Trash2, Loader2, Banknote } from 'lucide-react';
import clsx from 'clsx';

interface CartItem {
  id: number;
  product_id: number;
  name: string;
  price: string | number;
  quantity: number;
  stockError?: string | null;
}

interface CartPanelProps {
  items: CartItem[];
  isOnline: boolean;
  isCheckingOut: boolean;
  getCartTotal: () => string;
  onUpdateQuantity: (id: number, qty: number) => void;
  onRemoveItem: (id: number) => void;
  onCheckout: () => void;
}

/**
 * CartPanel — Right panel of the POS terminal
 * Extracted from terminal/page.tsx L355–473.
 */
export function CartPanel({
  items,
  isOnline,
  isCheckingOut,
  getCartTotal,
  onUpdateQuantity,
  onRemoveItem,
  onCheckout,
}: CartPanelProps) {
  return (
    <div className="w-full md:w-[400px] flex flex-col h-[50vh] md:h-full bg-white shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)] md:shadow-xl z-40 fixed md:relative bottom-0 left-0 md:bottom-auto md:left-auto">
      {/* Header */}
      <div className="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 min-h-[60px]">
        <div className="flex items-center gap-2">
          <ShoppingCart className="w-5 h-5 text-slate-600" />
          <h2 className="font-bold text-lg text-slate-800">Current Order</h2>
        </div>
        <span className="bg-slate-200 text-slate-700 text-xs font-bold px-2 py-1 rounded-md">
          {items.length} {items.length === 1 ? 'Item' : 'Items'}
        </span>
      </div>

      {/* Cart Items */}
      <div className="flex-1 overflow-y-auto p-4 space-y-3">
        {items.length === 0 ? (
          <div className="h-full flex flex-col items-center justify-center text-slate-400">
            <ShoppingCart className="w-12 h-12 mb-3 text-slate-200" />
            <p className="font-medium text-slate-500">Your cart is empty</p>
            <p className="text-sm text-slate-400 text-center px-6 mt-1">
              Tap products on the left to add them to your order.
            </p>
          </div>
        ) : (
          items.map(item => (
            <div
              key={item.id}
              className={clsx(
                'flex flex-col p-3 rounded-lg border transition-all relative overflow-hidden group',
                item.stockError
                  ? 'border-rose-500 bg-rose-50 shadow-[0_0_0_1px_rgba(244,63,94,1)] animate-[pulse_2s_ease-in-out_infinite]'
                  : 'border-slate-200 bg-white hover:border-slate-300'
              )}
            >
              <div className="flex items-start justify-between gap-3 relative z-10">
                <div className="flex-1">
                  <h4 className="font-semibold text-slate-800 text-sm">{item.name}</h4>
                  <div className="text-indigo-600 font-bold text-sm mt-0.5">
                    ${parseFloat(item.price as string).toFixed(2)}
                  </div>
                </div>
                <div className="flex items-center gap-2 bg-slate-100 rounded-lg border border-slate-200 p-1">
                  <button
                    onClick={() => onUpdateQuantity(item.id, item.quantity - 1)}
                    disabled={item.quantity <= 1}
                    className="w-11 h-11 flex items-center justify-center rounded-md text-slate-600 hover:bg-white hover:shadow-sm hover:text-slate-900 transition-all active:scale-95 disabled:opacity-40"
                  >
                    <Minus className="w-5 h-5" />
                  </button>
                  <span className="w-8 text-center text-base font-semibold text-slate-800">{item.quantity}</span>
                  <button
                    onClick={() => onUpdateQuantity(item.id, item.quantity + 1)}
                    className="w-11 h-11 flex items-center justify-center rounded-md text-slate-600 hover:bg-white hover:shadow-sm hover:text-slate-900 transition-all active:scale-95"
                  >
                    <Plus className="w-5 h-5" />
                  </button>
                </div>
                <button
                  onClick={() => onRemoveItem(item.id)}
                  className="w-11 h-11 flex items-center justify-center rounded-full text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-colors absolute -right-2 -top-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 md:group-hover:relative md:group-hover:top-0 md:group-hover:right-0 active:scale-95"
                >
                  <Trash2 className="w-5 h-5" />
                </button>
              </div>

              {/* Stock Error / Offline Warning */}
              {item.stockError ? (
                <div className="mt-2 flex items-center gap-1.5 text-xs font-bold text-rose-600 bg-rose-100/50 p-1.5 rounded-md">
                  <AlertCircle className="w-3.5 h-3.5" />
                  {item.stockError}
                </div>
              ) : !isOnline ? (
                <div className="mt-2 flex items-center gap-1.5 text-xs font-semibold text-amber-600 bg-amber-50 p-1.5 rounded-md border border-amber-100">
                  <AlertCircle className="w-3.5 h-3.5" />
                  Offline: Stock not verified
                </div>
              ) : null}
            </div>
          ))
        )}
      </div>

      {/* Totals & Checkout */}
      <div className="p-4 bg-slate-50 border-t border-slate-200 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div className="space-y-2 mb-4">
          <div className="flex justify-between text-sm text-slate-500">
            <span>Subtotal</span>
            <span>${getCartTotal()}</span>
          </div>
          <div className="flex justify-between text-sm text-slate-500">
            <span>Tax</span>
            <span>$0.00</span>
          </div>
          <div className="flex justify-between text-lg font-bold text-slate-900 pt-2 border-t border-slate-200 border-dashed">
            <span>Total</span>
            <span>${getCartTotal()}</span>
          </div>
        </div>

        <button
          onClick={onCheckout}
          disabled={items.length === 0 || isCheckingOut}
          className="w-full relative flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white py-3.5 rounded-xl font-bold text-lg transition-all disabled:opacity-50 disabled:hover:bg-indigo-600 overflow-hidden group"
        >
          {isCheckingOut ? (
            <><Loader2 className="w-5 h-5 animate-spin" />Processing...</>
          ) : (
            <><Banknote className="w-5 h-5" />Charge ${getCartTotal()}</>
          )}
          {/* Glossy overlay */}
          <div className="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out" />
        </button>
      </div>
    </div>
  );
}
