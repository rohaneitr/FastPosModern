# PHASE 1 — REPOSITORY STRUCTURE EXTRACTION

## 1. Complete Folder & 2. Complete File Tree

```text
├── .gitattributes
├── 0000.csv
├── 0000.parquet
├── analyze.py
├── changelog/  (Purpose: Root Config | Owner: DevOps | Dependencies: None | Risk: Low)
│   └── 2026_06_04_tenant_isolation.md
├── client/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   ├── .env.local
│   ├── .gitignore
│   ├── AGENTS.md
│   ├── CLAUDE.md
│   ├── README.md
│   ├── eslint.config.mjs
│   ├── lint_output.txt
│   ├── next-env.d.ts
│   ├── next.config.mjs
│   ├── package-lock.json
│   ├── package.json
│   ├── postcss.config.mjs
│   ├── public/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   ├── file.svg
│   │   ├── globe.svg
│   │   ├── icons/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   ├── icon-192x192.png
│   │   │   └── icon-512x512.png
│   │   ├── manifest.json
│   │   ├── next.svg
│   │   ├── vercel.svg
│   │   └── window.svg
│   ├── src/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   ├── app/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   ├── (dashboards)/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   ├── layout.tsx
│   │   │   │   └── superadmin/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       ├── approvals/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       ├── audit-logs/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       ├── email-logs/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       ├── layout.tsx
│   │   │   │       ├── licenses/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       ├── monitoring/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       ├── page.tsx
│   │   │   │       ├── profile/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       ├── settings/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       ├── subscription-requests/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       ├── subscriptions/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       ├── support/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       │   └── page.tsx
│   │   │   │       └── tenants/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │           ├── [id]/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │           │   └── features/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │           │       └── page.tsx
│   │   │   │           └── page.tsx
│   │   │   ├── [domain]/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   ├── (dashboards)/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   ├── business/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   ├── accounting/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── billing/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   ├── checkout/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   ├── expired/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── categories/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── contacts/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   ├── [id]/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── ledger/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │       └── page.tsx
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── customers/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── due/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │       └── page.tsx
│   │   │   │   │   │   ├── hr/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   ├── employees/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   ├── page.tsx
│   │   │   │   │   │   │   └── payroll/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │       └── page.tsx
│   │   │   │   │   │   ├── inventory/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   ├── page.tsx
│   │   │   │   │   │   │   └── transfers/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │       └── page.tsx
│   │   │   │   │   │   ├── layout.tsx
│   │   │   │   │   │   ├── page.tsx
│   │   │   │   │   │   ├── pharmacy/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── products/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   ├── import/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── profile/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── purchases/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   ├── [id]/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── quotations/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── reports/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── sales/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   ├── [id]/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   ├── drafts/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   ├── page.tsx
│   │   │   │   │   │   │   ├── payments/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   ├── quotations/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   ├── returns/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   └── shipments/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │       └── page.tsx
│   │   │   │   │   │   ├── settings/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   ├── api-keys/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   ├── branding/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   ├── devices/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   ├── invoice-designer/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── support/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── users/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   └── warranty/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │       └── page.tsx
│   │   │   │   │   ├── settings/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   ├── devices/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   ├── profile/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   │   └── page.tsx
│   │   │   │   │   │   └── security/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │       └── page.tsx
│   │   │   │   │   ├── superadmin/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   └── backups/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │       └── page.tsx
│   │   │   │   │   ├── tenant/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │   └── billing/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   │       └── page.tsx
│   │   │   │   │   └── user/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │       ├── layout.tsx
│   │   │   │   │       ├── page.tsx
│   │   │   │   │       ├── pos/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │       │   ├── layout.tsx
│   │   │   │   │       │   └── page.tsx
│   │   │   │   │       └── profile/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │           └── page.tsx
│   │   │   │   ├── activation/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   └── page.tsx
│   │   │   │   ├── forgot-password/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   └── page.tsx
│   │   │   │   ├── layout.tsx
│   │   │   │   ├── login/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   └── page.tsx
│   │   │   │   ├── page.tsx
│   │   │   │   ├── receipt/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │   └── [uuid]/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   │       └── page.tsx
│   │   │   │   └── reset-password/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │       └── page.tsx
│   │   │   ├── accept-invite/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   └── page.tsx
│   │   │   ├── favicon.ico
│   │   │   ├── globals.css
│   │   │   ├── layout.tsx
│   │   │   ├── maintenance/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   └── page.tsx
│   │   │   ├── page.tsx
│   │   │   ├── providers.tsx
│   │   │   ├── register/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   └── page.tsx
│   │   │   ├── superadmin-login/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   │   └── page.tsx
│   │   │   └── ~offline/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │       └── page.tsx
│   │   ├── components/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   ├── AnnouncementBanner.tsx
│   │   │   ├── BulkMessageModal.tsx
│   │   │   ├── ClockWidget.tsx
│   │   │   ├── ImpersonationGuard.tsx
│   │   │   ├── LanguageSwitcher.tsx
│   │   │   ├── NotificationBell.tsx
│   │   │   └── profile/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │       └── ProfileSettings.tsx
│   │   ├── contexts/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   └── AuthContext.tsx
│   │   ├── hooks/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   └── usePosSounds.ts
│   │   ├── lib/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   ├── api.ts
│   │   │   ├── currency.tsx
│   │   │   └── i18n/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │       ├── bn.ts
│   │   │       ├── en.ts
│   │   │       └── index.tsx
│   │   ├── middleware.ts
│   │   ├── store/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │   │   └── useCartStore.ts
│   │   └── tests/  (Purpose: Next.js Frontend | Owner: Frontend Domain | Dependencies: React, Tailwind | Risk: Medium)
│   │       └── cart.test.ts
│   ├── tsconfig.json
│   └── tsconfig.tsbuildinfo
├── docs/  (Purpose: Documentation | Owner: Architecture | Dependencies: None | Risk: Low)
│   ├── api-map.md
│   ├── architecture-notes.md
│   ├── bug-log.md
│   ├── database-map.md
│   ├── decision-log.md
│   ├── feature-inventory.md
│   ├── migration-plan.md
│   ├── progress-log.md
│   ├── project-memory.md
│   ├── risk-register.md
│   └── testing-report.md
├── mobile/  (Purpose: React Native App | Owner: Mobile Domain | Dependencies: Expo | Risk: Medium)
│   ├── .claude/  (Purpose: React Native App | Owner: Mobile Domain | Dependencies: Expo | Risk: Medium)
│   │   └── settings.json
│   ├── .gitignore
│   ├── AGENTS.md
│   ├── App.tsx
│   ├── CLAUDE.md
│   ├── LICENSE
│   ├── app.json
│   ├── assets/  (Purpose: React Native App | Owner: Mobile Domain | Dependencies: Expo | Risk: Medium)
│   │   ├── android-icon-background.png
│   │   ├── android-icon-foreground.png
│   │   ├── android-icon-monochrome.png
│   │   ├── favicon.png
│   │   ├── icon.png
│   │   └── splash-icon.png
│   ├── index.ts
│   ├── package-lock.json
│   ├── package.json
│   ├── src/  (Purpose: React Native App | Owner: Mobile Domain | Dependencies: Expo | Risk: Medium)
│   │   ├── lib/  (Purpose: React Native App | Owner: Mobile Domain | Dependencies: Expo | Risk: Medium)
│   │   │   └── SyncManager.ts
│   │   └── screens/  (Purpose: React Native App | Owner: Mobile Domain | Dependencies: Expo | Risk: Medium)
│   │       ├── LoginScreen.tsx
│   │       └── POSScreen.tsx
│   └── tsconfig.json
├── project-memory/  (Purpose: Project Context | Owner: CTO | Dependencies: None | Risk: Low)
│   ├── async_architecture.md
│   ├── automated_qa_baseline.md
│   ├── data_portability.md
│   ├── resilience_and_performance.md
│   └── security_updates.md
└── server/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    ├── .dockerignore
    ├── .editorconfig
    ├── .env
    ├── .env.example
    ├── .gitattributes
    ├── .gitignore
    ├── .npmrc
    ├── .phpunit.result.cache
    ├── Dockerfile
    ├── README.md
    ├── app/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   ├── Console/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   └── Commands/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       ├── BackupDatabase.php
    │   │       ├── CheckSubscriptions.php
    │   │       ├── CleanupOrphanTenantsCommand.php
    │   │       ├── EvaluateGracePeriods.php
    │   │       ├── GenerateLicenseKeypair.php
    │   │       ├── ImportMedicinesCommand.php
    │   │       ├── MigrateLegacyTenants.php
    │   │       ├── ValidateParity.php
    │   │       └── WipeDummiesCommand.php
    │   ├── Domain/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── Accounting/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   └── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       ├── ExpenseCategoryController.php
    │   │   │       └── ExpenseController.php
    │   │   ├── CRM/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   ├── ContactController.php
    │   │   │   │   └── LedgerController.php
    │   │   │   └── Models/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       └── Contact.php
    │   │   ├── Catalog/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   ├── BrandController.php
    │   │   │   │   ├── CategoryController.php
    │   │   │   │   └── ProductController.php
    │   │   │   └── Models/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       ├── Brand.php
    │   │   │       ├── Category.php
    │   │   │       ├── Product.php
    │   │   │       ├── Unit.php
    │   │   │       └── Variation.php
    │   │   ├── HR/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   └── HRController.php
    │   │   │   └── Models/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       ├── Attendance.php
    │   │   │       ├── EmployeeProfile.php
    │   │   │       └── Payroll.php
    │   │   ├── IAM/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   ├── AuthController.php
    │   │   │   │   ├── DeviceController.php
    │   │   │   │   ├── ImpersonationController.php
    │   │   │   │   ├── InvitationController.php
    │   │   │   │   ├── PasswordResetController.php
    │   │   │   │   ├── ProfileController.php
    │   │   │   │   ├── RoleController.php
    │   │   │   │   ├── TwoFactorController.php
    │   │   │   │   └── UserController.php
    │   │   │   └── Models/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       └── User.php
    │   │   ├── Inventory/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   ├── BrandController.php
    │   │   │   │   ├── CategoryController.php
    │   │   │   │   ├── InventoryController.php
    │   │   │   │   ├── ProductController.php
    │   │   │   │   ├── StockTransferController.php
    │   │   │   │   └── UnitController.php
    │   │   │   ├── Models/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   ├── Brand.php
    │   │   │   │   ├── Category.php
    │   │   │   │   ├── Product.php
    │   │   │   │   ├── StockTransfer.php
    │   │   │   │   ├── StockTransferItem.php
    │   │   │   │   └── Unit.php
    │   │   │   └── Requests/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       ├── StoreBrandRequest.php
    │   │   │       ├── StoreCategoryRequest.php
    │   │   │       ├── StoreProductRequest.php
    │   │   │       ├── StoreUnitRequest.php
    │   │   │       └── UpdateProductRequest.php
    │   │   ├── Purchases/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   ├── PurchaseController.php
    │   │   │   │   └── SupplierController.php
    │   │   │   ├── Models/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   ├── Contact.php
    │   │   │   │   ├── Purchase.php
    │   │   │   │   └── PurchaseLine.php
    │   │   │   └── Requests/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       ├── StorePurchaseRequest.php
    │   │   │       └── StoreSupplierRequest.php
    │   │   ├── Reporting/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   └── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       ├── InvoiceController.php
    │   │   │       └── ReportController.php
    │   │   ├── Sales/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   ├── AdvancedSalesController.php
    │   │   │   │   ├── RMAController.php
    │   │   │   │   ├── RegisterController.php
    │   │   │   │   ├── SalesController.php
    │   │   │   │   └── TransactionController.php
    │   │   │   └── Services/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       └── InvoicePdfService.php
    │   │   ├── SuperAdmin/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   └── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       └── DashboardOverviewController.php
    │   │   ├── Support/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   │   └── TicketController.php
    │   │   │   └── Models/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       ├── SupportTicket.php
    │   │   │       └── TicketReply.php
    │   │   └── Tenant/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       ├── Actions/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       │   └── ProvisionSubscriptionAction.php
    │   │       ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       │   ├── AnnouncementController.php
    │   │       │   ├── ApiController.php
    │   │       │   ├── AuditLogController.php
    │   │       │   ├── BackupController.php
    │   │       │   ├── BusinessSettingsController.php
    │   │       │   ├── DeviceHeartbeatController.php
    │   │       │   ├── EmailLogController.php
    │   │       │   ├── ExternalApiController.php
    │   │       │   ├── ImportController.php
    │   │       │   ├── InvoiceLayoutController.php
    │   │       │   ├── LicenseController.php
    │   │       │   ├── LocationController.php
    │   │       │   ├── NotificationController.php
    │   │       │   ├── PaymentController.php
    │   │       │   ├── PrinterController.php
    │   │       │   ├── PublicTenantController.php
    │   │       │   ├── RegistrationController.php
    │   │       │   ├── SettingsController.php
    │   │       │   ├── SubscriptionController.php
    │   │       │   ├── SuperAdminSettingsController.php
    │   │       │   ├── SuperAdminTelemetryController.php
    │   │       │   ├── SuperadminController.php
    │   │       │   ├── TaxRateController.php
    │   │       │   ├── TenantApprovalController.php
    │   │       │   └── TenantFeaturesController.php
    │   │       ├── Models/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       │   ├── AuditLog.php
    │   │       │   ├── Business.php
    │   │       │   ├── DeviceActivation.php
    │   │       │   ├── EmailLog.php
    │   │       │   ├── License.php
    │   │       │   ├── Plan.php
    │   │       │   ├── Subscription.php
    │   │       │   ├── SubscriptionRequest.php
    │   │       │   ├── TenantModel.php
    │   │       │   └── TenantRequest.php
    │   │       ├── Scopes/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       │   └── BusinessScope.php
    │   │       ├── Services/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       │   ├── AuditLogger.php
    │   │       │   ├── LicenseKeyService.php
    │   │       │   └── TenantDeletionService.php
    │   │       └── Traits/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │           └── BelongsToBusiness.php
    │   ├── Http/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── Controllers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── Controller.php
    │   │   │   └── HealthController.php
    │   │   ├── Middleware/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── ActivityLogger.php
    │   │   │   ├── CheckModuleAccess.php
    │   │   │   ├── CheckSubscription.php
    │   │   │   ├── EnsureLicenseIsActive.php
    │   │   │   ├── IdleTimeoutMiddleware.php
    │   │   │   └── SaaSMaintenanceMode.php
    │   │   └── Requests/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       └── ImportCsvRequest.php
    │   ├── Jobs/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── ExportSalesCsvJob.php
    │   │   ├── ImportContactsCsvJob.php
    │   │   ├── ImportProductsCsvJob.php
    │   │   ├── ProcessStripeWebhookJob.php
    │   │   ├── SendBulkMessageJob.php
    │   │   └── SendInvoiceEmailJob.php
    │   ├── Listeners/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── LogFailedEmail.php
    │   │   └── LogSentEmail.php
    │   ├── Mail/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── GenericNotificationMail.php
    │   │   ├── PasswordResetMail.php
    │   │   ├── TenantApprovedMail.php
    │   │   ├── TenantRejectedMail.php
    │   │   └── TenantWelcomeMail.php
    │   ├── Notifications/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── InvoiceGenerated.php
    │   │   └── SmsChannel.php
    │   ├── Providers/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── AppServiceProvider.php
    │   │   └── HorizonServiceProvider.php
    │   └── Services/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │       └── SmsGatewayService.php
    ├── artisan
    ├── bootstrap/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   ├── app.php
    │   ├── cache/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── .gitignore
    │   │   ├── packages.php
    │   │   └── services.php
    │   └── providers.php
    ├── cleanup_script.php
    ├── composer.json
    ├── composer.lock
    ├── composer.phar
    ├── config/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   ├── app.php
    │   ├── auth.php
    │   ├── backup.php
    │   ├── cache.php
    │   ├── cors.php
    │   ├── database.php
    │   ├── filesystems.php
    │   ├── horizon.php
    │   ├── logging.php
    │   ├── mail.php
    │   ├── permission.php
    │   ├── queue.php
    │   ├── sanctum.php
    │   ├── services.php
    │   └── session.php
    ├── database/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   ├── .gitignore
    │   ├── database.sqlite
    │   ├── factories/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── BusinessFactory.php
    │   │   └── UserFactory.php
    │   ├── migrations/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── 0001_01_01_000000_create_users_table.php
    │   │   ├── 0001_01_01_000001_create_businesses_table.php
    │   │   ├── 0001_01_01_000001_create_cache_table.php
    │   │   ├── 0001_01_01_000002_add_business_id_to_users_table.php
    │   │   ├── 0001_01_01_000002_create_jobs_table.php
    │   │   ├── 2026_06_02_075151_create_permission_tables.php
    │   │   ├── 2026_06_02_075423_create_personal_access_tokens_table.php
    │   │   ├── 2026_06_02_140000_create_catalog_tables.php
    │   │   ├── 2026_06_02_140500_create_inventory_and_sales_tables.php
    │   │   ├── 2026_06_02_141000_create_crm_tables.php
    │   │   ├── 2026_06_02_141500_add_contact_id_to_transactions.php
    │   │   ├── 2026_06_02_142000_create_accounting_tables.php
    │   │   ├── 2026_06_02_142500_create_hr_tables.php
    │   │   ├── 2026_06_02_143000_create_settings_and_master_tables.php
    │   │   ├── 2026_06_02_144000_add_advanced_features_to_catalog.php
    │   │   ├── 2026_06_02_145000_add_advanced_sales_fields.php
    │   │   ├── 2026_06_02_160700_add_contact_info_to_users.php
    │   │   ├── 2026_06_02_162000_add_advanced_tracking_to_products.php
    │   │   ├── 2026_06_02_162500_add_categories_to_products.php
    │   │   ├── 2026_06_02_164000_add_imei_tracking_to_products.php
    │   │   ├── 2026_06_02_165000_add_brand_to_products.php
    │   │   ├── 2026_06_03_080000_add_i18n_and_currency_tables.php
    │   │   ├── 2026_06_03_103000_create_user_activities_and_profile_fields.php
    │   │   ├── 2026_06_03_120000_add_two_factor_columns_to_users_table.php
    │   │   ├── 2026_06_03_180000_create_saas_billing_tables.php
    │   │   ├── 2026_06_03_190000_create_stock_adjustments_table.php
    │   │   ├── 2026_06_03_210000_add_stripe_columns.php
    │   │   ├── 2026_06_03_220000_add_branding_and_discount_columns.php
    │   │   ├── 2026_06_03_230000_add_subdomain_to_businesses.php
    │   │   ├── 2026_06_04_000000_enhance_ledger_tables.php
    │   │   ├── 2026_06_04_160000_add_hybrid_saas_columns_to_plans.php
    │   │   ├── 2026_06_04_160500_create_device_activations_table.php
    │   │   ├── 2026_06_04_182000_create_licenses_table.php
    │   │   ├── 2026_06_04_190000_create_payments_table.php
    │   │   ├── 2026_06_04_194000_add_preferred_currency_to_users.php
    │   │   ├── 2026_06_05_000000_create_subscription_payments_table.php
    │   │   ├── 2026_06_05_100000_create_tenant_requests_table.php
    │   │   ├── 2026_06_05_102000_widen_license_key_to_text.php
    │   │   ├── 2026_06_05_104000_add_heartbeat_to_device_activations.php
    │   │   ├── 2026_06_05_110000_add_modules_and_audit_logs.php
    │   │   ├── 2026_06_05_115216_add_serial_and_warranty_to_products_table.php
    │   │   ├── 2026_06_05_115221_create_product_serials_table.php
    │   │   ├── 2026_06_05_120000_add_email_to_tenant_requests.php
    │   │   ├── 2026_06_05_123313_add_performance_indexes.php
    │   │   ├── 2026_06_05_130000_create_email_logs_table.php
    │   │   ├── 2026_06_05_180000_create_cash_registers_table.php
    │   │   ├── 2026_06_05_200000_create_subscription_requests_table.php
    │   │   ├── 2026_06_05_999999_add_features_to_plans.php
    │   │   ├── 2026_06_06_073357_create_hr_v2_tables.php
    │   │   ├── 2026_06_06_074400_add_communication_settings_to_businesses.php
    │   │   ├── 2026_06_06_093318_add_payment_tracking_to_transactions_table.php
    │   │   ├── 2026_06_06_100000_create_rma_tickets_table.php
    │   │   ├── 2026_06_06_110000_add_active_modules_to_businesses_table.php
    │   │   ├── 2026_06_06_120000_create_stock_transfers_tables.php
    │   │   ├── 2026_06_06_120500_update_product_serials_for_transfers.php
    │   │   ├── 2026_06_06_122150_create_user_devices_table.php
    │   │   ├── 2026_06_06_122151_create_support_tickets_tables.php
    │   │   ├── 2026_06_06_130000_add_payment_method_to_expenses.php
    │   │   ├── 2026_06_06_140000_add_generic_name_to_products_table.php
    │   │   ├── 2026_06_06_140000_add_session_type_to_user_devices_table.php
    │   │   ├── 2026_06_06_140500_add_billing_fields_to_businesses_table.php
    │   │   ├── 2026_06_06_180000_add_branding_and_theme_to_businesses_and_users_table.php
    │   │   ├── 2026_06_06_180500_create_global_settings_table.php
    │   │   ├── 2026_06_06_190000_create_announcements_table.php
    │   │   ├── 2026_06_06_221200_fix_plan_columns_to_json.php
    │   │   ├── 2026_06_07_091600_add_pharmacy_fields_to_products_table.php
    │   │   ├── 2026_06_07_120732_fix_device_activations_unique_constraint.php
    │   │   ├── 2026_06_07_161913_add_status_and_license_key_to_businesses_table.php
    │   │   ├── 2026_06_07_232600_enhance_inventory_catalog_tables.php
    │   │   └── 2026_06_08_000000_create_core_inventory_management_tables.php
    │   └── seeders/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │       ├── CurrencySeeder.php
    │       ├── DatabaseSeeder.php
    │       ├── FastTenantSeeder.php
    │       └── RolesAndPermissionsSeeder.php
    ├── docker-compose.yml
    ├── dump_log.txt
    ├── full_test_log.txt
    ├── full_test_log2.txt
    ├── full_test_log3.txt
    ├── full_test_log4.txt
    ├── full_test_log5.txt
    ├── full_test_log6.txt
    ├── get_log.php
    ├── hr_log.txt
    ├── laravel.log
    ├── package.json
    ├── phpunit.xml
    ├── public/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   ├── .htaccess
    │   ├── favicon.ico
    │   ├── index.php
    │   ├── robots.txt
    │   └── storage
    ├── resources/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   ├── css/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   └── app.css
    │   ├── js/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   └── app.js
    │   └── views/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │       ├── emails/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │       │   ├── password-reset.blade.php
    │       │   ├── tenant-approved.blade.php
    │       │   ├── tenant-rejected.blade.php
    │       │   └── tenant-welcome.blade.php
    │       ├── invoices/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │       │   └── receipt.blade.php
    │       ├── pdf/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │       │   └── invoice.blade.php
    │       └── welcome.blade.php
    ├── routes/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   ├── api.php
    │   ├── console.php
    │   └── web.php
    ├── storage/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   ├── app/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── .gitignore
    │   │   ├── private/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   └── .gitignore
    │   │   └── public/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       └── .gitignore
    │   ├── framework/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── .gitignore
    │   │   ├── cache/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   ├── .gitignore
    │   │   │   └── data/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │       └── .gitignore
    │   │   ├── sessions/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   └── .gitignore
    │   │   ├── testing/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   │   └── .gitignore
    │   │   └── views/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │       ├── .gitignore
    │   │       ├── 19d1ca22cd8db231f88e0685e9c3a20e.php
    │   │       ├── 275c7c02e2528e6029079c885e2d2418.php
    │   │       ├── 860ee13fc77d7c3e870a0386b5e6649a.php
    │   │       ├── 9530b07eb2ab2b97c5ae138f0bfc5952.php
    │   │       └── dd310000961f2d208873a737c27d849a.php
    │   └── logs/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │       ├── .gitignore
    │       └── laravel.log
    ├── test_api.php
    ├── test_cache.php
    ├── test_err.log
    ├── test_err2.log
    ├── test_login.php
    ├── test_plan.php
    ├── tests/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   ├── Feature/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │   │   ├── AccountingTest.php
    │   │   ├── AuthTest.php
    │   │   ├── CRMTest.php
    │   │   ├── CheckoutTest.php
    │   │   ├── CoreIntegrityTest.php
    │   │   ├── ExampleTest.php
    │   │   ├── FinancialIntegrityTest.php
    │   │   ├── HRTest.php
    │   │   ├── InventoryConcurrencyTest.php
    │   │   ├── InventoryTest.php
    │   │   ├── MathIntegrityTest.php
    │   │   ├── RBACTest.php
    │   │   ├── RaceConditionTest.php
    │   │   ├── ReportingTest.php
    │   │   ├── SecurityTest.php
    │   │   ├── SubscriptionMiddlewareTest.php
    │   │   ├── SuperadminTest.php
    │   │   └── TenantIsolationTest.php
    │   ├── TestCase.php
    │   └── Unit/  (Purpose: Laravel Backend API | Owner: Backend Domain | Dependencies: PHP, SQLite | Risk: High)
    │       └── ExampleTest.php
    └── vite.config.js
```

