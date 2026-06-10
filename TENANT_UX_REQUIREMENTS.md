# TENANT DASHBOARD ARCHITECTURAL AUDIT & UX REQUIREMENTS

## TOP 3 HIGH-RISK TECHNICAL AREAS
1. **Inventory Concurrency & Race Conditions ("Ghost-Sales")**: Multiple hardware terminals trying to deduct from the same master inventory pool simultaneously.
2. **Offline-First Synchronization**: Reconciling the local IndexedDB/Zustand transaction queue with the PostgreSQL server post-outage without losing financial integrity or triggering duplicates.
3. **Strict Branch Isolation**: A cashier in Branch A must not be able to process sales, view stock, or access customer wallets from Branch B, enforced at both the UI and API layer.

---

## DOMAIN MAPPING: UX TO API BLUEPRINT

### Domain 1: Multi-Location Business & RBAC
* **UX Component**: `src/app/(dashboards)/business/locations/page.tsx`
* **API Dependency**: `GET /api/v1/tenant/locations`, `POST /api/v1/tenant/locations`
* **UX Requirement**: A map/list view of branches. Role assignment must visually restrict navigation (e.g., Cashiers cannot see the "Accounting" tab).

### Domain 2: Product & Inventory Catalog
* **UX Component**: `src/app/(dashboards)/business/inventory/page.tsx`
* **API Dependency**: `GET /api/v1/tenant/products` (paginated, searched), `POST /api/v1/tenant/inventory/adjust`
* **UX Requirement**: High-density data table for SKUs with low-stock warning indicators (`text-rose-500` for `stock < threshold`). Modal for stock transfers between branches.

### Domain 3: The Point of Sale (POS) Interface
* **UX Component**: `src/app/(pos)/terminal/page.tsx`
* **API Dependency**: `POST /api/v1/tenant/sales/checkout` (Sync target), `GET /api/v1/tenant/catalog/pos-sync`
* **UX Requirement**: Grid layout for fast-tap touchscreens. Must contain a network indicator component (Online/Offline) and an Unsynced Transactions counter badge.

### Domain 4: Accounting & Ledger
* **UX Component**: `src/app/(dashboards)/business/accounting/page.tsx`
* **API Dependency**: `GET /api/v1/tenant/reports/ledger`, `POST /api/v1/tenant/registers/close`
* **UX Requirement**: Double-entry ledger view (Debits vs Credits). Must include an End-of-Day (EOD) wizard for blind-counting the cash drawer.

### Domain 5 & 6: CRM & Supplier Purchasing
* **UX Component**: `src/app/(dashboards)/business/customers/page.tsx` & `src/app/(dashboards)/business/purchases/page.tsx`
* **API Dependency**: `GET /api/v1/tenant/customers/{id}/wallet`, `POST /api/v1/tenant/purchases/receive`
* **UX Requirement**: Customer profile slider. PO generation interface with dynamic row insertion for receiving stock.
