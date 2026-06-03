# Feature Inventory

Based on controller and module analysis, the following core features are present in the legacy system:

## 1. Inventory & Products
- **Products:** Manage variations, brands, categories (taxonomy), units, and warranties.
- **Stock Management:** Opening stock, stock adjustments, stock transfers between locations.
- **Selling Price Groups:** Different pricing tiers.
- **Barcodes & Labels:** Generate and print labels.
- **Imports:** Bulk import products and opening stock.

## 2. Sales & Point of Sale (POS)
- **POS Interface:** Dedicated fast POS controller (`SellPosController`).
- **Standard Sales:** Create, edit, and view sales (`SellController`).
- **Sales Orders:** Draft/Quotation management.
- **Returns:** Sell return management.
- **Discounts:** Standard discounts and ledger discounts.
- **Drafts & Quotations:** Handled within sales controllers.

## 3. Purchases & Supply Chain
- **Purchases:** Purchase entry and supplier invoicing.
- **Purchase Orders:** Issuing POs to suppliers.
- **Purchase Requisitions:** Internal requests.
- **Purchase Returns:** Returning stock to suppliers.

## 4. CRM & Contacts
- **Customers & Suppliers:** Managed uniformly as Contacts.
- **Customer Groups:** Grouping for discounts/pricing.
- **Sales Commission Agents:** Tracking commissions for internal or external agents.

## 5. Accounting & Finances
- **Accounts & Account Types:** Chart of accounts.
- **Expenses:** Expense categories and expense tracking.
- **Cash Registers:** Opening/closing registers and tracking cash shifts.
- **Payments:** Handling transaction payments.

## 6. Multi-Tenant & Business Settings
- **Business Profiles:** Location settings, business settings, tax rates, and group taxes.
- **Invoice Configuration:** Invoice schemes and layouts.
- **Types of Service:** E.g., Dine-in, Delivery, Custom.
- **Superadmin (SaaS):** Package and tenant management (via `Modules/Superadmin`).

## 7. HR & User Management
- **Users & Roles:** Granular permissions, role management, and user profiles.

## 8. Reporting
- **Extensive Reports:** Handled by a massive `ReportController` (200KB+ size indicates high complexity).
- **Account Reports:** Financial reporting.

## 9. API & Mobile App Support
- **Mobile Activations & Users:** Specific endpoints and logic to support the mobile offline-first app (which syncs with the system).
