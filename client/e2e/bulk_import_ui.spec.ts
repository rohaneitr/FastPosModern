import { test, expect } from '@playwright/test';

test.describe('Bulk Import UI & Streaming Remediation', () => {
    
    test.beforeEach(async ({ page }) => {
        // Mock the file upload API
        await page.route('**/api/v1/data-migration/import/products', async route => {
            const json = {
                message: 'Import queued successfully.',
                import_id: 999,
                total_rows: 500
            };
            await route.fulfill({ status: 202, json });
        });
    });

    test('Simulates dragging a file, polls progress bar to 100% completion', async ({ page }) => {
        // Mock the polling API to simulate a fast progression
        let pollCount = 0;
        await page.route('**/api/v1/data-migration/status/999', async route => {
            pollCount++;
            
            if (pollCount === 1) {
                // Initial State
                await route.fulfill({ json: { data: { id: 999, status: 'processing', total_rows: 500, processed_rows: 250, successful_rows: 250, failed_rows: 0, errors: {} } } });
            } else {
                // Completed State
                await route.fulfill({ json: { data: { id: 999, status: 'completed', total_rows: 500, processed_rows: 500, successful_rows: 500, failed_rows: 0, errors: {} } } });
            }
        });

        await page.goto('/business/settings/imports');

        // Verify Dashboard Header
        await expect(page.locator('text="Bulk Data Import"')).toBeVisible();

        // Simulate File Upload by unhiding the input and dispatching event
        // (Since drag/drop is hard to mock purely in standard Playwright without specific element coordinates, we interact with the hidden input directly)
        const fileInput = page.locator('input[type="file"]');
        
        // Use an empty buffer to simulate a CSV
        await fileInput.setInputFiles({
            name: 'test_products.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from('name,sku,price,cost\nProduct A,SKU-1,10,5\n')
        });

        // The UI should transition to the progress bar. In polling step 1, it hits 250/500 (50%)
        await expect(page.locator('text="Importing Data..."')).toBeVisible();
        await expect(page.locator('text="50%"')).toBeVisible();
        await expect(page.locator('text="Processing: 250 / 500 rows"')).toBeVisible();

        // In polling step 2 (1 second later), it hits 100%
        await expect(page.locator('text="100%"')).toBeVisible();
        await expect(page.locator('text="Import Complete"')).toBeVisible();
    });

    test('Simulates processing failure and securely renders Error Remediation Panel', async ({ page }) => {
        // Mock the polling API returning a partial success with JSONB errors
        await page.route('**/api/v1/data-migration/status/999', async route => {
            await route.fulfill({ 
                json: { 
                    data: { 
                        id: 999, 
                        status: 'partial_success', 
                        total_rows: 500, 
                        processed_rows: 500, 
                        successful_rows: 498, 
                        failed_rows: 2, 
                        errors: {
                            "245": "Prices and costs cannot be negative.",
                            "300": "SKU already exists in catalog: SKU-DUPLICATE"
                        } 
                    } 
                } 
            });
        });

        await page.goto('/business/settings/imports');

        // Simulate Upload
        const fileInput = page.locator('input[type="file"]');
        await fileInput.setInputFiles({
            name: 'malicious_products.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from('name,sku,price,cost\nProduct A,SKU-1,10,5\n')
        });

        // Verify the Error Remediation Panel mounts
        await expect(page.locator('text="Attention Required: 2 Rows Failed Validation"')).toBeVisible();

        // Verify exact rows and messages mapped properly
        await expect(page.locator('span:has-text("Row 245")')).toBeVisible();
        await expect(page.locator('span:has-text("Prices and costs cannot be negative.")')).toBeVisible();

        await expect(page.locator('span:has-text("Row 300")')).toBeVisible();
        await expect(page.locator('span:has-text("SKU already exists in catalog: SKU-DUPLICATE")')).toBeVisible();
    });
});
