# FastPOS Modern — Master Project Memory
> **Last Updated:** 2026-06-12  
> **Maintained by:** Antigravity AI Agent  
> **Purpose:** Single source of truth for all architectural decisions, tech stack versions, known issues, and refactoring plans.

---

## 1. PROJECT IDENTITY

| Field | Value |
|---|---|
| **Project Name** | FastPOS Modern (FastPosModern) |
| **Type** | Multi-Tenant SaaS ERP / Point-of-Sale Platform |
| **GitHub Repo** | https://github.com/rohaneitr/FastPosModern |
| **Architecture Pattern** | Modular Monolith (Backend) + Feature-Sliced (Frontend) |
| **Tenancy Strategy** | Shared Database, `business_id` column isolation |
| **Current Health Score** | 7.5 / 10 (Phase 1 + Phase 2 complete) |
| **Project Owner** | Rohan (rohaneitr) |
| **Started** | June 2026 |
| **Target** | Production-ready SaaS ERP for SMBs |

---

## 2. COMPLETE TECH STACK & VERSIONS

### 2A. Backend (server/)
| Technology | Version | Purpose |
|---|---|---|
| **PHP** | ^8.3 | Runtime |
| **Laravel Framework** | 13.14.0 (^13.8) | Core Framework |
| **Laravel Sanctum** | ^4.0 | API Token Authentication |
| **Laravel Tinker** | ^3.0 | REPL / Debugging |
| **Spatie Permission** | ^8.0 | RBAC (Role-Based Access Control) |
| **Spatie Activity Log** | ^5.0 | Audit Trail / Activity Logging |
| **Laravel DomPDF** | ^3.1 | PDF Generation (Invoices) |
| **Bacon QR Code** | ^3.0 | QR Code Generation |
| **Jenssegers Agent** | ^2.6 | User Agent Detection (Device tracking) |
| **PragmaRX Google2FA** | * | Two-Factor Authentication (TOTP) |
| **Predis** | ^3.5 | Redis PHP Client |
| **PHPUnit** | ^12.5.12 | Unit & Feature Testing |
| **Faker** | ^1.23 | Test Data Generation |

### 2B. Database & Infrastructure
| Technology | Version | Purpose |
|---|---|---|
| **PostgreSQL** | 16-alpine | Primary Database |
| **Redis** | 7-alpine | Cache / Queue / Sessions |
| **Nginx** | Latest | Reverse Proxy / SSL Termination |
| **Docker Compose** | 3.8 | Container Orchestration |

### 2C. Frontend (client/)
| Technology | Version | Purpose |
|---|---|---|
| **Next.js** | 16.2.7 | React Framework (App Router) |
| **React** | 19.2.4 | UI Library |
| **TypeScript** | ^5 | Type Safety |
| **TailwindCSS** | ^4 | Styling |
| **Zustand** | ^5.0.14 | Global State Management |
| **SWR** | ^2.4.1 | Data Fetching / Cache |
| **Axios** | ^1.16.1 | HTTP Client |
| **React Hook Form** | ^7.78.0 | Form Management |
| **Zod** | ^4.4.3 | Schema Validation |
| **Recharts** | ^3.8.1 | Charts & Data Visualization |
| **Dexie** | ^4.4.3 | IndexedDB ORM (Offline-First) |
| **Decimal.js** | ^10.6.0 | Precise Financial Calculations |
| **Lucide React** | ^1.17.0 | Icon Library |
| **cmdk** | ^1.1.1 | Command Palette |
| **react-hot-toast** | ^2.6.0 | Notifications |
| **@ducanh2912/next-pwa** | ^10.2.9 | PWA Support |
| **Playwright** | ^1.60.0 | E2E Testing |
| **Vitest** | ^4.1.8 | Unit Testing |

### 2D. Mobile App (mobile/)
| Technology | Version | Purpose |
|---|---|---|
| **Expo** | ~56.0.8 | React Native Framework |
| **React Native** | 0.85.3 | Mobile UI Framework |
| **React** | 19.2.3 | UI Library |
| **TanStack Query** | ^5.101.0 | Server State Management |
| **Axios** | ^1.17.0 | HTTP Client |
| **Expo Secure Store** | ^56.0.4 | Encrypted Local Storage |
| **Expo Crypto** | ^56.0.4 | Device Fingerprinting |
| **TypeScript** | ~6.0.3 | Type Safety |