## 3. Total File Counts
Total Files: **486**

## 4. File Type Counts
| Extension | Count |
|---|---|
| .php | 277 |
| .tsx | 91 |
| no_extension | 26 |
| .md | 23 |
| .json | 11 |
| .txt | 10 |
| .ts | 10 |
| .png | 8 |
| .svg | 5 |
| .log | 4 |
| .mjs | 3 |
| .ico | 2 |
| .css | 2 |
| .js | 2 |
| .csv | 1 |
| .parquet | 1 |
| .py | 1 |
| .local | 1 |
| .tsbuildinfo | 1 |
| .example | 1 |
| .cache | 1 |
| .lock | 1 |
| .phar | 1 |
| .yml | 1 |
| .xml | 1 |
| .sqlite | 1 |


## 5. Largest Files
| Size (Bytes) | File |
|---|---|
| 3,843,823 | `0000.csv` |
| 3,561,371 | `server\composer.phar` |
| 1,136,202 | `0000.parquet` |
| 806,810 | `client\package-lock.json` |
| 691,886 | `server\storage\logs\laravel.log` |
| 393,493 | `mobile\assets\icon.png` |
| 344,064 | `server\database\database.sqlite` |
| 339,562 | `server\composer.lock` |
| 316,216 | `server\laravel.log` |
| 241,829 | `mobile\package-lock.json` |


