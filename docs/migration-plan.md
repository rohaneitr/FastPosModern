# Migration & Modernization Plan

## Phase 1: Preparation & Scaffolding (Backend)
1. **Initialize Modern Laravel:** Create a fresh Laravel 11 / PHP 8.3 project in `FastPosModern/server`.
2. **Core Dependencies:** Install modern packages (Laravel Sanctum/Passport for Auth, Spatie Multi-tenancy or explicit scope, Spatie Permissions).
3. **Database Migration Strategy:** 
   - Re-write the 300+ legacy migrations into consolidated, clean migrations.
   - Retain table and column naming conventions where possible to ease data migration.
   - Implement strict Tenant ID scoping on all tables.

## Phase 2: Domain Driven Restructuring (Backend)
Instead of placing all logic in massive controllers, we will divide the app into Domains (Domain-Driven Design concepts):
- **Domain: IAM (Identity & Access Management)** (Users, Roles, Auth)
- **Domain: Tenant (SaaS)** (Businesses, Subscriptions, Packages)
- **Domain: Catalog** (Products, Variations, Categories, Brands)
- **Domain: Inventory** (Stock Transfers, Adjustments, Locations)
- **Domain: CRM** (Contacts, Customers, Suppliers)
- **Domain: Sales & POS** (Sell Transactions, Returns, Cash Registers)
- **Domain: Purchases** (Purchase Orders, Transactions)
- **Domain: Accounting** (Expenses, Accounts)

## Phase 3: API & Mobile Parity
- Create robust, versioned REST/GraphQL APIs.
- Ensure all endpoints required by the existing mobile application (offline-first sync) are ported first and behave identically to avoid breaking mobile clients.

## Phase 4: Frontend Development
- Initialize a modern Next.js/React frontend in `FastPosModern/client`.
- **Admin/Dashboard App:** For reporting, catalog management, and settings.
- **Web POS App:** A high-performance, keyboard-friendly point of sale interface.

## Phase 5: Data Migration Scripting
- Develop Artisan commands to connect to the old database and ETL (Extract, Transform, Load) the data into the new schema cleanly.
- Map old `business_id` accurately to the new multi-tenant structure.

## Phase 6: QA & Verification
- [x] Unit and Feature tests for every domain. (Written comprehensive testing suites for CRM, Inventory, Purchases, Accounting, Reporting, HR, and Superadmin domains).
- [x] Parallel run: Validating API responses of old vs new systems. (Implemented `php artisan parity:validate` command to diff Legacy API against Modern API).