---

## 3. INFRASTRUCTURE & DEPLOYMENT

### Docker Services (Production)
```
fastpos_backend_prod   → PHP-FPM Laravel API  → Port 8000
fastpos_frontend_prod  → Next.js App          → Port 3000
fastpos_queue_prod     → Laravel Queue Worker → Background jobs
fastpos_postgres_prod  → PostgreSQL 16        → Database
fastpos_redis_prod     → Redis 7              → Cache/Queue/Sessions
```

### Environment Configuration
- **DB**: PostgreSQL (`pgsql` driver), DB name: `fastpos`
- **Cache**: Redis (Production) / Database (Local)
- **Queue**: Redis (Production) / Database (Local)
- **Sessions**: Redis (Production) / Database (Local)
- **Mail**: SMTP (Production) / Log (Local)
- **Auth**: Laravel Sanctum (Token-based API auth)
- **2FA**: Google Authenticator TOTP

### Docker Compose Deployment Notes
> ⚠️ **CRITICAL**: Backend Docker image does NOT use volume mapping for source code.
> Any PHP code changes REQUIRE a full `docker build` + restart.
> Frontend Turbopack drops inotify events on Windows — container restart required after programmatic file edits.

---

## 4. BACKEND MODULE ARCHITECTURE

### Module List (26 Modules under `server/app/Modules/`)

| Module | Responsibility | Status |
|---|---|---|
| **IAM** | Identity & Access Management, Auth, User management | ✅ Active |
| **Tenant** | Business provisioning, Subscriptions, Licensing, Settings | ✅ Active |
| **SuperAdmin** | Platform-level SaaS management, oversight | ✅ Active |
| **Catalog** | Products, Variants, Product transfer | ✅ Active |
| **Inventory** | Stock management, Adjustments, Layers (FIFO/FEFO) | ✅ Active |
| **Sales** | POS Checkout, Transactions, Cash Registers | ✅ Active |
| **Procurement** | Purchase Orders, Supplier management | ✅ Active |
| **CRM** | Contacts (Customers/Suppliers), Customer Groups | ✅ Active |
| **Finance** | General Ledger, Journal Entries, Expenses | ✅ Active |
| **Reports** | Financial Reports (P&L, Balance Sheet, Valuation) | ✅ Active |
| **Reporting** | Additional reporting endpoints | ✅ Active |
| **HR** | Employees, Payroll management | ✅ Active |
| **Imports** | CSV/Parquet bulk data import with job queue | ✅ Active |
| **Audit** | Forensic audit log, immutable checksum trail | ✅ Active |
| **SerialCore** | Serial number / IMEI tracking for products | ✅ Active |
| **Restaurant** | KOT tickets, Table management, Sessions | ✅ Active |
| **Pharmacy** | Rx prescription tracking, medicine metadata | ✅ Active |
| **Clinical** | Patient management, Appointments, Lab orders | ✅ Active |
| **Clinic** | (Legacy/transitioning) Clinical services | ⚠️ Review |
| **Manufacturing** | Production orders, BOM, Scrap tracking | ✅ Active |
| **HardwareBuilder** | PC component assembly & quotation builder | ✅ Active |
| **Education** | Student management, Batches, Exams, Invoices | ✅ Active |
| **Security** | Security service helpers | ✅ Active |
| **Shared** | Shared events used cross-module | ✅ Active |
| **Kernel** | Module dependency manifest validator | ✅ Active |
| **Auth** | (Legacy auth controllers) | ⚠️ Review |

### Module Loading Mechanism
- Modules auto-loaded by `ModuleServiceProvider`
- Each module can have: `Routes/api.php`, `Database/Migrations/`, `module.json`
- Routes prefixed at `api/v1` automatically

---

## 5. RBAC ARCHITECTURE

### Roles (Defined in `RolesAndPermissionsSeeder.php`)

