# FastPOS Project Memory - Automated QA Baseline

## Latest Updates: 2026-06-04
Initiated the Automated Quality Assurance Phase to mathematically guarantee the integrity of our core transaction logic before moving to frontend integration.

### Testing Infrastructure
- **Framework**: PHPUnit is installed natively within the Laravel core.
- **Database**: The testing environment utilizes a fast, isolated SQLite in-memory database (`DB_DATABASE=:memory:`), ensuring no cross-contamination with the development or production databases.
- **State Management**: The `RefreshDatabase` trait is employed across all core tests to completely migrate and seed the schema before each test executes, providing a pristine state.

### 1. Financial Integrity Test (`FinancialIntegrityTest`)
- **Objective**: Mathematically verify the POS checkout calculations.
- **Scenario**: A user processes a cart containing 2 items at $100 each. A $10 flat discount and a 5% tax rate are applied.
- **Assertions**: 
  - Confirms the transaction processes successfully (`201 Created`).
  - Asserts the final JSON payload guarantees exact mathematical precision: Subtotal ($200) - Discount ($10) = Pre-tax ($190) + Tax ($9.50) = Final Total ($199.50).
  - Asserts the stock is actively deducted by exactly 2 units in the `product_stocks` table.

### 2. Inventory Concurrency Test (`InventoryConcurrencyTest`)
- **Objective**: Prevent negative stock and race conditions.
- **Scenario**: A product has exactly 5 units available. The cashier attempts to ring up 10 units in the POS.
- **Assertions**:
  - Asserts that the system strictly throws a `422 Unprocessable Entity` validation exception.
  - Guarantees the database completely blocks the transaction to prevent negative inventory escalation.

### 3. Tenant Isolation Test (`TenantIsolationTest`)
- **Objective**: Validate the ironclad multi-tenant boundaries.
- **Scenario**: User A (Business A) creates a unique product. User B (Business B) attempts to directly access the API endpoint for User A's product using its specific ID.
- **Assertions**:
  - Asserts that the system returns a secure `404 Not Found`. 
  - Validates that the global tenant scope effectively renders other businesses' data completely invisible, ensuring compliance and data security.
