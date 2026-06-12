# FastPOS Modern - Global Frontend UI & Wiring Audit Report

**Date:** 2026-06-10
**Type:** Static Analysis & Dependency Map
**Status:** ACTION REQUIRED

The backend architecture is currently rock-solid, but the Next.js frontend has several significant technical debt components, including placeholder UIs, disconnected API calls, and incomplete module tabs. Below is the brutal, prioritized reality of the UI layer.

---

## 1. CRITICAL PRIORITY (Broken API Contracts)
These features appear completely functional in the UI but will 100% fail in production because the backend endpoints do not exist.

### Module: HR & Employee Management
- **File:** `client/src/app/[domain]/(dashboards)/business/hr/employees/page.tsx`
- **Missing API Connection:** The **"Invite Staff"** modal submits to `POST /business/invites`. **This API endpoint does not exist** anywhere in the Laravel backend. 
- **Impact:** Tenants cannot invite new cashiers or managers to their business.

### Module: Bulk Messaging
- **File:** `client/src/components/BulkMessageModal.tsx`
- **Missing API Connection:** The form submits to `POST /messages/bulk`. **This API endpoint does not exist.**
- **Impact:** Staff communication feature will instantly fail with a 404 or 500 error.

---

## 2. MODERATE PRIORITY (Dummy UI & Missing Components)
These are structural tabs and buttons that visually exist but lack actual React implementation or historical data fetching.

### Module: Financial Reports
- **File:** `client/src/app/[domain]/(dashboards)/business/reports/page.tsx`
- **Dummy Tab 1:** "Sales Summary" is a hardcoded empty `div` that reads: *"Sales Summary Report Generation under construction."*
- **Dummy Tab 2:** "Inventory Valuation" is a hardcoded empty `div` that reads: *"Inventory Valuation Report Generation under construction."*
- **Orphan Action:** The **"Export PDF"** button does not export anything; it simply triggers `toast.error('Server-side PDF Export coming in next phase.');`.

### Module: Inventory Management
- **File:** `client/src/app/[domain]/(dashboards)/business/inventory/page.tsx`
- **Dummy Tab 1:** The **"Stock Adjustments"** tab does not show an adjustment history. It simply re-renders the generic `<StockTable />` component with the search bar hidden.
- **Dummy Tab 2:** The **"Stock Transfers"** tab does the exact same thing. It is a visual shell and does not list past warehouse transfers.

---

## 3. LOW PRIORITY (Orphan Buttons & Empty Handlers)
These elements are visible in grids or tables but cannot be clicked, or they perform no action.

### Module: Sales Hub
- **File:** `client/src/app/[domain]/(dashboards)/business/sales/page.tsx`
- **Orphan Button 1:** The **"Convert"** button (for Quotations) has no `onClick` handler. It is purely presentational: `<button className="text-primary hover:text-blue-400 font-medium text-xs px-2 py-1">Convert</button>`
- **Orphan Button 2:** The **"View"** button (for final sales) has no `onClick` handler. It is purely presentational: `<button className="text-text-muted hover:text-white font-medium text-xs px-2 py-1">View</button>`
- **Unverified Routes:** The quick-links for **Quotations, Drafts, Returns, Shipments,** and **Payments** navigate to sub-directories in Next.js that may be partially implemented or completely empty shells.

---

## Summary & Recommendations
Approximately **15-20%** of the secondary business modules (HR Invites, advanced reporting, and inventory historical views) are currently dummy templates or calling phantom APIs. 

**Recommended Action Plan for Phase 8.2:**
1. Temporarily hide or disable the "Invite Staff", "Bulk Message", and "Export PDF" buttons until their backend counterparts are built.
2. Remove the empty "Sales Summary" and "Inventory Valuation" tabs from the Reports module to prevent user frustration.
3. Wire up the "View" and "Convert" buttons in the Sales Grid, or hide them if the detail/conversion pages are not yet designed.