| Role | Scope | Permissions |
|---|---|---|
| **SuperAdmin** | Platform-wide | ALL permissions (`platform.manage` + everything) |
| **BusinessAdmin** | Tenant-wide | `tenant.manage`, `users.*`, `products.manage`, `inventory.manage`, `sales.manage`, `reports.manage`, `pos.access` |
| **Cashier** | POS Terminal | `pos.access`, `sales.manage` |
| **InventoryManager** | Stock operations | `products.manage`, `inventory.manage` |
| **Accountant** | Financial view | `reports.manage`, `sales.manage` |

### Defined Permissions (50 total — ✅ COMPLETE after Phase 1)
```
platform.manage       → Full SaaS control (SuperAdmin only)
tenant.manage         → Business settings, locations, branding, API keys
tenant.billing        → Subscription management, plan changes
tenant.devices        → POS device activation/revocation
users.view            → View staff list
users.create          → Create staff accounts
users.edit            → Edit staff details
users.delete          → Delete staff
users.invite          → Send team invitations
roles.manage          → Create/edit roles and permissions
products.view         → Browse product catalog
products.create       → Add new products
products.edit         → Edit product details
products.delete       → Delete products (BusinessAdmin only)
products.import       → Bulk CSV/Parquet import
inventory.view        → View stock levels and history
inventory.adjust      → Manual stock adjustments
inventory.transfer    → Transfer between locations
inventory.labels      → Print barcode labels
categories.manage     → CRUD for categories
brands.manage         → CRUD for brands
units.manage          → CRUD for units
pos.access            → Open POS terminal (hardware-locked)
sales.view            → View transaction history
sales.manage          → Process sales, sync offline
sales.void            → Reverse/cancel transactions (supervisor)
sales.discount        → Apply manual discounts
registers.manage      → Open/close cash registers
contacts.view         → View CRM contacts
contacts.create       → Create new contacts
contacts.edit         → Edit contacts
contacts.delete       → Delete contacts (BusinessAdmin only)
suppliers.view        → View supplier list
suppliers.create      → Create suppliers
suppliers.edit        → Edit suppliers
suppliers.delete      → Delete suppliers (BusinessAdmin only)
purchases.view        → View purchase orders
purchases.create      → Create purchase orders
purchases.edit        → Edit purchase orders
purchases.delete      → Delete purchase orders (BusinessAdmin only)
purchases.receive     → Receive stock against PO
reports.view          → View KPIs and financial reports
reports.export        → Export reports to PDF/CSV
accounting.view       → View GL, trial balance, balance sheet
expenses.view         → View expense records
expenses.create       → Log expenses
expenses.edit         → Edit expenses
expenses.delete       → Delete expenses (BusinessAdmin only)
hr.employees.manage   → Full HR employee lifecycle
hr.payroll.manage     → Generate and process payroll
hr.attendance         → Clock in/out (all staff)
```

### Roles (6 total — ✅ Manager role ADDED in Phase 1)
| Role | Permissions | Notes |
|---|---|---|
| **SuperAdmin** | ALL (50) | Platform gate uses `role:SuperAdmin` BY DESIGN |
| **BusinessAdmin** | 50 (all except platform.manage) | Full tenant control |
| **Manager** | 31 | NEW in Phase 1. Mid-level supervisor |
| **Cashier** | 11 | POS operations only |
| **InventoryManager** | 19 | Stock and catalog management |
| **Accountant** | 14 | Financial reporting and expenses |

### Middleware Stack
| Alias | Class | Purpose |
|---|---|---|
| `auth:sanctum` | Laravel Sanctum | Token authentication |
| `role:X` | Spatie | Role-based gate (HARDCODED — ⚠️ FLAW) |
| `permission:X` | Spatie | Permission gate (CORRECT pattern) |
| `role_or_permission:X\|Y` | Spatie | Mixed gate (FLAW — hardcodes roles) |
| `subscribed` | CheckSubscription | SaaS subscription validation |
| `hardware_lock` | VerifyHardwareHash | POS device fingerprint check |
| `rbac.shadow:X` | RbacShadowLogger | Non-blocking forensic RBAC audit |
| `maintenance` | CheckMaintenanceMode | Platform maintenance mode gate |
| `module:X` | CheckModuleAccess | Tenant module entitlement |
| `plan.limits` | CheckPlanLimits | Plan capacity enforcement |
| `idle.timeout` | IdleTimeoutMiddleware | Session idle logout |
| `idempotency` | EnforceIdempotencyGateway | Duplicate transaction prevention |
| `entitlement` | EntitlementMiddleware | Feature flag/entitlement check |

