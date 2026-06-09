import { test, expect } from '@playwright/test';

test.describe('Cash Control Security & DOM Gating', () => {
    test('DevTools bypass resistance test for DRAWER_LOCKED', async ({ page }) => {
        // Intercept network to force DRAWER_LOCKED state
        await page.route('/api/v1/register/status', async (route) => {
            const json = {
                is_open: false,
                settings: {
                    pos_enforce_device_lock: true,
                    pos_enforce_strict_cash_control: true
                },
                register: null
            };
            await route.fulfill({ json });
        });

        // Mock Web Crypto so the boot sequence completes in Playwright
        await page.addInitScript(() => {
            Object.defineProperty(window, 'crypto', {
                value: {
                    subtle: {
                        digest: async () => new Uint8Array([1, 2, 3]).buffer
                    },
                    randomUUID: () => 'mock-uuid-for-e2e'
                }
            });
        });

        // Navigate to the POS route (assuming /business/pos is where the provider wraps)
        // For the sake of isolated testing, we assume the provider is mounted here.
        // If the route doesn't exist yet, we're building the infrastructure blueprint first.
        // We'll just test the DOM logic structure for now.
        // Assuming we mount a test page or the actual /pos page
        await page.goto('/business/pos');

        // Wait for the VaultInterceptorOverlay to appear
        await expect(page.locator('.vault-overlay')).toBeVisible();

        // Ensure POS Workspace grid is NOT in the DOM natively
        await expect(page.locator('.pos-workspace-grid')).toHaveCount(0);

        // Simulate malicious DevTools "Delete Element"
        await page.evaluate(() => {
            const overlay = document.querySelector('.vault-overlay');
            if (overlay) overlay.remove();
        });

        // The DOM should now be empty of both the overlay AND the workspace
        await expect(page.locator('.vault-overlay')).toHaveCount(0);
        await expect(page.locator('.pos-workspace-grid')).toHaveCount(0);
        
        // Assert the body has no workspace text
        const bodyText = await page.textContent('body');
        expect(bodyText).not.toContain('Checkout');
    });
});
