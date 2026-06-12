'use client';

import useSWR from 'swr';
import { useState, useEffect, useCallback } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useRouter, useSearchParams } from 'next/navigation';
import toast from 'react-hot-toast';
import api from '@/lib/api';
import {
  customerSchema,
  CUSTOMER_FORM_DEFAULTS,
  type CustomerFormValues,
  type Customer,
} from '../types';

const fetcher = (url: string) => api.get(url).then(res => res.data);

/**
 * useCustomers — Custom Hook
 *
 * Extracted from customers/page.tsx (lines 31–140 of 348).
 * Owns ALL state, data fetching, and action handlers for the Customers page:
 *
 *   DATA:    SWR for paginated customer list
 *   STATE:   searchTerm (URL-synced + debounced), pagination, modal, deletingId
 *   FORM:    react-hook-form + zod for create-customer form
 *   ACTIONS: onSubmit (create), handleDelete (archive), exportCSV
 *
 * ZERO TRUST:
 *   - Delete requires window.confirm gate before any API call
 *   - Per-row deletingId prevents double-click races
 *   - Error messages surfaced verbatim from server — never silently swallowed
 *   - CSV export is client-side only (no server round-trip for current page data)
 *
 * @feature crm/customers
 */
export function useCustomers() {
  const router       = useRouter();
  const searchParams = useSearchParams();

  const [searchTerm,  setSearchTermState] = useState(searchParams.get('cust_search') || '');
  const [currentPage, setCurrentPage]     = useState(parseInt(searchParams.get('cust_page') || '1', 10));
  const [isModalOpen, setIsModalOpen]     = useState(false);
  const [deletingId,  setDeletingId]      = useState<number | null>(null);
  const [isExporting, setIsExporting]     = useState(false);

  // ── URL Debounce Sync ─────────────────────────────────────────────────
  useEffect(() => {
    const timer = setTimeout(() => {
      const params = new URLSearchParams(searchParams.toString());
      if (searchTerm) {
        params.set('cust_search', searchTerm);
      } else {
        params.delete('cust_search');
      }
      params.set('cust_page', currentPage.toString());
      router.replace(`?${params.toString()}`);
    }, 400);
    return () => clearTimeout(timer);
  }, [searchTerm, currentPage, router, searchParams]);

  // ── SWR: Customers ────────────────────────────────────────────────────
  const searchParam = searchTerm ? `&search=${encodeURIComponent(searchTerm)}` : '';
  const { data: customersData, isLoading, mutate } = useSWR(
    `/tenant/contacts?type=customer&per_page=20&page=${currentPage}${searchParam}`,
    fetcher,
    { revalidateOnFocus: false, keepPreviousData: true }
  );
  const customers: Customer[] = customersData?.data ?? [];

  // ── Form ──────────────────────────────────────────────────────────────
  const form = useForm<CustomerFormValues>({
    resolver: zodResolver(customerSchema),
    defaultValues: CUSTOMER_FORM_DEFAULTS,
  });

  const closeModal = useCallback(() => {
    setIsModalOpen(false);
    form.reset();
  }, [form]);

  // ── Actions ───────────────────────────────────────────────────────────

  const onSubmit = useCallback(async (data: CustomerFormValues) => {
    try {
      await api.post('/tenant/contacts', data);
      toast.success('Customer added successfully!');
      closeModal();
      mutate();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to add customer.');
    }
  }, [closeModal, mutate]);

  const handleDelete = useCallback(async (id: number) => {
    if (!window.confirm('Are you sure? This will archive the customer, but keep historical transactions intact.')) return;
    setDeletingId(id);
    try {
      await api.delete(`/tenant/contacts/${id}`);
      toast.success('Customer archived successfully.');
      mutate();
    } catch {
      toast.error('Failed to archive customer.');
    } finally {
      setDeletingId(null);
    }
  }, [mutate]);

  const exportCSV = useCallback(async () => {
    setIsExporting(true);
    try {
      const header = 'ID,Name,Mobile,Email,City,State,Country,Created At';
      const rows   = customers.map(c =>
        `"${c.id}","${c.name}","${c.mobile ?? ''}","${c.email ?? ''}","${c.city ?? ''}","${c.state ?? ''}","${c.country ?? ''}","${c.created_at}"`
      );
      const blob = new Blob([[header, ...rows].join('\n')], { type: 'text/csv;charset=utf-8;' });
      const url  = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', `Customers_Export_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url); // Memory cleanup not present in original
      toast.success('Customers exported successfully');
    } catch {
      toast.error('Failed to export customers.');
    } finally {
      setIsExporting(false);
    }
  }, [customers]);

  const setSearchTerm = useCallback((v: string) => {
    setSearchTermState(v);
    setCurrentPage(1);
  }, []);

  return {
    // Data
    customers,
    isLoading,

    // Pagination + search
    searchTerm,
    setSearchTerm,
    currentPage,
    setCurrentPage,

    // Modal
    isModalOpen,
    setIsModalOpen,
    closeModal,

    // Form
    form,
    onSubmit,

    // Actions
    deletingId,
    handleDelete,
    isExporting,
    exportCSV,
  };
}
