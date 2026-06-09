import { test, expect } from '@playwright/test';

test.describe('Rate Limiting UI Handling', () => {
  test('should display toast and disable submit button when 429 received', async ({ page }) => {
    // Navigate to a login page where the rate limit UI is integrated
    await page.goto('/superadmin-login');

    // Intercept the tenant resolve API call to prevent hanging
    await page.route('**/api/v1/tenant/resolve/*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ tenant: { name: 'Demo Tenant', branding: {} } }),
        headers: { 'Access-Control-Allow-Origin': '*' }
      });
    });

    // Intercept CSRF to prevent hanging
    await page.route('**/sanctum/csrf-cookie', async (route) => {
      await route.fulfill({ status: 204, headers: { 'Access-Control-Allow-Origin': 'http://localhost:3000', 'Access-Control-Allow-Credentials': 'true' } });
    });

    // Intercept the login API call and force a 429 response ONLY for POST
    await page.route('**/api/v1/login', async (route) => {
      if (route.request().method() === 'OPTIONS') {
        await route.fulfill({ status: 204, headers: { 'Access-Control-Allow-Origin': 'http://localhost:3000', 'Access-Control-Allow-Credentials': 'true', 'Access-Control-Allow-Methods': 'POST, OPTIONS', 'Access-Control-Allow-Headers': '*' } });
        return;
      }
      await route.fulfill({
        status: 429,
        headers: {
          'Retry-After': '5',
          'Access-Control-Allow-Origin': 'http://localhost:3000',
          'Access-Control-Allow-Credentials': 'true',
          'Access-Control-Expose-Headers': 'Retry-After',
        },
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Too many requests.' }),
      });
    });
    // Wait for network idle to ensure the page has loaded
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'before-fill.png' });

    // Fill in the form
    await page.fill('input[type="email"]', 'test@example.com');
    await page.fill('input[type="password"]', 'password123');

    // Click submit and wait for the intercepted response
    await Promise.all([
      page.waitForResponse('**/api/v1/login'),
      page.click('button[type="submit"]')
    ]);

    // 1. Assert that the submit button becomes disabled
    const submitButton = page.locator('button[type="submit"]');
    await expect(submitButton).toBeDisabled({ timeout: 5000 });
    
    // 2. Assert that the button text changes to reflect the countdown
    await expect(submitButton).toContainText('Please wait 5s');

    // 3. Assert that the react-hot-toast appears with the correct message
    const toast = page.locator('text=Too many requests. Please try again in 5 seconds.');
    await expect(toast).toBeVisible({ timeout: 5000 });

    // Optional: wait a couple of seconds and verify countdown
    await page.waitForTimeout(2000);
    await expect(submitButton).toContainText('Please wait 3s');
  });
});
