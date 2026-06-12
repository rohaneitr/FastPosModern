# FastPOS Project Memory - Data Portability & Onboarding Phase

## Latest Updates: 2026-06-04
Implemented high-throughput background queue workers for parsing and importing massive CSV datasets to ensure zero-downtime tenant onboarding.

### 1. Bulk CSV Imports Architecture
- **Controllers & Routing**: Created `ImportController` handling `/products/import` and `/contacts/import`. Validations are securely gated via `ImportCsvRequest`.
- **Background Dispatch**: Instead of parsing synchronously (which crashes due to PHP memory limits and gateway timeouts on large files), the controller stores the CSV in the local `storage/app` disk and dispatches `ImportProductsCsvJob` or `ImportContactsCsvJob`.
- **Chunked Processing**: 
  - Jobs utilize `fopen` and `fgetcsv` to stream file contents in small memory chunks (100 rows per batch) rather than loading the entire file into an array.
  - Temporary files are securely deleted (`unlink`) immediately after completion to prevent disk bloat.

### 2. Tenant Data Isolation
- **Row-level Enforcement**: Inside the Job, every single parsed row is hardcoded to receive the `$businessId` context of the executing user. This ensures absolutely no cross-tenant data spillage even if a malicious payload is submitted.

### 3. Status Polling Strategy
- Maintained the exact same UX logic used for CSV Exports. 
- The background Jobs write their current state (`processing`, `completed`, `failed`) into the Redis cache using the `import_status_{type}_{user_id}` key.
- The UI can hit the lightweight `/products/import/status` endpoint to monitor progress without polling the actual database tables.

### Data Import CSV Templates
**Products Template Headers**:
`Name, SKU, Type, Barcode Type, Unit ID, Brand ID, Category ID, Enable Stock (yes/no), Alert Quantity, Sell Price Inc Tax`

**Contacts Template Headers**:
`Type (customer/supplier), Name, First Name, Last Name, Email, Contact ID, Tax Number, Mobile`