---

## 6. DATABASE ARCHITECTURE

### Multi-Tenancy Strategy
- **Type**: Shared Single Database
- **Isolation Column**: `business_id` (FK → `businesses.id`)
- **Base Model**: `TenantModel` abstract class with automatic `business_id` global scope
- **Auto-assignment**: `TenantModel::creating()` auto-assigns `business_id` from auth user

### Total Tables: ~85 tables

### Tables WITH business_id (Tenant-Scoped ✅)
`businesses`, `users`, `products`, `transactions`, `contacts`, `purchases`, `locations`, `categories`, `brands`, `units`, `employees`, `payrolls`, `tax_rates`, `expenses`, `expense_categories`, `invoice_layouts`, `barcodes`, `printers`, `warranties`, `selling_price_groups`, `customer_groups`, `cash_registers`, `inventory_layers`, `inventory_item_serials`, `stock_ledgers`, `stock_history`, `loyalty_point_ledgers`, `customer_wallets`, `supplier_ledgers`, `journal_entries`, `chart_of_accounts`, `finance_accounts`, `finance_journal_entries`, `currency_rates`, `import_statuses`, `email_logs`, `audit_logs`, `activity_log`, `restaurant_tables`, `restaurant_sessions`, `clinical_patients`, `clinical_appointments`, `clinical_lab_orders`, `prescriptions`, `production_orders`, `student_batches`, `student_enrollments`, `student_invoices`, `exam_schedules`, `exam_results`, `commercial_quotations`, `tenant_modules`, `tenant_invoices`, `subscriptions`, `saas_subscriptions`, `saas_payment_ledgers`, `team_invitations`, `tickets`

### Tables MISSING business_id (⚠️ CRITICAL GAPS)
| Table | Risk Level | Notes |
|---|---|---|
| `transaction_lines` | 🔴 HIGH | Core sales line items — isolated only via JOIN to `transactions` |
| `purchase_lines` | 🔴 HIGH | Procurement line items — isolated only via JOIN to `purchases` |
| `stock_adjustments` | 🟠 MEDIUM | Uses `location_id` as proxy — but location check is indirect |
| `product_stocks` | 🟠 MEDIUM | Uses `location_id` as proxy |
| `variations` | 🟡 LOW | Uses `product_id` as proxy |
| `transaction_payments` | 🟡 LOW | Uses `transaction_id` as proxy |
| `journal_lines` | 🟡 LOW | Uses `journal_entry_id` as proxy |
| `finance_journal_lines` | 🟡 LOW | Uses `journal_entry_id` as proxy |
| `transaction_item_serials` | 🟡 LOW | Uses `transaction_item_id` as proxy |

---

## 7. KNOWN TECHNICAL DEBT (Priority Order)

### 🔴 P1 — Critical — ✅ COMPLETED (Phase 1)

#### RBAC-1: Hardcoded Roles in Routes — ✅ FIXED
- **Was**: `middleware('role:BusinessAdmin')` / `middleware('role_or_permission:X|Y')` across 9 route files
- **Fix Applied**: Replaced ALL instances with `permission:X` gates. `role:SuperAdmin` intentionally kept.
- **Files Fixed**: All 9 module route files + `routes/api.php`

#### RBAC-2: Misaligned Permission Gates — ✅ FIXED
- `DELETE /contacts` → Now gated by `contacts.delete` ✅
- `DELETE /suppliers` → Now gated by `suppliers.delete` ✅
- `DELETE /purchases` → Now gated by `purchases.delete` ✅
- `DELETE /expenses` → Now gated by `expenses.delete` ✅

