'use client';

import useSWR from 'swr';
import { useState, useEffect, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { v4 as uuidv4 } from 'uuid';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import { useCartStore } from '@/store/useCartStore';
import { useSyncStore } from '@/store/useSyncStore';

// ── Validation Schemas ────────────────────────────────────────────────────

export const openRegisterSchema = z.object({
  opening_balance: z.coerce.number().min(0, 'Opening float must be at least 0'),
});

export const closeRegisterSchema = z.object({
  closing_balance_counted: z.coerce.number().min(0, 'Counted amount must be at least 0'),
});

// ── Types ─────────────────────────────────────────────────────────────────

export interface Product {
  id: number;
  name: string;
  price: string;
  stock: number;
  image?: string;
  category?: string;
}

const fetcher = (url: string) => api.get(url).then(res => res.data);

/**
 * usePOSTerminal — Custom Hook
 *
 * Extracted from terminal/page.tsx (lines 1–247 of 572).
 * Owns ALL state, side-effects, and action handlers for the POS terminal:
 *
 *   NETWORK:  Online/offline detection + viewport lockdown
 *   SYNC:     Background sync engine for queued offline transactions
 *   DATA:     SWR for product catalog, SWR for register status
 *   CART:     Delegates to useCartStore (Zustand)
 *   ACTIONS:  checkout, openRegister, closeRegister
 *
 * ZERO TRUST:
 *   - Idempotency key (UUID) on every checkout POST
 *   - X-Idempotency-Key header prevents ghost-sales on server
 *   - Ghost-sale catch: sets per-item stock error for visual rebound
 *   - Offline fallback: persists to sync queue, never drops a sale
 *
 * @feature pos/terminal
 */
export function usePOSTerminal() {
  const [searchTerm,      setSearchTerm]      = useState('');
  const [isOnline,        setIsOnline]        = useState(true);
  const [isCheckingOut,   setIsCheckingOut]   = useState(false);
  const [isCloseModalOpen, setIsCloseModalOpen] = useState(false);
  const [isSyncing,       setIsSyncing]       = useState(false);

  // ── Zustand Stores ────────────────────────────────────────────────────
  const { unsynced_transactions, addTransaction, removeTransaction } = useSyncStore();
  const { items, addItem, updateQuantity, removeItem, getCartTotal, setCartItemError, clearCart } = useCartStore();

  // ── React Hook Form ───────────────────────────────────────────────────
  const openForm = useForm<{ opening_balance: number }>({
    resolver: zodResolver(openRegisterSchema) as any,
    defaultValues: { opening_balance: 0 },
  });

  const closeForm = useForm<{ closing_balance_counted: number }>({
    resolver: zodResolver(closeRegisterSchema) as any,
    defaultValues: { closing_balance_counted: 0 },
  });

  // ── Register SWR ──────────────────────────────────────────────────────
  const { data: registerData, mutate: mutateRegister, isLoading: isLoadingRegister } = useSWR(
    '/tenant/registers/status',
    fetcher,
    { revalidateOnFocus: false, revalidateIfStale: false }
  );
  const isRegisterOpen = registerData?.is_open === true;

  // ── Product Catalog SWR ───────────────────────────────────────────────
  const { data: productsData, error: catalogError, isLoading: isCatalogLoading } = useSWR<{ data: Product[] }>(
    '/tenant/catalog/pos-sync',
    fetcher,
    {
      revalidateOnFocus: items.length === 0,
      revalidateIfStale: true,
      dedupingInterval: 5000,
    }
  );
  const products = productsData?.data ?? [];

  // ── Network Listener + Mobile Viewport Lockdown ───────────────────────
  useEffect(() => {
    // Prevent accidental zoom on iOS POS devices
    let viewportMeta = document.querySelector('meta[name="viewport"]');
    if (!viewportMeta) {
      viewportMeta = document.createElement('meta');
      viewportMeta.setAttribute('name', 'viewport');
      document.head.appendChild(viewportMeta);
    }
    viewportMeta.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');

    const handleOnline  = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);
    window.addEventListener('online',  handleOnline);
    window.addEventListener('offline', handleOffline);
    return () => {
      window.removeEventListener('online',  handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // ── Background Sync Engine ────────────────────────────────────────────
  useEffect(() => {
    if (!isOnline || unsynced_transactions.length === 0 || isSyncing) return;

    const processSyncQueue = async () => {
      setIsSyncing(true);
      let successCount = 0;

      for (const tx of unsynced_transactions) {
        try {
          await api.post('/tenant/sales/checkout',
            { ...tx.payload, is_offline_sync: true },
            { headers: { 'X-Idempotency-Key': tx.uuid } }
          );
          removeTransaction(tx.uuid);
          successCount++;
        } catch (err: any) {
          // 4xx = server-rejected payload → remove to unblock queue
          if (err.response?.status >= 400 && err.response?.status < 500) {
            removeTransaction(tx.uuid);
            toast.error(`Offline sale failed: ${err.response.data?.message || 'Invalid payload'}`);
          }
          // 5xx / network = transient → leave in queue for next attempt
        }
      }

      if (successCount > 0) {
        toast.success(`Successfully synchronized ${successCount} offline sales!`);
      }
      setIsSyncing(false);
    };

    processSyncQueue();
  }, [isOnline, unsynced_transactions, removeTransaction, isSyncing]);

  // ── Actions ───────────────────────────────────────────────────────────

  const handleCheckout = useCallback(async () => {
    if (items.length === 0) return;
    setIsCheckingOut(true);

    const idempotencyKey = uuidv4();
    const payload = {
      items: items.map(item => ({
        product_id: item.product_id,
        quantity:   item.quantity,
        price:      item.price,
      })),
      total:          getCartTotal(),
      payment_method: 'cash',
    };

    try {
      if (!isOnline) throw new Error('ERR_NETWORK');

      await api.post('/tenant/sales/checkout', payload, {
        headers: { 'X-Idempotency-Key': idempotencyKey },
      });
      toast.success('Checkout Successful!');
      clearCart();
    } catch (err: any) {
      // Offline fallback — persist to sync queue
      if (!isOnline || err.code === 'ERR_NETWORK' || err.message === 'ERR_NETWORK') {
        addTransaction({ uuid: idempotencyKey, payload, timestamp: Date.now() });
        toast.success('Saved Offline! Will sync when connected.');
        clearCart();
        setIsCheckingOut(false);
        return;
      }

      // Ghost-sale catch — per-item stock error display
      const errorMessage = err.response?.data?.message || err.message || String(err);
      const match = errorMessage.match(/Insufficient stock for product ID: (\d+)/);
      if (match?.[1]) {
        setCartItemError(parseInt(match[1], 10), 'Out of stock during checkout!');
        toast.error('Some items are out of stock. Please review your cart.');
      } else {
        toast.error(errorMessage || 'Checkout failed. Please try again.');
      }
    } finally {
      setIsCheckingOut(false);
    }
  }, [items, isOnline, getCartTotal, clearCart, addTransaction, setCartItemError]);

  const handleOpenRegister = useCallback(async (data: { opening_balance: number }) => {
    try {
      await api.post('/tenant/registers/open', data);
      toast.success('Register opened successfully!');
      mutateRegister();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to open register');
    }
  }, [mutateRegister]);

  const handleCloseRegister = useCallback(async (data: { closing_balance_counted: number }) => {
    try {
      await api.post('/tenant/registers/close', data);
      toast.success('Register closed successfully! Shift ended.');
      setIsCloseModalOpen(false);
      closeForm.reset();
      mutateRegister();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to close register');
    }
  }, [closeForm, mutateRegister]);

  return {
    // Network
    isOnline,
    isSyncing,
    unsyncedCount: unsynced_transactions.length,

    // Register
    isRegisterOpen,
    isLoadingRegister,
    openForm,
    closeForm,
    isCloseModalOpen,
    setIsCloseModalOpen,
    handleOpenRegister,
    handleCloseRegister,

    // Catalog
    products,
    catalogError,
    isCatalogLoading,

    // Cart
    items,
    addItem,
    updateQuantity,
    removeItem,
    getCartTotal,
    clearCart,

    // Checkout
    isCheckingOut,
    handleCheckout,

    // Search
    searchTerm,
    setSearchTerm,
  };
}
