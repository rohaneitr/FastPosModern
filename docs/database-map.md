# Database Map

The legacy system contains over 310 migration files, indicating a mature and complex schema. Below is a high-level map of the core entities derived from the migration files.

## Core Entities

### 1. Business & Multi-Tenancy
- **business**: The central tenant table.
- **business_locations**: Physical stores or branches for a business.
- **invoice_layouts** & **invoice_schemes**: Configurations for printing invoices per location.
- **printers**: Associated with locations for receipt printing.

### 2. Users & Authentication
- **users**: System users, staff, and admins.
- **roles** & **permissions** (Spatie): RBAC tables.
- **oauth_* (Passport)**: API authentication tables (tokens, clients).
- **security_tables** & **audit_logs**: Recent additions for security tracking.

### 3. Products & Inventory
- **products**: Core product information.
- **variations** & **product_variations**: Support for variable products (e.g., sizes, colors).
- **variation_location_details**: Tracks stock quantities per location.
- **brands**, **categories**, **units**, **warranties**: Product attributes.
- **selling_price_groups** & **variation_group_prices**: Tiered pricing.

### 4. Transactions (Sales, Purchases, Stock Transfers)
*The system uses a unified "transactions" table for all major movements.*
- **transactions**: Represents Sales, Purchases, Stock Adjustments, and Stock Transfers (distinguished by a `type` column).
- **transaction_sell_lines**: Individual items in a sale.
- **purchase_lines**: Individual items in a purchase.
- **transaction_sell_lines_purchase_lines**: Maps sold items to specific purchase lots (for precise profit tracking and FIFO).
- **transaction_payments**: Records payments made against transactions.

### 5. Contacts (CRM)
- **contacts**: Unified table for Suppliers and Customers.
- **customer_groups**: Grouping for applying bulk discounts.

### 6. Accounting & Cash Registers
- **accounts** & **account_transactions**: Basic chart of accounts and journal entries.
- **cash_registers** & **cash_register_transactions**: Tracking till balances for POS shifts.
- **expense_categories**: Categorization for expense tracking.

### 7. Mobile App Extensions
- **mobile_activations**, **mobile_devices**, **user_registrations**: Tables handling offline-first mobile app provisioning and activation.

### 8. System & Logs
- **activity_log**: Action auditing.
- **notifications** & **notification_templates**: Email/SMS logs and configurations.

## Schema Design Pattern Note
The system heavily relies on polymorphic-like or generic tables (e.g., `transactions` handling everything from a supplier purchase to a customer quote to a stock transfer). This "thick table" approach requires careful indexing and scoped queries.