#### DB-1: Missing business_id on transaction_lines — ⏳ NEXT (Phase 2)

### 🟠 P2 — High (Fix in first month post-launch)

#### CODE-1: Fat Controllers
| Controller | Lines | Issue |
|---|---|---|
| `TransactionController` | 1,226 | Entire checkout logic inline |
| `SuperadminController` | 776 | Tenant creation + billing + impersonation all in one |
| `SubscriptionController` | 560 | 14 raw DB calls inline |
| `PurchaseController` | 551 | 9 raw DB calls inline |
| `DeviceHeartbeatController` | 307 | 17 raw DB calls inline |

- **Fix**: Extract to `ProcessTransactionService`, `CreateTenantAction`, etc.

#### CODE-2: Fragmented RBAC Policy Enforcement
- **Problem**: Security middleware defined in both `server/routes/api.php` AND `server/app/Modules/*/Routes/api.php`. No centralized policy map.
- **Fix**: Move ALL security middleware to `routes/api.php` or dedicated `RouteServiceProvider`.

### 🟡 P3 — Medium (Fix in roadmap)

#### FE-1: Bloated Frontend Pages
| Page | Lines | Issue |
|---|---|---|
| `superadmin/tenants/page.tsx` | 731 | Mixed state + render + API logic |
| `(pos)/terminal/page.tsx` | 571 | Cart logic + UI + barcode scan all inline |
| `business/categories/page.tsx` | 512 | Should be extracted to components |

#### ARCH-1: Dual Module Systems
- Both `Reporting` and `Reports` modules exist. Unclear separation of responsibility.
- Both `Clinic` and `Clinical` modules exist. Should be merged.

#### ARCH-2: Test Utility Files in Root
- `server/` root contains: `test_api.php`, `test_controller.php`, `test_ledger.php`, `test_login.php`, `analyze_schema.php`, `dump_schema.php`
- These should be removed from production codebase or moved to `scripts/`

---

## 8. ARTISAN COMMANDS AVAILABLE

| Command | Purpose |
|---|---|
| `CheckExpiredSubscriptions` | Cron: expire trial/subscription |
| `CheckTrialStatus` | Cron: trial expiry warnings |
| `CleanupArchivedData` | Purge soft-deleted old records |
| `CleanupOrphanTenantsCommand` | Remove businesses with no users |
| `DatabaseAuditCommand` | Run DB integrity check |
| `ExecuteSecureBackup` | Trigger encrypted DB backup |
| `MigrateLegacyTenants` | One-time legacy data migration |
| `RbacAudit` | Audit RBAC permission coverage |
| `RbacRollback` | Rollback RBAC changes |
| `SetupLocalTestingEnv` | Seed local environment |
| `SuperAdminAuditCommand` | SuperAdmin activity audit |
| `SuperAdminStressTestCommand` | Load/stress test |
| `SystemIntegrityCheck` | Full system health scan |
| `TestImpersonationAudit` | Validate impersonation logs |
| `ValidateParity` | API schema parity check |
| `VerifyLedgerBalance` | Assert debit = credit in GL |
| `ChaosClusterSimulation` | Offline sync chaos test |

---

## 9. REFACTORING ROADMAP

### Phase 1: RBAC Hardening — ✅ COMPLETED (2026-06-12)
- [x] **Task 1.1**: Mapped all endpoints to granular permissions
- [x] **Task 1.2**: Added 50 permissions to seeder (was 10)
- [x] **Task 1.3**: Replaced ALL `middleware('role:...')` / `middleware('role_or_permission:...')` with `permission:X`
- [x] **Task 1.4**: Added `Manager` role (31 permissions) between Cashier and BusinessAdmin
- [x] **Task 1.5**: Seeder run — verified with `php artisan permission:show`

### Phase 2: Database Multi-Tenancy Completion — ✅ COMPLETED (2026-06-12)
- [x] **Task 2.1**: Migration `2026_06_12_142810_add_business_id_to_child_tables.php` — 9 tables upgraded
  - `transaction_lines`, `purchase_lines`, `transaction_payments`, `transaction_item_serials`
  - `product_stocks`, `stock_adjustments`, `variations`
  - `journal_lines`, `finance_journal_lines`
