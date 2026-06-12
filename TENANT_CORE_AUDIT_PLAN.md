# TENANT CORE (SAAS) AUDIT PLAN
**Date:** June 10, 2026
**Architect:** Lead Enterprise Architect & Principal DevOps Engineer
**Status:** Authorized for Next Phase

## Executive Summary
With the Super Admin integration successfully locked down, version-controlled, and verified for production readiness, the Zero-Trust audit must pivot to the **Tenant Core Engine**. The Tenant ecosystem is the beating heart of FastPOS Modern, encompassing critical financial logic, multi-register synchronization, and inventory depletion.

Based on our architectural reconnaissance of `server/app/Modules` (Inventory, Sales, Pharmacy, HR) and the Next.js `[domain]/(dashboards)` multi-tenant routing structure, the following 3 vectors represent the highest-risk critical attack surfaces.

---

## 1. Sales & Cart Calculation Integrity (Financial Vector)
**The Risk:** Client-side cart manipulation. If the Next.js POS UI sends raw item prices to the backend, a malicious cashier could intercept the payload and modify totals, apply unauthorized discounts, or bypass tax engines.
**Zero-Trust Audit Objectives:**
- **Payload Verification:** Ensure `SalesController` completely recalculates the cart total based strictly on trusted database inventory IDs, ignoring any frontend-supplied prices.
- **Ledger Balancing:** Verify that every completed transaction correctly balances debits and credits across the `Payment` and `Invoice` schemas.
- **Discount & Tax Engines:** Audit the compounding logic. Are stacked discounts (e.g., global store discount + item-level discount) mathematically capped to prevent sub-zero totals?

## 2. Inventory Race Conditions (Concurrency Vector)
**The Risk:** High-concurrency checkout environments. If two cashiers sell the exact same SKU simultaneously, the inventory engine might fail to deplete stock accurately, leading to negative inventory, especially in strict modules like Pharmacy (FEFO tracking) or Manufacturing (BOM depletion).
**Zero-Trust Audit Objectives:**
- **Database Locking:** Audit the checkout transaction pipeline for `DB::transaction()` blocks and `lockForUpdate()` pessimistic locks on the `inventory_stocks` table.
- **Stock Movement Ledger:** Verify that every depletion creates an immutable `StockMovement` log that cannot be altered or hard-deleted by cashiers.
- **Vertical Edge Cases:** Ensure that selling a "Manufactured" product correctly recursively depletes its raw material components.

## 3. RBAC Bleeding & Tenant Isolation (Security Vector)
**The Risk:** Permission escalation and horizontal tenant spoofing. A Cashier elevating themselves to Business Admin, or an authenticated user manipulating a `business_id` payload to access a competitor's CRM data.
**Zero-Trust Audit Objectives:**
- **Scope Verification:** Ensure that absolutely every Tenant-level model strictly extends `TenantModel` or utilizes the `business_id` global scope automatically.
- **API Guardrails:** Audit the Spatie Permission assignments. Can a Cashier hit a `PATCH /api/inventory/{id}` endpoint directly if the UI button is hidden?
- **Session Bleeding:** Verify Next.js Edge Middleware handles JWT and Sanctum state securely across the dynamic `[domain]` subdomains.

---
**Next Steps:** Awaiting CTO authorization to engage Phase 1 of the Tenant Core Audit (Financial Vector).
