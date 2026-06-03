# Project Memory

## Mission
Fully analyze, document, modernize, and rebuild the existing PHP ERP/POS/Inventory legacy system into a modern, production-grade, SaaS-ready platform. 

## Source of Truth
- **Legacy Codebase:** `C:\Users\Rohan\Desktop\FastPos`
- **Rule:** The legacy codebase is READ ONLY. It must never be modified, renamed, deleted, refactored, or overwritten.

## Current Phase: 5. Implement
- [x] **1. Analyze:** Performed analysis of tech stack, controllers, modules, and database schema.
- [x] **2. Document:** Populated `feature-inventory.md`, `architecture-notes.md`, and `database-map.md`.
- [x] **3. Plan:** Define the modernization strategy and architecture.
- [x] **4. Verify:** Confirm plan with user/stakeholders.
- [x] **6. Domain Implementation:** Initialized `IAM` and `Tenant` domains (Migrations, Models, API Authentication).
- [x] **7. ETL Migration:** Wrote `MigrateLegacyTenants` Artisan command to safely extract users/businesses from the legacy DB into the modern DDD structure.
- [x] **8. Catalog Domain:** Initialized `Catalog` domain (Products, Variations, Categories, Brands, Units) with strict Tenant isolation.
- [x] **9. Inventory & Sales Domains:** Defined schemas and models for `Locations`, `Stock`, `Transactions`, `Transaction Lines`, and `Payments`.
- [x] **10. CRM Domain:** Defined schemas and models for `Contacts` (Customers and Suppliers). **UPDATE**: Implemented `ContactController` API and Next.js `contacts/page.tsx` UI with glassmorphism design.
- [x] **11. Purchases Domain:** Created `PurchaseController`, updated schemas to link suppliers (`contact_id`) to transactions, and added the Next.js `purchases/page.tsx` UI.
- [x] **12. Inventory Domain:** Built `InventoryController` to manage stock listings and manual adjustments, paired with Next.js `inventory/page.tsx`.
- [x] **13. Accounting Domain:** Defined `expenses` schemas, built `ExpenseController`, and created Next.js `accounting/page.tsx` UI.
- [x] **14. Frontend State & Integration:** Converted the POS UI to use `Zustand` for interactive cart management and `Axios` for backend API integration with CSRF protection.
- [x] **15. Reporting Domain:** Implemented `ReportController` for P&L and Sales trends, and a visual dashboard at `reports/page.tsx`.
- [x] **16. Superadmin SaaS Domain:** Created `SuperadminController` to manage tenants and a `superadmin/page.tsx` dashboard.
- [x] **17. HR & Payroll Domain:** Defined `employees` and `payrolls` schemas, implemented `HRController`, and a dual-tab Next.js UI at `hr/page.tsx`.
- [x] **12. POS Checkout Flow:** Implemented end-to-end checkout mapping Next.js cart payloads to the Laravel Sales Domain, persisting into transaction ledger schemas.
- [x] **13. Mobile App Bridge:** Established `api/mobile/*` route group in Laravel to maintain backward compatibility with the existing React Native offline-first mobile app (`FastPosMobile`).

## Discoveries & Architecture Summaries
- **Legacy Stack:** Laravel 9, PHP 8.0, jQuery/DataTables, Spatie Permissions, multiple payment gateways.
- **Complexity:** Over 60 fat controllers handling everything from Sales to HR to SaaS management. A unified `transactions` table orchestrates all inventory movements.
- **Mobile Context:** There are recent migrations and controllers indicating an active, offline-first mobile app relying on API endpoints and a "mobile_activations" system.
- **SaaS:** Admin module (`Modules/Superadmin`) exists for managing multi-tenancy.

## Guiding Principles for the New Platform
1. **API-First Architecture:** To support both web and the offline-first mobile app symmetrically.
2. **Strict Multi-Tenancy:** Robust tenant isolation at the database or row level.
3. **Clean Architecture:** Moving away from fat controllers to Service/Action classes and Repository patterns for better testability.
4. **Modern Frontend:** Transitioning from Blade/jQuery to a modern framework (Next.js/React or Vue/Inertia) for a responsive, reactive POS experience.
