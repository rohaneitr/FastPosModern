/**
 * Centralized SWR cache key constants.
 * Ensures consistent cache invalidation across the application.
 */
export const queryKeys = {
  // Auth & Profile
  user: '/user',

  // Dashboard
  dashboardKPI: '/reports/dashboard',
  eodReport: '/reports/eod',

  // Inventory
  inventoryStock: '/inventory/stock',
  locations: '/locations',

  // Sales
  salesList: (page: number) => `/sales?page=${page}`,
  recentTransactions: '/sales?limit=10',

  // Products
  productsList: (page: number, search?: string) =>
    `/products?page=${page}${search ? `&search=${search}` : ''}`,

  // Contacts
  contactsList: '/contacts',

  // Settings
  subscription: '/settings/subscription',
} as const;
