# SUPER ADMIN MASTER ROADMAP

This matrix maps out the complete surface area of the FastPOS Super Admin ecosystem, merging Next.js frontend routes with Laravel backend controllers.

## 1. Authentication, Security & RBAC
- [ ] SuperAdmin Login & Session Management - `[Untested]`
- [ ] Platform Route Security (Middleware `auth:sanctum`, `role:SuperAdmin`) - `[Incomplete]`
- [ ] God-Mode Impersonation (Inject Custom Claim, Audit Trail tracking) - `[Needs Architecture Upgrade]`
- [ ] API Rate Limiting (SaaS Plan-based middleware enforcement) - `[Needs Architecture Upgrade]`

## 2. Tenant / Business Provisioning
- [x] Create New Tenant (with queued Mailable Welcome Email)
- [x] Suspend / Activate Tenant Status
- [x] Delete Tenant (Guarded `DB::transaction`, Soft Delete cascading)
- [ ] Tenant Approval Workflow (Approve/Reject `/tenant-requests`) - `[Untested]`
- [ ] Search & Pagination (Migrate to `pg_trgm` Trigram indexing) - `[Needs Architecture Upgrade]`

## 3. SaaS Subscriptions & Plans
- [x] Create Subscription Plan (Dynamic `enabled_modules` JSON mapping)
- [x] Edit Subscription Plan
- [x] Delete Subscription Plan (Guarded against active subscribers)
- [ ] Renew Subscription (+1 Month / +1 Year) - `[Untested]`
- [ ] Override Subscription Status - `[Untested]`

## 4. Hardware Licensing (Native/Offline POS)
- [ ] Generate New License Key (`/licenses/generate`) - `[Untested]`
- [ ] List Active Licenses (`/licenses`) - `[Untested]`
- [ ] Suspend / Revoke License (`toggleLicenseStatus`) - `[Untested]`

## 5. Global Platform Configurations
- [x] Update System Configs (Timezone, Currency, SAAS Name, Cache purge)
- [ ] File Upload Architecture (Refactor `saas_logo`, `favicon` to `Storage::disk('s3')` with `local` fallback) - `[Needs Architecture Upgrade]`
- [ ] SMTP Handshake Verification (Synchronous `/test-smtp` endpoint) - `[Needs Architecture Upgrade]`

## 6. Disaster Recovery & System Maintenance
- [ ] View Backups List (`/backups`) - `[Untested]`
- [ ] Trigger Manual Database Backup (Async `pg_dump` queue) - `[Untested]`
- [ ] Backup Upload & Restore (`RestoreDatabaseJob`) - `[Untested]`
- [ ] System Maintenance Mode Toggle (With bypass secret) - `[Untested]`
- [ ] Broadcast Global Announcements (`/announcements`) - `[Untested]`

## 7. Observability, Logs & CRM
- [ ] SuperAdmin Dashboard KPI Stats (Revenue, MRR, Total Tenants) - `[Untested]`
- [ ] Platform Monitoring Engine (Active Devices, Live connections) - `[Untested]`
- [ ] Audit Logs Viewer (`/audit-logs`) - `[Untested]`
- [ ] Email Dispatch Logs Viewer (`/email-logs`) - `[Untested]`
- [ ] Support Ticket Helpdesk (`/tickets` list, reply, change status) - `[Untested]`