## 6. Dead Files
*(Heuristic: Files not explicitly imported via JS/TS/PHP use statements. False positives expected for frameworks.)*
Total dead files found: 336
Sample of dead files:
- `.gitattributes`
- `0000.csv`
- `0000.parquet`
- `analyze.py`
- `changelog\2026_06_04_tenant_isolation.md`
- `client\.env.local`
- `client\.gitignore`
- `client\AGENTS.md`
- `client\CLAUDE.md`
- `client\eslint.config.mjs`
- `client\lint_output.txt`
- `client\next-env.d.ts`
- `client\next.config.mjs`
- `client\postcss.config.mjs`
- `client\README.md`
- `client\tsconfig.tsbuildinfo`
- `client\public\file.svg`
- `client\public\globe.svg`
- `client\public\vercel.svg`
- `client\public\window.svg`


## 7. Duplicate Files
Total duplicate files: 17
- Hash `336cc4fb...`
  - `client\CLAUDE.md`
  - `mobile\CLAUDE.md`
- Hash `63ef318d...`
  - `client\public\icons\icon-192x192.png`
  - `client\public\icons\icon-512x512.png`
- Hash `e3b0c442...`
  - `docs\api-map.md`
  - `docs\bug-log.md`
  - `docs\progress-log.md`
  - `docs\testing-report.md`
  - `server\public\favicon.ico`
- Hash `240a3e0d...`
  - `server\bootstrap\cache\.gitignore`
  - `server\storage\app\private\.gitignore`
  - `server\storage\app\public\.gitignore`
  - `server\storage\framework\cache\data\.gitignore`
  - `server\storage\framework\sessions\.gitignore`
  - `server\storage\framework\testing\.gitignore`
  - `server\storage\framework\views\.gitignore`
  - `server\storage\logs\.gitignore`


## 8. Unused Files
Unused files heavily overlap with the dead files listed above. Media files not explicitly referenced in code:
- `client\public\file.svg`
- `client\public\globe.svg`
- `client\public\next.svg`
- `client\public\vercel.svg`
- `client\public\window.svg`


## 9. Circular Dependencies
Static analysis detected **0** explicit import cycles at the module level. Laravel's IoC container and Next.js lazy loading effectively mitigate hard file-level circular imports in this architecture.