- [x] **Task 2.2**: No backfill needed — all tables had 0 rows at migration time
- [x] **Task 2.3**: `NOT NULL` + FK → `businesses.id` + composite performance indexes on all 9 tables
  - Verified: 9/9 `[PASS]` via automated verification script
- [x] **Task 2.4**: Models updated — `BelongsToBusiness` trait added:
  - New: `TransactionLine`, `TransactionPayment`, `TransactionItemSerial`, `StockAdjustment`, `ProductStock`, `FinanceJournalLine`
  - Updated: `Variation` (was plain Model), `PurchaseLine` (was plain Model), `JournalLine` (was plain Model)
  - Updated: `Sale` model — added `lines()` and `payments()` hasMany relationships
- [x] **Task 2.5**: All DB insert callsites fixed — `business_id` explicitly passed:
  - `TransactionController::checkout()` — `transaction_lines`, `transaction_payments`, `transaction_item_serials`
  - `TransactionController::holdTransaction()` — `transaction_lines`
  - `TransactionController::convertToInvoice()` — `transaction_lines`, `transaction_item_serials`
  - `PurchaseController::receive()` — `transaction_lines`
  - `PurchaseController::store()` / `update()` — `purchase_lines`
  - `ProductController::store()` — `variations`

### Phase 3: Controller Refactoring — 🔄 IN PROGRESS
- [x] **Task 3.1**: `TransactionController` refactored — 2026-06-12
  - **Before**: 1244 lines, 6+ responsibilities (pricing, inventory, serials, ledger, loyalty, notifications)
  - **After**: ~230 lines, pure HTTP orchestration only
  - **Extracted to**:
    - `Sales\DataTransferObjects\SaleCheckoutDTO` — immutable input type
    - `Sales\DataTransferObjects\SaleCheckoutResult` — immutable output type
    - `Sales\Actions\CalculateSaleTotalsAction` — zero-trust DB pricing
    - `Sales\Actions\SaleTotals` — pricing value object
    - `Sales\Services\ProcessSaleService` — core checkout pipeline
    - `Sales\Services\HoldTransactionService` — hold/resume/delete
  - Routes verified: 5 routes resolve correctly via IoC injection
  - Syntax verified: 7/7 files, zero errors
- [x] **Task 3.2**: `SuperadminController` refactored — 2026-06-12
  - **Before**: 777 lines, 21 methods, 5 domains, 20+ duplicate role checks
  - **After**: ~280 lines, pure HTTP orchestration, role guard via middleware
  - **Extracted to**:
    - `Tenant\Actions\CreateNewTenantAction` — atomic user+business+subscription+email pipeline
    - `Tenant\Actions\ImpersonateTenantAction` — scoped token + forensic audit
    - `Tenant\Services\TenantLicenseService` — license generate/toggle/revoke + device management
    - `Tenant\Services\TenantSubscriptionService` — renew/override subscription
  - **Bug fixed**: `restoreBusiness()` was an orphaned controller method with no route — now registered as `PATCH /superadmin/businesses/{id}/restore`
  - Routes verified: 10/10 business routes resolve, 45 total superadmin routes OK
  - Syntax verified: 5/5 files, zero errors

- [x] **Task 3.3**: `SubscriptionController` refactored — 2026-06-12
  - **Before**: 561 lines, 3 mixed domains (Plan CRUD, Subscription Lifecycle, Stripe Billing)
  - **After**: ~160 lines, pure HTTP orchestration
  - **Extracted to**:
    - `Tenant\Services\PlanManagementService` — Plan CRUD + active subscription guard + buildPlanData dedup
    - `Tenant\Services\SubscriptionLifecycleService` — renew, overrideStatus, updateCapabilities, changePlan
    - `Tenant\Services\StripePaymentService` — subscribe via Stripe, mock fallback, 3 webhook handlers, billing portal
  - **Deduplication**: `planValidationRules()` extracted to private helper (was copied in storePlan + updatePlan)
  - **Improvement**: `switch()` → `match()` for webhook event dispatch
  - Routes verified: 4 plan routes + 3 subscription routes + tenant routes all OK
  - Syntax verified: 4/4 files, zero errors

