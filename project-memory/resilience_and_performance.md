# FastPOS Project Memory - Resilience & Performance Optimization Phase

## Latest Updates: 2026-06-04
Initiated the "Resilience & Performance Optimization Phase" focusing on backend API security and data caching layers. No UI changes were implemented.

### 1. API Rate Limiting (DDoS & Brute-Force Prevention)
- **Issue**: Missing protection on core authentication and high-frequency sync/checkout endpoints.
- **Resolution**:
  - Applied Laravel's native `throttle:auth` middleware to the `AuthController@login` route (both web and mobile) to prevent brute-force attacks.
  - Applied a strict `throttle:60,1` API throttle to critical POS transaction endpoints, including `/checkout`, `/sales`, and the mobile offline sync endpoint (`/sync/push`), to prevent API abuse and DDoS attacks.

### 2. Redis Caching Layer for Heavy Operations
- **Issue**: `ReportController::dashboardKPIs` and `PublicTenantController::resolveSubdomain` executed heavy and repetitive database queries leading to poor performance under load.
- **Resolution**: 
  - Wrapped `dashboardKPIs` logic inside a `Cache::store('redis')->remember` block with a 15-minute TTL, keyed uniquely by `business_id`.
  - Wrapped `resolveSubdomain` inside a `Cache::store('redis')->remember` block with a 15-minute TTL, keyed uniquely by `subdomain`.

### 3. Automated Cache Invalidation
- **Issue**: Real-time reporting accuracy could suffer if caches were not cleared after meaningful data changes (like a new sale).
- **Resolution**: 
  - Added cache invalidation (`Cache::store('redis')->forget("dashboard_kpis_business_{$businessId}")`) following successful database commits in `SalesController@store`, `SalesController@convertToSale`, `TransactionController@checkout`, and `TransactionController@syncPush`.
