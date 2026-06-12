# FastPOS Modern — Security & RBAC Memory
> Last Updated: 2026-06-12

## Current RBAC State

### Roles (5 defined)
| Role | Key Permissions |
|---|---|
| SuperAdmin | platform.manage + ALL |
| BusinessAdmin | tenant.manage, users.*, products.manage, inventory.manage, sales.manage, reports.manage, pos.access |
| Cashier | pos.access, sales.manage |
| InventoryManager | products.manage, inventory.manage |
| Accountant | reports.manage, sales.manage |

### KNOWN FLAWS (Do not fix yet — audit only)
1. `middleware('role:BusinessAdmin')` hardcoded in route files → Prevents custom roles
2. `DELETE /contacts` → gated by `tenant.manage` (WRONG — should be `contacts.delete`)
3. `DELETE /suppliers` → gated by `products.manage` (WRONG — should be `suppliers.delete`)
4. `DELETE /expenses` → gated by `reports.manage` (WRONG — should be `expenses.delete`)
5. `DELETE /purchases` → gated by `products.manage` (WRONG — should be `purchases.delete`)

### Completed Security Features
- Tenant subdomain validation on login (prevents cross-tenant session desync)
- `TenantModel` global scope forces `business_id = -1` when user has no business_id (prevents spillage)
- `VerifyHardwareHash` middleware locks POS to registered device fingerprint
- `RbacShadowLogger` non-blocking forensic audit on all permission-protected routes
- Immutable `audit_logs` with SHA-256 checksums
- `impersonator_id` tracked in audit_logs for SuperAdmin impersonation
- Rate limiting: `throttle:auth` on login, `throttle:60,1` on checkout/sync endpoints
- Redis cache: Dashboard KPIs (15-min TTL, per `business_id` key)
- Redis cache: Subdomain resolution (15-min TTL, per subdomain key)
- Cache invalidation on new sale/transaction/sync

### RBAC Refactoring Plan (Phase 1 — Next Priority)
1. Add granular permissions: `contacts.delete`, `suppliers.delete`, `expenses.delete`, `purchases.delete`, `products.delete`, `locations.manage`, `users.invite`
2. Add `Manager` role with mid-level permissions
3. Replace ALL `middleware('role:X')` with `middleware('permission:X')` 
4. Run `php artisan db:seed --class=RolesAndPermissionsSeeder`
