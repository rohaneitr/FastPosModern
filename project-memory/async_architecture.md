# FastPOS Project Memory - Asynchronous Processing Architecture Phase

## Latest Updates: 2026-06-04
Transitioned heavy operations to the Redis/Horizon background queue to optimize system resilience and avoid HTTP timeouts under load.

### 1. Stripe Webhooks Refactoring
- **Issue**: Synchronously processing Stripe Webhooks risks timeouts from third-party calls or database delays, potentially causing Stripe to needlessly retry payloads.
- **Resolution**: 
  - Refactored `SubscriptionController@handleStripeWebhook` to synchronously validate the webhook signature and immediately return a `200 OK` response to Stripe.
  - The actual payload parsing and subscription updates are now dispatched to `App\Jobs\ProcessStripeWebhookJob`.
  - Added robust `failed()` method in the job to log failures for Horizon dashboard monitoring.

### 2. Async CSV Exports
- **Issue**: Exporting large amounts of sales data to CSV synchronously blocks the PHP thread and can easily hit gateway timeouts for large tenants.
- **Resolution**:
  - Refactored `ReportController@exportSales` to immediately return a JSON acknowledgment to the frontend (`status: processing`).
  - Extracted the heavy DB query and file IO into `App\Jobs\ExportSalesCsvJob`.
  - The job queries the DB, writes the CSV to `storage/app/public/exports/`, and writes a marker to the Redis cache (`export_status_{user_id}`) upon completion or failure.
  - Users can now safely trigger exports and retrieve them without blocking the server.

### Queue Requirements
- **Driver**: Redis
- **Monitor**: Laravel Horizon
- **Job Interfaces**: All custom background jobs implement `ShouldQueue` and possess a `failed(\Throwable $exception)` method for graceful handling and observability.
