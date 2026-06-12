"use client";

import React, { useState, useMemo, useEffect } from "react";
import useSWR from "swr";
import { Search, ShoppingCart, AlertCircle, Plus, Minus, CreditCard, Banknote, Trash2, Box, Wifi, WifiOff, Loader2 } from "lucide-react";
import toast from "react-hot-toast";
import clsx from "clsx";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { v4 as uuidv4 } from "uuid";
import api from "@/lib/api";
import { useCartStore } from "@/store/useCartStore";
import { useSyncStore } from "@/store/useSyncStore";

const fetcher = (url: string) => api.get(url).then((res) => res.data);

const openRegisterSchema = z.object({
  opening_balance: z.coerce.number().min(0, "Opening float must be at least 0"),
});

const closeRegisterSchema = z.object({
  closing_balance_counted: z.coerce.number().min(0, "Counted amount must be at least 0"),
});

// Define types according to requirements
interface Product {
  id: number;
  name: string;
  price: string;
  stock: number;
  image?: string;
  category?: string;
}

export default function POSPage() {
  const [searchTerm, setSearchTerm] = useState("");
  const [isOnline, setIsOnline] = useState(true);
  const [isCheckingOut, setIsCheckingOut] = useState(false);
  const [isCloseModalOpen, setIsCloseModalOpen] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);

  // Zustand Stores
  const { unsynced_transactions, addTransaction, removeTransaction } = useSyncStore();

  // Register state fetching
  const { data: registerData, mutate: mutateRegister, isLoading: isLoadingRegister } = useSWR(
    '/tenant/registers/status',
    fetcher,
    {
      revalidateOnFocus: false, // Prevent infinite loops during POS usage
      revalidateIfStale: false,
    }
  );

  const isRegisterOpen = registerData?.is_open === true;

  // Forms
  const openForm = useForm<{ opening_balance: number }>({
    resolver: zodResolver(openRegisterSchema) as any,
    defaultValues: { opening_balance: 0 }
  });

  const closeForm = useForm<{ closing_balance_counted: number }>({
    resolver: zodResolver(closeRegisterSchema) as any,
    defaultValues: { closing_balance_counted: 0 }
  });
  
  const { 
    items, 
    addItem, 
    updateQuantity, 
    removeItem, 
    getCartTotal, 
    setCartItemError, 
    clearCart 
  } = useCartStore();

  // Network listener & Viewport lockdown
  useEffect(() => {
    // Inject mobile viewport lockdown for iOS devices to prevent zooming
    let viewportMeta = document.querySelector('meta[name="viewport"]');
    if (!viewportMeta) {
      viewportMeta = document.createElement('meta');
      viewportMeta.setAttribute('name', 'viewport');
      document.head.appendChild(viewportMeta);
    }
    viewportMeta.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');

    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);
    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);
    return () => {
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
    };
  }, []);

  // Background Sync Engine
  useEffect(() => {
    if (!isOnline || unsynced_transactions.length === 0 || isSyncing) return;

    const processSyncQueue = async () => {
      setIsSyncing(true);
      let successCount = 0;

      for (const tx of unsynced_transactions) {
        try {
          await api.post('/tenant/sales/checkout', {
            ...tx.payload,
            is_offline_sync: true,
          }, {
            headers: {
              'X-Idempotency-Key': tx.uuid
            }
          });
          
          removeTransaction(tx.uuid);
          successCount++;
        } catch (err: any) {
          // If the backend actively rejected it (e.g. 422 Validation error), we might want to log it and remove it, or leave it for manual intervention. 
          // For now, if it's a 4xx error (not network), we'll remove it to unblock the queue.
          if (err.response && err.response.status >= 400 && err.response.status < 500) {

            removeTransaction(tx.uuid);
            toast.error(`Offline sale failed: ${err.response.data?.message || 'Invalid payload'}`);
          }
        }
      }

      if (successCount > 0) {
        toast.success(`Successfully synchronized ${successCount} offline sales!`);
      }
      setIsSyncing(false);
    };

    processSyncQueue();
  }, [isOnline, unsynced_transactions, removeTransaction, isSyncing]);

  // Fetch products via SWR
  // Conditional window-focus refetching: disable when cart has items
  const { data: productsData, error, isLoading } = useSWR<{ data: Product[] }>(
    '/tenant/catalog/pos-sync',
    fetcher,
    {
      revalidateOnFocus: items.length === 0,
      revalidateIfStale: true,
      dedupingInterval: 5000, // 5 seconds
    }
  );

  const products = productsData?.data || [];

  const filteredProducts = useMemo(() => {
    if (!searchTerm) return products;
    return products.filter((p) =>
      p.name.toLowerCase().includes(searchTerm.toLowerCase())
    );
  }, [products, searchTerm]);

  // Handle Checkout
  const handleCheckout = async () => {
    if (items.length === 0) return;
    setIsCheckingOut(true);

    const idempotencyKey = uuidv4();
    const payload = {
      items: items.map((item) => ({
        product_id: item.product_id,
        quantity: item.quantity,
        price: item.price,
      })),
      total: getCartTotal(),
      payment_method: "cash",
    };

    try {
      if (!isOnline) {
        throw new Error("ERR_NETWORK");
      }

      await api.post('/tenant/sales/checkout', payload, {
        headers: {
          'X-Idempotency-Key': idempotencyKey
        }
      });
      
      toast.success("Checkout Successful!");
      clearCart();
    } catch (err: any) {
      // Offline fallback handling
      if (!isOnline || err.code === 'ERR_NETWORK' || err.message === 'ERR_NETWORK') {
        addTransaction({
          uuid: idempotencyKey,
          payload,
          timestamp: Date.now()
        });
        toast.success("Saved Offline! Will sync when connected.");
        clearCart();
        setIsCheckingOut(false);
        return;
      }

      // The "Ghost-Sale" Catch (Pessimistic UI Rebound)
      const responseData = err.response?.data;
      const errorMessage = responseData?.message || err.message || String(err);
      
      const match = errorMessage.match(/Insufficient stock for product ID: (\d+)/);
      
      if (match && match[1]) {
        const productId = parseInt(match[1], 10);
        setCartItemError(productId, "Out of stock during checkout!");
        toast.error("Some items are out of stock. Please review your cart.");
      } else {
        toast.error(errorMessage || "Checkout failed. Please try again.");
      }
    } finally {
      setIsCheckingOut(false);
    }
  };

  // Register Handlers
  const handleOpenRegister = async (data: { opening_balance: number }) => {
    try {
      await api.post('/tenant/registers/open', data);
      toast.success("Register opened successfully!");
      mutateRegister();
    } catch (err: unknown) {
      const errorObj = err as Record<string, any>;
      toast.error(errorObj.response?.data?.message || "Failed to open register");
    }
  };

  const handleCloseRegister = async (data: { closing_balance_counted: number }) => {
    try {
      await api.post('/tenant/registers/close', data);
      toast.success("Register closed successfully! Shift ended.");
      setIsCloseModalOpen(false);
      closeForm.reset();
      mutateRegister();
    } catch (err: unknown) {
      const errorObj = err as Record<string, any>;
      toast.error(errorObj.response?.data?.message || "Failed to close register");
    }
  };

  return (
    <div className="flex flex-col md:flex-row h-screen w-full bg-slate-50 overflow-hidden text-slate-900">
      
      {/* LEFT PANEL: PRODUCT GRID */}
      <div className="flex-1 flex flex-col h-[50vh] md:h-full border-b md:border-b-0 md:border-r border-slate-200 bg-white z-10">
        
        {/* Header Bar */}
        <div className="flex items-center justify-between p-4 border-b border-slate-100 shadow-sm">
          <div className="flex items-center gap-2">
            <Box className="w-6 h-6 text-indigo-600" />
            <h1 className="text-xl font-bold tracking-tight text-slate-800">Terminal</h1>
            <div className={clsx(
              "flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ml-4 transition-colors",
              isOnline ? "bg-emerald-100 text-emerald-700" : "bg-rose-100 text-rose-700"
            )}>
              {isOnline ? <Wifi className="w-3.5 h-3.5" /> : <WifiOff className="w-3.5 h-3.5" />}
              {isOnline ? "Online" : "Offline Mode"}
            </div>
          </div>
          
          <div className="flex items-center gap-3">
            {unsynced_transactions.length > 0 && (
              <div className="flex items-center gap-1.5 bg-amber-50 text-amber-700 px-3 py-1.5 rounded-lg text-sm font-semibold border border-amber-200">
                <AlertCircle className="w-4 h-4" />
                {unsynced_transactions.length} Pending
                {isSyncing && <Loader2 className="w-3 h-3 animate-spin ml-1" />}
              </div>
            )}
            <button 
              onClick={() => setIsCloseModalOpen(true)}
              className="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors"
              disabled={!isRegisterOpen}
            >
              Close Register
            </button>
            <div className="relative w-64">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input
                type="text"
                placeholder="Search products..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all text-sm"
              />
            </div>
          </div>
        </div>

        {/* Product Grid Area */}
        <div className="flex-1 p-6 overflow-y-auto">
          {isLoading ? (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
              {Array.from({ length: 15 }).map((_, i) => (
                <div key={i} className="bg-slate-100 rounded-xl p-4 h-32 animate-pulse border border-slate-200 flex flex-col justify-between">
                  <div className="w-2/3 h-4 bg-slate-200 rounded"></div>
                  <div className="flex justify-between items-end">
                    <div className="w-1/3 h-5 bg-slate-200 rounded"></div>
                    <div className="w-8 h-8 bg-slate-200 rounded-full"></div>
                  </div>
                </div>
              ))}
            </div>
          ) : error ? (
            <div className="flex flex-col items-center justify-center h-full text-slate-500">
              <AlertCircle className="w-12 h-12 text-rose-500 mb-3" />
              <p className="text-lg font-medium text-slate-800">Failed to load catalog</p>
              <p className="text-sm">Please check your connection and try again.</p>
            </div>
          ) : filteredProducts.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-slate-500">
              <div className="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mb-4">
                <Search className="w-8 h-8 text-slate-400" />
              </div>
              <p className="text-lg font-medium text-slate-800">No products found</p>
              <p className="text-sm">Try adjusting your search terms.</p>
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
              {filteredProducts.map((product) => (
                <button
                  key={product.id}
                  onClick={() => addItem(product)}
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
                  {/* Subtle decorative background */}
                  <div className="absolute -bottom-4 -right-4 w-16 h-16 bg-gradient-to-br from-indigo-50 to-transparent rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* RIGHT PANEL: CART (Bottom Sheet on Mobile, Sticky on Tablet/Desktop) */}
      <div className="w-full md:w-[400px] flex flex-col h-[50vh] md:h-full bg-white shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)] md:shadow-xl z-40 fixed md:relative bottom-0 left-0 md:bottom-auto md:left-auto">
        <div className="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 min-h-[60px]">
          <div className="flex items-center gap-2">
            <ShoppingCart className="w-5 h-5 text-slate-600" />
            <h2 className="font-bold text-lg text-slate-800">Current Order</h2>
          </div>
          <span className="bg-slate-200 text-slate-700 text-xs font-bold px-2 py-1 rounded-md">
            {items.length} {items.length === 1 ? 'Item' : 'Items'}
          </span>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-3">
          {items.length === 0 ? (
            <div className="h-full flex flex-col items-center justify-center text-slate-400">
              <ShoppingCart className="w-12 h-12 mb-3 text-slate-200" />
              <p className="font-medium text-slate-500">Your cart is empty</p>
              <p className="text-sm text-slate-400 text-center px-6 mt-1">Tap products on the left to add them to your order.</p>
            </div>
          ) : (
            items.map((item) => (
              <div 
                key={item.id} 
                className={clsx(
                  "flex flex-col p-3 rounded-lg border transition-all relative overflow-hidden group",
                  item.stockError 
                    ? "border-rose-500 bg-rose-50 shadow-[0_0_0_1px_rgba(244,63,94,1)] animate-[pulse_2s_ease-in-out_infinite]" 
                    : "border-slate-200 bg-white hover:border-slate-300"
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
                      onClick={() => updateQuantity(item.id, item.quantity - 1)}
                      className="w-11 h-11 flex items-center justify-center rounded-md text-slate-600 hover:bg-white hover:shadow-sm hover:text-slate-900 transition-all active:scale-95"
                      disabled={item.quantity <= 1}
                    >
                      <Minus className="w-5 h-5" />
                    </button>
                    <span className="w-8 text-center text-base font-semibold text-slate-800">
                      {item.quantity}
                    </span>
                    <button 
                      onClick={() => updateQuantity(item.id, item.quantity + 1)}
                      className="w-11 h-11 flex items-center justify-center rounded-md text-slate-600 hover:bg-white hover:shadow-sm hover:text-slate-900 transition-all active:scale-95"
                    >
                      <Plus className="w-5 h-5" />
                    </button>
                  </div>
                  <button 
                    onClick={() => removeItem(item.id)}
                    className="w-11 h-11 flex items-center justify-center rounded-full text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-colors absolute -right-2 -top-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 md:group-hover:relative md:group-hover:top-0 md:group-hover:right-0 active:scale-95"
                  >
                    <Trash2 className="w-5 h-5" />
                  </button>
                </div>
                
                {/* Soft Reserve / Error Tooltip */}
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
            onClick={handleCheckout}
            disabled={items.length === 0 || isCheckingOut}
            className="w-full relative flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white py-3.5 rounded-xl font-bold text-lg transition-all disabled:opacity-50 disabled:hover:bg-indigo-600 overflow-hidden group"
          >
            {isCheckingOut ? (
              <>
                <Loader2 className="w-5 h-5 animate-spin" />
                Processing...
              </>
            ) : (
              <>
                <Banknote className="w-5 h-5" />
                Charge ${getCartTotal()}
              </>
            )}
            
            {/* Glossy overlay effect */}
            <div className="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-in-out"></div>
          </button>
        </div>
      </div>

      {/* POS GATEKEEPER MODAL (OPEN REGISTER) */}
      {!isLoadingRegister && !isRegisterOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-md p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col p-6 text-center">
            <div className="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4">
              <Banknote className="w-8 h-8" />
            </div>
            <h2 className="text-xl font-bold text-slate-800 mb-2">Open Register</h2>
            <p className="text-sm text-slate-500 mb-6">You must open your cash register for this location to process sales.</p>
            
            <form onSubmit={openForm.handleSubmit(handleOpenRegister)} className="flex flex-col gap-4 text-left">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-slate-700">Opening Cash Float ($)</label>
                <input 
                  type="number"
                  step="0.01"
                  disabled={openForm.formState.isSubmitting}
                  {...openForm.register("opening_balance")}
                  className={clsx(
                    "w-full px-4 py-3 bg-slate-50 border rounded-xl text-lg font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50",
                    openForm.formState.errors.opening_balance ? "border-rose-500" : "border-slate-300"
                  )}
                  placeholder="0.00"
                />
                {openForm.formState.errors.opening_balance && (
                  <span className="text-xs font-semibold text-rose-500">{openForm.formState.errors.opening_balance.message}</span>
                )}
              </div>
              <button
                type="submit"
                disabled={openForm.formState.isSubmitting}
                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-xl font-bold text-base transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2 mt-2"
              >
                {openForm.formState.isSubmitting ? (
                  <><Loader2 className="w-4 h-4 animate-spin" /> Processing...</>
                ) : (
                  <>Start Shift</>
                )}
              </button>
            </form>
          </div>
        </div>
      )}

      {/* BLIND-COUNT EOD MODAL (CLOSE REGISTER) */}
      {isCloseModalOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-md p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col p-6 text-center">
            <h2 className="text-xl font-bold text-slate-800 mb-2">End of Day Verification</h2>
            <p className="text-sm text-slate-500 mb-6">Physically count the cash in your drawer. (Blind Count)</p>
            
            <form onSubmit={closeForm.handleSubmit(handleCloseRegister)} className="flex flex-col gap-4 text-left">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-semibold text-slate-700">Counted Cash Amount ($)</label>
                <input 
                  type="number"
                  step="0.01"
                  disabled={closeForm.formState.isSubmitting}
                  {...closeForm.register("closing_balance_counted")}
                  className={clsx(
                    "w-full px-4 py-3 bg-slate-50 border rounded-xl text-lg font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50",
                    closeForm.formState.errors.closing_balance_counted ? "border-rose-500" : "border-slate-300"
                  )}
                  placeholder="0.00"
                />
                {closeForm.formState.errors.closing_balance_counted && (
                  <span className="text-xs font-semibold text-rose-500">{closeForm.formState.errors.closing_balance_counted.message}</span>
                )}
              </div>
              
              <div className="flex gap-3 mt-4">
                <button
                  type="button"
                  onClick={() => { setIsCloseModalOpen(false); closeForm.reset(); }}
                  className="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-3 rounded-xl font-bold transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={closeForm.formState.isSubmitting}
                  className="flex-1 bg-rose-600 hover:bg-rose-700 text-white px-4 py-3 rounded-xl font-bold transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2"
                >
                  {closeForm.formState.isSubmitting ? (
                    <><Loader2 className="w-4 h-4 animate-spin" /> Processing...</>
                  ) : (
                    <>Close Register</>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
