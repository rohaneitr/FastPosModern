# FastPOS Modern — Architecture & Technical Debt Memory
> Last Updated: 2026-06-12

## Architecture Pattern
- **Backend**: Modular Monolith (26 Modules under `server/app/Modules/`)
- **Frontend**: Next.js 16 App Router with Feature-Sliced organization (`src/features/`)
- **Mobile**: Expo + React Native (separate app, `mobile/`)
- **Overall Health Score**: 4.5 / 10

## Top 3 Critical Flaws (Blocking Scale)
1. **Fat Controllers** — `TransactionController` (1,226 lines), raw DB logic in controllers
2. **Hardcoded RBAC** — `role:BusinessAdmin` in route middleware prevents custom roles
3. **Incomplete Multi-tenancy** — `transaction_lines`, `purchase_lines` missing direct `business_id`

## Module Dependency Map
- Sales → depends on: Tenant, Finance
- Finance → depends on: Manufacturing, Sales  
- Procurement → depends on: Inventory, Tenant
- Reports → depends on: Finance, Sales
- Tenant → depends on: IAM, SuperAdmin
- Imports → depends on: Catalog, Tenant

## Duplicate/Overlapping Modules (Need Cleanup)
- `Clinic` vs `Clinical` → should be merged into `Clinical`
- `Reports` vs `Reporting` → should be merged into `Reporting`

## Fat Controllers (Refactoring Queue)
| Controller | Lines | Priority |
|---|---|---|
| TransactionController | 1,226 | P1 🔴 |
| SuperadminController | 776 | P1 🔴 |
| SubscriptionController | 560 | P2 🟠 |
| PurchaseController | 551 | P2 🟠 |
| DeviceHeartbeatController | 307 | P2 🟠 |

## Fat Frontend Pages (Refactoring Queue)
| Page | Lines | Priority |
|---|---|---|
| superadmin/tenants/page.tsx | 731 | P2 🟠 |
| (pos)/terminal/page.tsx | 571 | P2 🟠 |
| business/categories/page.tsx | 512 | P3 🟡 |

## Database Tables Missing business_id
- `transaction_lines` 🔴 HIGH
- `purchase_lines` 🔴 HIGH  
- `stock_adjustments` 🟠 MEDIUM
- `product_stocks` 🟠 MEDIUM
- `variations` 🟡 LOW
