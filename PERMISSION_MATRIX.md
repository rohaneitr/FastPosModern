# FastPOS Enterprise: Permission Matrix

| Permission | SuperAdmin | BusinessAdmin | Cashier | InventoryManager | Accountant |
| :--- | :---: | :---: | :---: | :---: | :---: |
| `platform.manage` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `tenant.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.create` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.edit` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `users.delete` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `products.manage` | ✅ | ✅ | ❌ | ✅ | ❌ |
| `inventory.manage` | ✅ | ✅ | ❌ | ✅ | ❌ |
| `sales.manage` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `reports.manage` | ✅ | ✅ | ❌ | ❌ | ✅ |
| `pos.access` | ✅ | ✅ | ✅ | ❌ | ❌ |

## High-Risk Findings
- `api/v1/tenant/inventory/transfer` lacks strict `permission:inventory.manage` mapping in `api.php`.
- `api/v1/tenant/sales/checkout` allows cashiers to bypass terminal locks if the `hardware_lock` middleware is spoofed.
