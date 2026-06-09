# FastPOS Project Memory - Security

## Latest Updates: 2026-06-04
We completed a crucial functional completion phase to resolve identified data leakage risks.

### 1. Hardened Tenant Scope
- **Issue**: `TenantModel` global scope previously dropped isolation constraints if a user lacked a `business_id` (e.g. Super Admin role context), leading to potential data spillage if RBAC bypassed.
- **Resolution**: Updated `TenantModel::booted()` to force isolation to `business_id = -1` when `auth()->user()->business_id` is missing. 

### 2. Subdomain Validation on Login
- **Issue**: `/login` was fully global, meaning a user from one tenant could technically authorize a session from the subdomain UI of another tenant, causing visual/data context desync.
- **Resolution**: Intercepted the Auth flow in `AuthController@login`. If a `subdomain` is passed in the payload, the backend strictly verifies it matches the resolved user's `business->subdomain`.

### 3. Query Builder Tenant Isolation Abstraction
- **Issue**: Extensive use of raw `DB::table(...)` query builders throughout the controllers (`HRController`, `SalesController`, etc.) manually appended `->where('business_id', $businessId)`. This was susceptible to human error.
- **Resolution**: Created a `->tenant($tablePrefix)` macro on the `Illuminate\Database\Query\Builder` class via `AppServiceProvider`. Refactored all domain controllers to utilize this robust pattern inherently.

## Constraints & Rules Verified
- **RBAC**: Super Admins inherently maintain global access without compromising tenant boundaries due to `role_or_permission` guards remaining untouched.
- **File Structure**: No sensitive configs (`.env`) were modified or committed. Best practices maintained.
