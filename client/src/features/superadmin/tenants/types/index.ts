/**
 * Tenant Management — Domain Types
 *
 * Single source of truth for all tenant-related shapes.
 * Used across hooks, components, and the page.
 *
 * @feature superadmin/tenants
 */

export interface Tenant {
  id: number;
  business_name: string;
  owner_name: string;
  owner_email: string;
  owner_id: number | null;
  subdomain: string | null;
  url: string | null;
  is_active: boolean;
  status: 'active' | 'suspended' | string;
  plan_id: number | null;
  plan_max_users: number | null;
  plan_max_locations: number | null;
  users_count: number;
  locations_count: number;
  subscription_id: number | null;
  subscription_expires_at: string | null;
  subscription_status: string | null;
  subscription_status_real: string | null;
  active_modules: string[];
  license_key: string | null;
  created_at: string;
}

export interface Plan {
  id: number;
  name: string;
  price: string;
  interval: string;
}

export interface BillingFormState {
  duration: '1_month' | '1_year';
  status: 'active' | 'past_due' | 'suspended' | 'canceled';
}

export interface CreateTenantFormState {
  name: string;
  owner_email: string;
  password: string;
  plan_id: string;
  subdomain: string;
}

export interface TenantModule {
  id: string;
  label: string;
}

export const AVAILABLE_MODULES: TenantModule[] = [
  { id: 'core',             label: 'Core POS & Inventory' },
  { id: 'crm',              label: 'CRM & Loyalty' },
  { id: 'hr',               label: 'HR Management' },
  { id: 'serial_tracking',  label: 'Serial & IMEI Tracking' },
  { id: 'pharmacy',         label: 'Pharmacy Vertical' },
  { id: 'restaurant',       label: 'Restaurant Vertical' },
  { id: 'hardware_builder', label: 'PC/Hardware Builder' },
  { id: 'manufacturing',    label: 'Manufacturing' },
];

export type StatusFilter = 'all' | 'active' | 'suspended';
