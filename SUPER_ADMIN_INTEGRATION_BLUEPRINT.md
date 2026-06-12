# SUPER ADMIN INTEGRATION BLUEPRINT
**Date:** June 10, 2026
**Architect:** Lead Enterprise Architect & Full-Stack Integration Expert
**Status:** Discovery & Integration Strategy

## 1. Phase 1 Completion Status (Immediate Fixes)
- ✅ **Ghost Routes Mapped:** Mapped missing API routes in `SuperAdmin/Routes/api.php` for `impersonate`, `licenses`, `monitoring`, `renewals`, and the entire `TenantApprovalController`.
- ✅ **Payload Drift Corrected:** Modified Next.js UI in `tenants/page.tsx` (Line 154) to send `subscription_status` instead of `status`, bypassing the 422 Validation Error.
- ✅ **Tenant Approvals Online:** "Pending Approvals" workflow is now wired and functional.

## 2. Phase 2: Brutally Honest Feature Inventory (Zero-Trust Scan)
A deep dive into the `app/Modules` and `database/migrations` yields the following **true** state of the FastPOS codebase:

### Core Vertical Modules (Fully Present)
1. **Core POS & Inventory** (`core`)
2. **CRM & Loyalty** (`crm`) - Includes customer tracking and store credit.
3. **HR Management** (`hr`) - Staff scheduling, payroll, and advanced permissions.
4. **Serial & IMEI Tracking** (`serial_tracking`) - Item-level tracking for electronics.
5. **Pharmacy Vertical** (`pharmacy`) - FEFO routing and batch expiry schemas.
6. **Restaurant Vertical** (`restaurant`) - Recipe-based depletion and KDS routing.
7. **Manufacturing** (`manufacturing`) - BOMs (Bill of Materials) and raw material conversion.
8. **PC/Hardware Builder** (`hardware_builder`) - Compatibility engines for assemblies.

### Tenant Boundaries & Isolation
- **Data Isolation:** All major tables (`transactions`, `users`, `products`, `ledgers`) are explicitly scoped using `business_id`. 
- **Module Provisioning:** The platform supports declarative module gating via `tenant_modules` and `active_modules` JSON arrays on the `businesses` table. Spatie permissions are dynamically synchronized when a Super Admin toggles a feature.
- **SaaS Billing Limits:** Handled by the `plans` schema (`max_users`, `stripe_price_id`) and device activations (`DeviceActivation` quota enforcement).

### Global Configuration Gaps (The Missing Pieces)
- **SMTP & SMS Gateways:** There are **NO** database schemas for global SMTP or SMS configurations. These are currently hardcoded into the `.env` file.
- **Global Branding:** The SuperAdmin UI component (`POST /superadmin/branding`) expects to save a "Global SaaS Platform Name" and "Logo", but there is NO `global_settings` or `options` table in the database to receive this data.
- **Support Tickets:** The frontend has a Support Ticket system UI, but no `tickets` or `ticket_replies` tables/controllers exist in the backend.
- **Email Logs:** No database schema exists to trap and record outbound emails (`Mailable` logging).

---

## 3. Phase 3 & 4: Integration Roadmap (Making Fake UIs Real)

To upgrade the Super Admin Dashboard from a partial facade to a 100% functional command center, we must execute the following systematic integrations:

### Step 1: Implement the "Global Settings" Engine
We cannot rely solely on `.env` modifications during runtime (as it breaks Docker/Octane deployment states).
- **Action:** Create a `global_settings` table (key-value pair).
- **Backend:** Build a `GlobalSettingsController` to handle `POST /superadmin/branding` and `POST /superadmin/system`.
- **Frontend:** Remove the `setTimeout` mocks in `settings/page.tsx` and wire it to Axios.

### Step 2: Construct the Immutable Audit Trail
- **Current State:** The `audit_logs` table *does* exist in migrations, featuring a `business_id` and immutable `created_at` timestamp. 
- **Action:** Build an `AuditLogController` inside the SuperAdmin module that reads the `audit_logs` table (bypassing `business_id` global scopes if any exist).
- **Frontend:** Connect `GET /superadmin/audit-logs` to render the cross-tenant forensics grid.

### Step 3: Implement Email Forensics (Mailable Interception)
- **Action:** Create an `email_logs` database table.
- **Backend:** Register a Laravel Event Listener on `Illuminate\Mail\Events\MessageSent` to automatically write the `to_email`, `subject`, and `mailable_class` to the database.
- **Frontend:** Connect the existing UI component to `GET /superadmin/email-logs`.

### Step 4: Drop the Support Ticket Facade (Or Build It)
- **Decision Required by CTO:** The Support Tickets UI requires significant schema design (`tickets`, `ticket_replies`, `ticket_attachments`). We must either officially build this module OR remove the frontend UI link to prevent dead-ends.

## Conclusion
The infrastructure is exceptionally modular and robust. The SaaS tenant boundaries are airtight. However, the Super Admin layer was clearly an afterthought, containing numerous UI facades built ahead of backend schemas. Proceeding with Steps 1-3 above will permanently close this gap.
