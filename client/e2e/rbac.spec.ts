import { test, expect } from '@playwright/test';

test.describe('Frontend RBAC Enforcement', () => {

  test('Cashier should not see administrative buttons on Category page', async ({ page }) => {
    // 1. Mock the sessionStorage for a Cashier user
    const cashierUser = {
      id: 2,
      name: 'Cashier User',
      email: 'cashier@fastpos.test',
      business_id: 1,
      business: { status: 'active', active_modules: ['pos', 'inventory', 'catalog'] },
      roles: [{ name: 'Cashier' }]
    };

    // We navigate to a public or base page to inject localStorage/sessionStorage
    await page.goto('/');
    
    // Inject auth state
    await page.evaluate((user) => {
      sessionStorage.setItem('fastpos_user', JSON.stringify(user));
      sessionStorage.setItem('fastpos_token', 'mock_token_for_cashier');
    }, cashierUser);

    // 2. Navigate to Categories (which is protected by Role but Cashiers have read access)
    // Actually, in layout.tsx, Cashiers trying to access /business/... directly get redirected to /user/pos
    // unless the path is explicitly allowed. Wait, layout.tsx hard-guard:
    // const adminPaths = ['/business/dashboard', '/business/reports', '/business/settings', '/business/hr', '/business/users', '/business/accounting'];
    // /business/categories is NOT an adminPath, so Cashier can visit it!
    await page.goto('/business/categories');

    // 3. Verify Page loaded by checking Title
    await expect(page.locator('h1')).toContainText('Catalog Setup');

    // 4. Assert Add Category Button is NOT rendered
    const addButton = page.locator('button', { hasText: 'Add Category' });
    await expect(addButton).not.toBeVisible();

    // 5. Verify the Sidebar does not show 'Users & Roles' or 'Settings'
    const settingsLink = page.locator('nav').locator('a', { hasText: 'Settings' });
    const hrLink = page.locator('nav').locator('a', { hasText: 'Staff & HR' });
    
    await expect(settingsLink).not.toBeVisible();
    await expect(hrLink).not.toBeVisible();
  });

  test('API 403 gracefully handled by interceptor', async ({ page }) => {
    // Mock the API response to return a 403 Forbidden
    await page.route('**/api/v1/categories', async route => {
      const response = await route.fetch();
      await route.fulfill({
        status: 403,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Forbidden access' })
      });
    });

    const cashierUser = {
      id: 2,
      name: 'Cashier User',
      roles: [{ name: 'Cashier' }]
    };

    await page.goto('/');
    await page.evaluate((user) => {
      sessionStorage.setItem('fastpos_user', JSON.stringify(user));
      sessionStorage.setItem('fastpos_token', 'mock_token_for_cashier');
    }, cashierUser);

    // Mock window.alert to capture the message
    const alerts: string[] = [];
    page.on('dialog', async dialog => {
      alerts.push(dialog.message());
      await dialog.accept();
    });

    await page.goto('/business/categories');

    // Wait for the route to be triggered and alert to show
    await page.waitForTimeout(1000);

    expect(alerts).toContain('403 Forbidden: Access Denied. You do not have permission to perform this action.');
  });
});
