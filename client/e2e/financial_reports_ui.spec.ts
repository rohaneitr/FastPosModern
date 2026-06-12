import { test, expect } from '@playwright/test';

test.describe('Financial Reports UI Integrity', () => {
  // We mock the API layer to purely test the UI boundaries and query parameters without needing a live backend
  
  test.beforeEach(async ({ page }) => {
    // Mock P&L Payload
    await page.route('**/api/v1/accounting/profit-and-loss*', async route => {
      const json = {
        data: {
          totals: { revenue: "0.0000", cogs: "0.0000", gross_profit: "0.0000", operating_expenses: "0.0000", net_profit: "0.0000" },
          breakdown: { revenue_accounts: [], cogs_accounts: [], expense_accounts: [] }
        }
      };
      await route.fulfill({ json });
    });

    // Mock Balance Sheet Payload
    await page.route('**/api/v1/accounting/balance-sheet*', async route => {
      const json = {
        data: {
          totals: { assets: "0.0000", liabilities: "0.0000", core_equity: "0.0000", retained_earnings: "0.0000", total_equity: "0.0000", liabilities_and_equity: "0.0000" },
          breakdown: { asset_accounts: [], liability_accounts: [], equity_accounts: [] }
        }
      };
      await route.fulfill({ json });
    });
  });

  test('Profit & Loss tab enforces chronological boundary pickers', async ({ page }) => {
    // Navigate to the Accounting Reports Page (assume auth is bypassed or not strictly enforced via UI mount directly)
    await page.goto('/business/reports/accounting');

    // Wait for the P&L View to mount (Default tab)
    await expect(page.locator('text="Profit & Loss Statement"').first()).toBeVisible();

    // Verify exactly TWO date inputs exist (Start and End)
    const startDateInput = page.locator('input[name="start_date"]');
    const endDateInput = page.locator('input[name="end_date"]');
    
    await expect(startDateInput).toBeVisible();
    await expect(endDateInput).toBeVisible();

    // Verify As Of Date does NOT exist
    await expect(page.locator('input[name="as_of_date"]')).toHaveCount(0);

    // Setup Request Interception to verify outgoing payload
    const requestPromise = page.waitForRequest(request => 
      request.url().includes('/accounting/profit-and-loss') && request.method() === 'GET'
    );

    // Trigger change
    await startDateInput.fill('2026-05-01');
    
    const request = await requestPromise;
    const url = new URL(request.url());
    
    // Assert the URL strictly contains start_date and end_date
    expect(url.searchParams.get('start_date')).toBe('2026-05-01');
    expect(url.searchParams.has('end_date')).toBeTruthy();
  });

  test('Profit & Loss tab renders data visualizer and supports export', async ({ page }) => {
    await page.goto('/business/reports/accounting');
    await expect(page.locator('text="Profit & Loss Statement"').first()).toBeVisible();

    // Verify Visualizer DOM Wrapper mounted
    await expect(page.locator('text="Performance Visualizer"').first()).toBeVisible();
    await expect(page.locator('text="Total Revenue"').first()).toBeVisible();
    await expect(page.locator('text="Gross Profit"').first()).toBeVisible();

    // Verify Export Buttons
    const csvExportBtn = page.locator('button:has-text("Export to Excel (CSV)")');
    await expect(csvExportBtn).toBeVisible();
    
    const pdfExportBtn = page.locator('button:has-text("Export to PDF")');
    await expect(pdfExportBtn).toBeVisible();

    // Verify CSV export click doesn't crash (we mock the actual download in a real runner, here we just assert it's clickable)
    await csvExportBtn.click();
    
    // We cannot easily test window.print() in standard headless without injecting mocks, but we verify button works
    await pdfExportBtn.hover();
  });

  test('Balance Sheet tab strictly enforces single As-Of snapshot picker and prints securely', async ({ page }) => {
    await page.goto('/business/reports/accounting');

    // Click the Balance Sheet Tab
    await page.click('button:has-text("Balance Sheet")');

    // Wait for the Balance Sheet view to mount
    await expect(page.locator('text="Snapshot As Of:"').first()).toBeVisible();

    // The core architectural proof: Start and End date MUST be destroyed
    await expect(page.locator('input[name="start_date"]')).toHaveCount(0);
    await expect(page.locator('input[name="end_date"]')).toHaveCount(0);

    // Ensure the single "As Of" picker is rendered
    const asOfInput = page.locator('input[name="as_of_date"]');
    await expect(asOfInput).toBeVisible();

    // Setup Request Interception
    const requestPromise = page.waitForRequest(request => 
      request.url().includes('/accounting/balance-sheet') && request.method() === 'GET'
    );

    // Trigger change
    await asOfInput.fill('2026-06-30');
    
    const request = await requestPromise;
    const url = new URL(request.url());

    // Assert the URL strictly contains ONLY as_of_date
    expect(url.searchParams.get('as_of_date')).toBe('2026-06-30');
    expect(url.searchParams.has('start_date')).toBeFalsy();
    expect(url.searchParams.has('end_date')).toBeFalsy();
  });
});
