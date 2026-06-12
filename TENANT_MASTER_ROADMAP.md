# TENANT & BUSINESS ADMIN ECOSYSTEM ROADMAP (PHASE 2)

With the SuperAdmin domain fully secured, hardened, and scalable, we now move into the core operational value of the SaaS: The Tenant/Business ecosystem. This ecosystem governs what the paying customers (businesses) see, control, and execute.

## Domain 1: Multi-Location Business & Hierarchy
- **Branch Management**: Businesses need the ability to add unlimited branches (within plan limits) and track inventory/sales by location.
- **Role-Based Access Control (RBAC)**: Secure definition of roles (Cashier, Manager, Admin) and assignment of specific permissions natively hooked to our domain middlewares.
- **Employee Clock-In/Out (HR Mini-Module)**: Simple attendance tracking for cashiers and managers starting their POS shifts.

## Domain 2: Product & Inventory Catalog
- **Matrix Products (Variants)**: Implementing size/color matrix logic for retail setups.
- **Multi-Location Inventory**: Tracking specific stock quantities per branch instead of a global pool.
- **Stock Transfers & Adjustments**: Safe transactional ledgers for moving stock between stores and correcting shrink/waste.
- **Barcode & SKU Generation**: Utilities for auto-generating internal EAN-13 barcodes.

## Domain 3: The Point of Sale (POS) Interface
- **Offline-First Synchronization Engine**: Perfecting the queue-based sync so that internet outages do not halt the checkout lane.
- **Hardware Integration**: Print nodes for receipt printers, cash drawer kicks, and generic barcode scanner HID reading.
- **Cart Management**: Applying discounts (item-level vs order-level), overriding taxes, and splitting payments (Cash + Card + Store Credit).

## Domain 4: Accounting & Ledger
- **Double-Entry Architecture**: Ensure every transaction writes balanced debits and credits.
- **End-of-Day (EOD) Reconciliation**: Workflow for managers to count the drawer and close the register batch, flagging discrepancies.
- **P&L / Tax Reporting**: Generating period-based tax reports and general ledger summaries.

## Domain 5: Customer Relationship Management (CRM)
- **Customer Profiles**: Tracking purchase history, outstanding balances, and reward points.
- **Store Credit / Wallets**: Managing refunds via store credit rather than cash.

## Domain 6: Supplier & Purchasing
- **Purchase Orders (POs)**: Generating POs, tracking pending deliveries, and receiving stock dynamically into inventory.
- **Supplier Ledgers**: Tracking accounts payable to vendors.

### Execution Strategy
All subsequent domains must strictly abide by the Tenant Isolation boundaries. No query can be executed without `where('business_id', $currentTenantId)`, and all API routes must sit behind the `EnsureLicenseIsActive` and `PlanLimitEnforcer` middlewares.