- [x] **Task 3.4**: `PurchaseController` refactored — 2026-06-12
  - **Before**: 561 lines, 3 domains, identical pricing blocks copy-pasted in store() and update()
  - **After**: ~120 lines, pure HTTP orchestration
  - **Key bug eliminated**: pricing formula (subtotal → discount → tax → shipping → grand_total → payment_status) was copy-pasted verbatim in store() AND update() — now in single `PurchaseTotalsCalculator`
  - **Extracted to**:
    - `Procurement\Actions\PurchaseTotalsCalculator` — deduped pricing formula (was 2× 65 lines → 1× 45 lines)
    - `Procurement\Actions\PurchaseTotals` — immutable value object with `toUpdateArray()`
    - `Procurement\Services\ProcessPurchaseService` — FIFO + double-entry + forensic audit pipeline
    - `Procurement\Services\ReceivePurchaseService` — WAC quick-receive (kept separate: different costing method)
  - Routes verified: 7 routes (index, store, show, update, destroy, receive ×2) all resolve
  - Syntax verified: 5/5 files, zero errors

- [ ] **Task 3.5**: Extract `DeviceHeartbeatController` (307L) → `DeviceRegistrationService`
- [ ] **Task 3.6**: Write unit tests for each Service class

### Phase 4: Frontend Component Decomposition (2–3 sessions)
- [ ] **Task 4.1**: Break `tenants/page.tsx` → `<TenantTable>`, `<TenantMetrics>`, `useTenants.ts`
- [ ] **Task 4.2**: Break `terminal/page.tsx` → extract to `features/pos/` components + hooks
- [ ] **Task 4.3**: Break `categories/page.tsx` → `<CategoryForm>`, `<CategoryTable>`, `useCategories.ts`

### Phase 5: Architecture Cleanup
- [ ] **Task 5.1**: Merge `Clinic` into `Clinical` module
- [ ] **Task 5.2**: Merge `Reports` into `Reporting` module
- [ ] **Task 5.3**: Move test utility scripts from `server/` root to `scripts/`
- [ ] **Task 5.4**: Move all route RBAC policy to centralized `RouteServiceProvider`

---

## 10. FRONTEND ARCHITECTURE

### App Router Structure
```
client/src/app/
├── (dashboards)/       → Multi-tenant dashboard (business admin, superadmin)
├── (pos)/terminal/     → POS terminal interface
├── (portal)/reports/   → Reporting portal
├── [domain]/           → Subdomain-based tenant routing
│   ├── login/          → Tenant-specific login
│   ├── (dashboards)/   → Tenant dashboard pages
│   └── restaurant/     → Restaurant module UI
├── superadmin-login/   → SuperAdmin login (separate from tenant)
├── register/           → Tenant self-registration
└── accept-invite/      → Team invitation acceptance
```

### State Management Strategy
| Store | Technology | Purpose |
|---|---|---|
| `useAuthStore` | Zustand | Auth token, user profile |
| `useCartStore` | Zustand | POS cart items + totals |
| `useRateLimitStore` | Zustand | Client-side rate limit tracking |
| `useSyncStore` | Zustand | Offline sync queue state |

### Offline-First Architecture
- **Library**: Dexie (IndexedDB ORM)
- **Sync Manager**: `lib/sync/SyncManager.ts`
- **Strategy**: Queue transactions in IndexedDB → sync on reconnection via `/sync/push`
- **Conflict Resolution**: Server wins on conflict; client re-fetches clean state

### API Communication
- **Base Client**: `lib/api.ts` (Axios instance with interceptors)
- **Data Fetching**: SWR for server-state caching
- **Auth**: Sanctum token in Authorization header + cookie-based Sanctum CSRF

---

## 11. SECURITY ARCHITECTURE

### Authentication Flow
1. Tenant resolves via subdomain → `/api/v1/tenant/resolve/{subdomain}`
2. User POSTs credentials → `/api/v1/auth/login` (with subdomain validation)
3. Sanctum issues Bearer token
4. Token stored in `useAuthStore` (Zustand) + HttpOnly cookie via Sanctum
5. Every request carries `Authorization: Bearer {token}` header

