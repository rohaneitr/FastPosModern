'use client';

import useSWR from 'swr';
import { useRouter, useSearchParams } from 'next/navigation';
import { useState, useEffect, useCallback } from 'react';
import api from '@/lib/api';
import toast from 'react-hot-toast';
import type {
  Tenant,
  Plan,
  BillingFormState,
  CreateTenantFormState,
  StatusFilter,
} from '../types';

const fetcher = (url: string) => api.get(url).then(res => res.data);

/**
 * useTenants — Custom Hook
 *
 * Extracted from tenants/page.tsx (lines 1–268 of 731).
 * Owns ALL state and side-effects for the Tenant Management page:
 *
 *   DATA:   SWR for tenant list + plan list
 *   STATE:  searchTerm, statusFilter, pagination, modal open/close, form values
 *   ACTIONS: toggle, impersonate, export, generate-license, delete,
 *            save-modules, renew-subscription, override-status, create-tenant
 *
 * ZERO TRUST principles applied:
 *   - No business_id from client payload; always from authenticated session
 *   - Optimistic UI for toggle with automatic rollback on server error
 *   - Confirmation gate before hard delete
 *
 * @feature superadmin/tenants
 */
export function useTenants() {
  const router = useRouter();
  const searchParams = useSearchParams();

  // ── URL-synced state ──────────────────────────────────────────────────────
  const [searchTerm,    setSearchTermState] = useState(searchParams.get('search') || '');
  const [statusFilter,  setStatusFilterState] = useState<StatusFilter>((searchParams.get('status') as StatusFilter) || 'all');
  const [currentPage,   setCurrentPage] = useState(parseInt(searchParams.get('page') || '1', 10));

  // Debounced URL sync
  useEffect(() => {
    const timer = setTimeout(() => {
      const params = new URLSearchParams();
      if (searchTerm) params.set('search', searchTerm);
      if (statusFilter !== 'all') params.set('status', statusFilter);
      params.set('page', currentPage.toString());
      router.replace(`?${params.toString()}`);
    }, 400);
    return () => clearTimeout(timer);
  }, [searchTerm, statusFilter, currentPage, router]);

  // ── SWR Data Fetching ─────────────────────────────────────────────────────
  const searchParam = searchTerm ? `&search=${encodeURIComponent(searchTerm)}` : '';
  const filterParam = statusFilter !== 'all' ? `&status=${statusFilter}` : '';
  const swrKey = `/superadmin/businesses?page=${currentPage}${searchParam}${filterParam}`;

  const { data: swrData, error: swrError, mutate: mutateBusinesses } = useSWR(swrKey, fetcher, {
    keepPreviousData: true,
  });

  const tenants: Tenant[]  = swrData?.data || swrData || [];
  const isLoading           = !swrData && !swrError;
  const totalPages          = swrData?.last_page || 1;
  const totalItems          = swrData?.total || 0;

  // ── Plans ─────────────────────────────────────────────────────────────────
  const [plans, setPlans] = useState<Plan[]>([]);
  useEffect(() => {
    api.get('/superadmin/plans')
      .then(res => setPlans(res.data))
      .catch(() => {}); // silent fail — plans are optional
  }, []);

  // ── Submission guard ──────────────────────────────────────────────────────
  const [submitting, setSubmitting] = useState(false);

  // ── Modal State ───────────────────────────────────────────────────────────
  const [selectedTenantId, setSelectedTenantId] = useState<number | null>(null);

  const [showModulesModal, setShowModulesModal] = useState(false);
  const [activeModules,    setActiveModules]    = useState<string[]>([]);

  const [showBillingModal, setShowBillingModal] = useState(false);
  const [billingForm,      setBillingForm]      = useState<BillingFormState>({ duration: '1_month', status: 'active' });

  const [showCreateModal,  setShowCreateModal]  = useState(false);
  const [createForm,       setCreateForm]       = useState<CreateTenantFormState>({
    name: '', owner_email: '', password: '', plan_id: '', subdomain: '',
  });

  const [showBulkMessageModal, setShowBulkMessageModal] = useState(false);

  // ── Filter helpers ────────────────────────────────────────────────────────
  const setSearchTerm = useCallback((v: string) => {
    setSearchTermState(v);
    setCurrentPage(1);
  }, []);

  const setStatusFilter = useCallback((v: StatusFilter) => {
    setStatusFilterState(v);
    setCurrentPage(1);
  }, []);

  const clearFilters = useCallback(() => {
    setSearchTermState('');
    setStatusFilterState('all');
    setCurrentPage(1);
  }, []);

  const filteredTenants = tenants.filter(b => {
    if (statusFilter === 'active')    return Boolean(b.is_active);
    if (statusFilter === 'suspended') return !Boolean(b.is_active);
    return true;
  });

  // ── Actions ───────────────────────────────────────────────────────────────

  const handleToggle = useCallback(async (id: number) => {
    const tenant    = tenants.find(b => b.id === id);
    const newStatus = tenant ? !tenant.is_active : false;

    // Optimistic update
    mutateBusinesses((prev: any) => {
      const arr    = prev?.data || prev || [];
      const newArr = arr.map((b: any) => b.id === id ? { ...b, is_active: newStatus } : b);
      return prev?.data ? { ...prev, data: newArr } : newArr;
    }, false);

    try {
      await api.post(`/superadmin/businesses/${id}/toggle`);
      mutateBusinesses();
      toast.success('Tenant status updated');
    } catch (err: any) {
      mutateBusinesses(); // Rollback
      toast.error(err.response?.data?.message || 'An error occurred');
    }
  }, [tenants, mutateBusinesses]);

  const handleOpenModules = useCallback((b: Tenant) => {
    setSelectedTenantId(b.id);
    setActiveModules(b.active_modules || ['core']);
    setShowModulesModal(true);
  }, []);

  const handleSaveModules = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post(`/superadmin/businesses/${selectedTenantId}/modules`, { active_modules: activeModules });
      toast.success('Modules updated successfully');
      setShowModulesModal(false);
      mutateBusinesses();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  }, [selectedTenantId, activeModules, mutateBusinesses]);

  const handleOpenBilling = useCallback((b: Tenant) => {
    setSelectedTenantId(b.id);
    setBillingForm({ duration: '1_month', status: (b.subscription_status_real as BillingFormState['status']) || 'active' });
    setShowBillingModal(true);
  }, []);

  const handleRenewSubscription = useCallback(async () => {
    setSubmitting(true);
    try {
      const tenant = tenants.find(b => b.id === selectedTenantId);
      if (!tenant?.subscription_id) throw new Error('No subscription attached to this tenant.');
      await api.post(`/superadmin/subscriptions/${tenant.subscription_id}/renew`, { extension_period: billingForm.duration });
      toast.success('Subscription renewed successfully');
      setShowBillingModal(false);
      mutateBusinesses();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  }, [tenants, selectedTenantId, billingForm.duration, mutateBusinesses]);

  const handleOverrideStatus = useCallback(async () => {
    setSubmitting(true);
    try {
      const tenant = tenants.find(b => b.id === selectedTenantId);
      if (!tenant?.subscription_id) throw new Error('No subscription attached to this tenant.');
      await api.patch(`/superadmin/subscriptions/${tenant.subscription_id}/status`, { status: billingForm.status });
      toast.success('Subscription status overridden');
      setShowBillingModal(false);
      mutateBusinesses();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  }, [tenants, selectedTenantId, billingForm.status, mutateBusinesses]);

  const handleCreateTenant = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post('/superadmin/businesses', createForm);
      toast.success('Tenant created successfully');
      setShowCreateModal(false);
      setCreateForm({ name: '', owner_email: '', password: '', plan_id: '', subdomain: '' });
      mutateBusinesses();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  }, [createForm, mutateBusinesses]);

  const handleImpersonate = useCallback(async (b: Tenant) => {
    try {
      toast.success('Initiating God Mode...');
      const res = await api.post(`/superadmin/impersonate/${b.id}`);
      sessionStorage.setItem('fastpos_user', JSON.stringify(res.data.user));

      const host = window.location.host;
      let newHost: string;
      if (host.includes('localhost')) {
        newHost = `${b.subdomain}.localhost:3000`;
      } else {
        const parts = host.split('.');
        newHost = parts.length > 2
          ? [...parts.slice(0, -2), b.subdomain, ...parts.slice(-2)].join('.')
          : `${b.subdomain}.${host}`;
      }
      window.location.href = `${window.location.protocol}//${newHost}/business`;
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'An error occurred');
    }
  }, []);

  const handleExport = useCallback(async (id: number, name: string) => {
    try {
      toast.success('Preparing backup...');
      const res  = await api.get(`/superadmin/businesses/${id}/export`, { responseType: 'blob' });
      const url  = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `backup_${name.replace(/[^A-Za-z0-9]/g, '_')}.json`);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'An error occurred');
    }
  }, []);

  const handleGenerateLicense = useCallback(async (b: Tenant) => {
    if (!b.plan_id) {
      toast.error('This tenant does not have a subscription plan attached.');
      return;
    }
    setSubmitting(true);
    try {
      toast.success('Generating License Code...');
      const res = await api.post('/superadmin/licenses/generate', { tenant_id: b.id, plan_id: b.plan_id });
      window.prompt('License Generated! Copy and securely deliver to the tenant:', res.data.license_key);
      mutateBusinesses();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  }, [mutateBusinesses]);

  const handleDelete = useCallback(async (id: number) => {
    if (!window.confirm('Are you absolutely sure you want to PERMANENTLY delete this tenant and ALL associated data? This action cannot be undone.')) return;
    setSubmitting(true);
    try {
      await api.delete(`/superadmin/businesses/${id}`);
      toast.success('Tenant permanently deleted');
      mutateBusinesses();
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  }, [mutateBusinesses]);

  // ── Bulk message recipients ────────────────────────────────────────────────
  const bulkMessageUsers = tenants
    .filter(b => b.owner_id)
    .map(b => ({ id: b.owner_id!, name: `${b.business_name} (Admin)`, email: 'Tenant Admin' }));

  return {
    // Data
    tenants: filteredTenants,
    allTenants: tenants,
    isLoading,
    totalPages,
    totalItems,
    plans,

    // Pagination
    currentPage,
    setCurrentPage,

    // Search & filter
    searchTerm,
    setSearchTerm,
    statusFilter,
    setStatusFilter,
    clearFilters,

    // Submitting guard
    submitting,

    // Modules modal
    showModulesModal,
    setShowModulesModal,
    activeModules,
    setActiveModules,
    handleOpenModules,
    handleSaveModules,

    // Billing modal
    showBillingModal,
    setShowBillingModal,
    billingForm,
    setBillingForm,
    handleOpenBilling,
    handleRenewSubscription,
    handleOverrideStatus,

    // Create tenant modal
    showCreateModal,
    setShowCreateModal,
    createForm,
    setCreateForm,
    handleCreateTenant,

    // Bulk message modal
    showBulkMessageModal,
    setShowBulkMessageModal,
    bulkMessageUsers,

    // Row actions
    handleToggle,
    handleImpersonate,
    handleExport,
    handleGenerateLicense,
    handleDelete,
  };
}
