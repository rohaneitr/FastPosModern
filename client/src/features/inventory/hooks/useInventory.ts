'use client';

import useSWR from 'swr';
import { useState, useEffect, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useRouter, useSearchParams } from 'next/navigation';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import { transferSchema, type TransferFormValues, type ProductStock, type Location } from '../types';

const fetcher = (url: string) => api.get(url).then(res => res.data);

/**
 * useInventory — Custom Hook
 *
 * Extracted from inventory/page.tsx (lines 48–115 of 355).
 * Owns ALL state, data fetching, and actions for the Inventory Master page:
 *
 *   DATA:    SWR for paginated inventory + locations list
 *   STATE:   searchTerm (URL-synced + debounced), pagination, modal open/close
 *   FORM:    react-hook-form + zod for stock transfer validation
 *   ACTION:  onTransferSubmit — POST /tenant/inventory/transfer
 *
 * ZERO TRUST:
 *   - Server error message surfaced verbatim (never silently swallowed)
 *   - SWR mutate() called after successful transfer to guarantee grid consistency
 *   - Form reset after modal close — no stale state leaks
 *
 * @feature inventory
 */
export function useInventory() {
  const router       = useRouter();
  const searchParams = useSearchParams();

  const [searchTerm,          setSearchTermState] = useState(searchParams.get('inv_search') || '');
  const [currentPage,         setCurrentPage]     = useState(parseInt(searchParams.get('inv_page') || '1', 10));
  const [isTransferModalOpen, setIsTransferModalOpen] = useState(false);

  // ── URL Debounce Sync ─────────────────────────────────────────────────
  useEffect(() => {
    const timer = setTimeout(() => {
      const params = new URLSearchParams();
      if (searchTerm) params.set('inv_search', searchTerm);
      params.set('inv_page', currentPage.toString());
      router.replace(`?${params.toString()}`);
    }, 400);
    return () => clearTimeout(timer);
  }, [searchTerm, currentPage, router]);

  // ── SWR: Inventory ────────────────────────────────────────────────────
  const searchParam = searchTerm ? `&search=${encodeURIComponent(searchTerm)}` : '';
  const {
    data: inventoryData,
    isLoading,
    mutate,
  } = useSWR(
    `/tenant/inventory?per_page=50&page=${currentPage}${searchParam}`,
    fetcher,
    { revalidateOnFocus: false, keepPreviousData: true }
  );

  // ── SWR: Locations ────────────────────────────────────────────────────
  const { data: locationsData } = useSWR('/tenant/locations', fetcher);

  const products:  ProductStock[] = inventoryData?.data ?? [];
  const locations: Location[]     = locationsData?.data ?? [];

  // ── Transfer Form ─────────────────────────────────────────────────────
  const form = useForm<TransferFormValues>({
    resolver: zodResolver(transferSchema),
    defaultValues: {
      product_id:       0,
      from_location_id: 0,
      to_location_id:   0,
      quantity:         0,
      note:             '',
    },
  });

  const closeTransferModal = useCallback(() => {
    setIsTransferModalOpen(false);
    form.reset();
  }, [form]);

  const onTransferSubmit = useCallback(async (data: TransferFormValues) => {
    try {
      await api.post('/tenant/inventory/transfer', data);
      toast.success('Stock transferred successfully');
      closeTransferModal();
      mutate(); // Force-revalidate grid
    } catch (err: any) {
      const errorMessage =
        err.response?.data?.error ||
        err.response?.data?.message ||
        'Transfer failed due to a server error.';
      toast.error(errorMessage);
    }
  }, [closeTransferModal, mutate]);

  // ── Filter helpers ────────────────────────────────────────────────────
  const setSearchTerm = useCallback((v: string) => {
    setSearchTermState(v);
    setCurrentPage(1);
  }, []);

  return {
    // Data
    products,
    locations,
    isLoading,

    // Pagination + search
    searchTerm,
    setSearchTerm,
    currentPage,
    setCurrentPage,

    // Transfer modal
    isTransferModalOpen,
    setIsTransferModalOpen,
    closeTransferModal,

    // Form
    form,
    onTransferSubmit,
  };
}
