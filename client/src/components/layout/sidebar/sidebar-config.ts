import {
  LayoutDashboard, ShoppingCart, FileText, Package, Warehouse, Truck,
  FolderTree, Users, CreditCard, ShoppingBag, Calculator, Pill,
  Shield, UserCog, BarChart3, Settings, Star, ClipboardList,
  Monitor, Receipt, DollarSign, Building2, Cpu, Camera,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export interface SidebarMenuItem {
  name: string;
  /** i18n key — used with t() if available, otherwise `name` is rendered */
  i18nKey?: string;
  path: string;
  icon: LucideIcon;
  /** Module slugs required (checked against user's active_modules) */
  moduleAccess?: string[];
  /** If true, only visible to non-cashier roles */
  adminOnly?: boolean;
  /** Badge text (e.g. count) */
  badge?: string;
  /** Divider label above this item */
  section?: string;
}

/**
 * Business Admin / Manager / Cashier sidebar menu.
 * Cashiers see a filtered subset (items without adminOnly flag).
 */
export const businessMenuItems: SidebarMenuItem[] = [
  // ── Core ──
  { name: 'Dashboard', i18nKey: 'nav.dashboard', path: '/business', icon: LayoutDashboard, adminOnly: true, section: 'Core' },
  { name: 'POS Terminal', i18nKey: 'nav.openPOS', path: '/user/pos', icon: Monitor, moduleAccess: ['pos', 'point of sale'] },

  // ── Sales ──
  { name: 'Sales & Invoices', i18nKey: 'nav.salesInvoices', path: '/business/sales', icon: ShoppingCart, moduleAccess: ['pos', 'point of sale'], section: 'Sales' },
  { name: 'Quotations', path: '/business/quotations', icon: FileText, moduleAccess: ['quotations'] },
  { name: 'Due Collection', path: '/business/customers/due', icon: DollarSign, moduleAccess: ['crm'] },

  // ── Inventory ──
  { name: 'Products', i18nKey: 'nav.products', path: '/business/products', icon: Package, moduleAccess: ['inventory', 'products'], section: 'Inventory' },
  { name: 'Stock Overview', i18nKey: 'nav.inventoryStock', path: '/business/inventory', icon: Warehouse, moduleAccess: ['inventory', 'stock'] },
  { name: 'Stock Transfers', path: '/business/inventory/transfers', icon: Truck, moduleAccess: ['inventory'] },
  { name: 'Catalog Settings', i18nKey: 'nav.categoriesBrands', path: '/business/categories', icon: FolderTree, moduleAccess: ['inventory', 'catalog'] },
  { name: 'Purchases', i18nKey: 'nav.purchases', path: '/business/purchases', icon: ShoppingBag, moduleAccess: ['purchases', 'inventory'] },

  // ── CRM ──
  { name: 'Customers & CRM', i18nKey: 'nav.customersCRM', path: '/business/contacts', icon: Users, moduleAccess: ['crm', 'customers'], section: 'CRM' },

  // ── Industry ──
  { name: 'PC Builder', path: '/business/quotations/pc-builder', icon: Cpu, moduleAccess: ['hardware_builder'], section: 'Industry' },
  { name: 'CCTV Builder', path: '/business/quotations/cctv-builder', icon: Camera, moduleAccess: ['hardware_builder'] },
  { name: 'Pharmacy', path: '/business/pharmacy', icon: Pill, moduleAccess: ['pharmacy'] },
  { name: 'Warranty & RMA', path: '/business/warranty', icon: Shield, moduleAccess: ['serial_tracking'] },

  // ── Back Office ──
  { name: 'Accounting', i18nKey: 'nav.accounting', path: '/business/accounting', icon: Calculator, moduleAccess: ['accounting', 'finance'], adminOnly: true, section: 'Back Office' },
  { name: 'Staff & HR', path: '/business/hr/employees', icon: UserCog, moduleAccess: ['hr', 'human resources'], adminOnly: true },
  { name: 'Payroll', path: '/business/hr/payroll', icon: Receipt, moduleAccess: ['hr', 'human resources'], adminOnly: true },
  { name: 'Users & Roles', i18nKey: 'nav.usersRoles', path: '/business/users', icon: Users, moduleAccess: ['users', 'iam', 'roles'], adminOnly: true },
  { name: 'Reports', i18nKey: 'nav.reports', path: '/business/reports', icon: BarChart3, moduleAccess: ['reports', 'analytics'], adminOnly: true },
  { name: 'Branches & Locations', path: '/business/settings/locations', icon: Building2, adminOnly: true },
  { name: 'Settings', i18nKey: 'nav.settings', path: '/business/settings', icon: Settings, moduleAccess: ['settings'], adminOnly: true },
  { name: 'Subscription', path: '/business/billing', icon: Star, adminOnly: true },
];

/**
 * Cashier / Staff sidebar — derived at runtime by filtering out adminOnly items.
 */
export function getFilteredMenu(items: SidebarMenuItem[], isCashier: boolean): SidebarMenuItem[] {
  if (!isCashier) return items;
  return items.filter(item => !item.adminOnly);
}

/**
 * SuperAdmin sidebar menu.
 */
export const superadminMenuItems: SidebarMenuItem[] = [
  { name: 'Dashboard', path: '/superadmin', icon: LayoutDashboard, section: 'Platform' },
  { name: 'Tenants', path: '/superadmin/tenants', icon: Building2 },
  { name: 'Subscriptions', path: '/superadmin/subscriptions', icon: CreditCard },
  { name: 'Subscription Requests', path: '/superadmin/subscription-requests', icon: ClipboardList },
  { name: 'Licenses', path: '/superadmin/licenses', icon: Shield },
  { name: 'Approvals', path: '/superadmin/approvals', icon: FileText, section: 'Operations' },
  { name: 'Audit Logs', path: '/superadmin/audit-logs', icon: BarChart3 },
  { name: 'Email Logs', path: '/superadmin/email-logs', icon: Receipt },
  { name: 'Monitoring', path: '/superadmin/monitoring', icon: Monitor },
  { name: 'Support', path: '/superadmin/support', icon: Users },
  { name: 'Settings', path: '/superadmin/settings', icon: Settings, section: 'System' },
  { name: 'Profile', path: '/superadmin/profile', icon: UserCog },
];
