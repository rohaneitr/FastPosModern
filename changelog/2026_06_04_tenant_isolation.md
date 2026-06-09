# Changelog: 2026-06-04 Security Updates

## Added
- **Global Tenant Macro**: Added a `tenant()` macro to `Illuminate\Database\Query\Builder` in `AppServiceProvider`. This ensures a unified, bug-resistant way to isolate tenant data when using `DB::table()`.
- **Subdomain Mismatch Prevention**: Added a check in `AuthController.php` to prevent authenticated users from logging into the UI of a different tenant's subdomain.

## Changed
- **TenantModel Scope Hardening**: Hardened the global `tenant` scope. If an authenticated user lacks a `business_id` (e.g., bypassing RBAC), it defaults to `-1` to explicitly block cross-tenant leakage.
- **Refactored Raw Queries**: Safely swapped out manual `$businessId` matching for the `tenant()` macro in `HRController`, `SalesController`, `PurchaseController`, and `ReportController`.