### POS Hardware Lock
- Device fingerprint created on first activation → stored in `user_devices`
- `VerifyHardwareHash` middleware validates hash on every POS request
- Deactivated devices are rejected at middleware level

### Audit Trail
- `AuditLog` model with immutable `checksum` (SHA-256 of content)
- `RbacShadowLogger` silently logs every permission check outcome to `rbac_shadow_log`
- Spatie Activity Log captures model-level changes to `activity_log`
- `impersonator_id` tracked in audit_logs for SuperAdmin impersonation

### Subscription Enforcement
- `CheckSubscription` middleware validates: Business active → Has subscription → Not expired
- SuperAdmin bypasses all subscription checks via `hasRole('SuperAdmin')`
- Plan limits (user count, location count) attached to request via middleware for downstream validation

---

## 12. IMPORTANT ARCHITECTURAL DECISIONS (ADRs)

### ADR-001: Shared Database Multi-tenancy
- **Decision**: Use single shared PostgreSQL database with `business_id` isolation rather than separate DB per tenant
- **Reason**: Easier to operate and maintain for a small team; allows cross-tenant SuperAdmin queries
- **Trade-off**: More developer discipline required; potential data leakage if `business_id` scope missed

### ADR-002: Modular Monolith over Microservices
- **Decision**: Keep all business logic in one Laravel application organized into modules
- **Reason**: Team size is small; microservices would add operational complexity without benefit
- **Trade-off**: Modules can become coupled if discipline breaks down

### ADR-003: Laravel Sanctum for API Auth (not Passport)
- **Decision**: Use Sanctum (token-based) not Passport (OAuth)
- **Reason**: SPA + Mobile app use case; Sanctum is simpler and sufficient
- **Trade-off**: No OAuth flows available (not needed for current use case)

### ADR-004: Offline-First POS via IndexedDB
- **Decision**: POS terminal works offline using Dexie + sync queue
- **Reason**: POS must not stop working on network loss
- **Trade-off**: Conflict resolution complexity; sync bugs possible

### ADR-005: TenantModel Global Scope
- **Decision**: All tenant-aware models extend `TenantModel` which auto-applies `business_id` WHERE clause
- **Reason**: Prevents accidental data leakage; single enforcement point
- **Trade-off**: Must use `withoutTenantScope()` in SuperAdmin contexts explicitly

---

## 13. LOCAL DEVELOPMENT SETUP

```bash
# Backend
cd server
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DatabaseSeeder
php artisan serve

# Frontend
cd client
npm install
npm run dev

# Mobile
cd mobile
npm install
npx expo start
```

### Database Credentials (Local)
- **Host**: 127.0.0.1:5432
- **DB**: fastpos
- **User**: fastpos_user
- **Password**: password123

---

## 14. FILES TO CLEAN UP (AFTER REFACTORING)

These files exist in the repo root/server root and should be removed or relocated:

```
FastPosModern/
├── analyze_architecture.php     → DELETE (analysis script)
├── 0000.csv                     → DELETE (test data file)
├── 0000.parquet                 → DELETE (test data file)

server/
├── analyze_schema.php           → DELETE (analysis script)
├── dump_schema.php              → DELETE (analysis script)
├── schema_dump.json             → DELETE (generated artifact)
├── analysis_result.txt          → DELETE (generated artifact)
├── test_api.php                 → MOVE to scripts/
├── test_controller.php          → MOVE to scripts/
├── test_ledger.php              → MOVE to scripts/
├── test_login.php               → MOVE to scripts/
├── test_query.php               → MOVE to scripts/
├── test_seeder.php              → MOVE to scripts/
├── e2e_seed.php                 → MOVE to scripts/
├── e2e_verify.php               → MOVE to scripts/
├── output.json                  → DELETE
├── login.json                   → DELETE (contains credentials?)
└── db_schema.json               → DELETE
```

---

*This document is the canonical memory for all future AI-assisted development sessions on this project. Always read this file at the start of every new session.*
