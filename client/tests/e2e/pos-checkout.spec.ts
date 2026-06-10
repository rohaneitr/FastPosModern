import { test, expect } from '@playwright/test';

test.describe('POS Checkout Lifecycle', () => {
  test('Cashier can add items, modify fractional ratio, checkout, and clear cart safely', async ({ page }) => {
    // 1. Cashier Login & navigate to POS
    await page.goto('/login');
    
    // Simulate login
    await page.fill('input[name="email"]', 'cashier@fastpos.test');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');

    // Wait for successful login redirect and auth state to settle
    await page.waitForURL('**/dashboard');

    // Navigate to POS terminal
    await page.goto('/pos');
    
    // Ensure POS is fully hydrated and mounted
    await expect(page.locator('h1:has-text("Point of Sale")')).toBeVisible();

    // 2. Add an item to the cart
    // Assuming there's a product grid and we click the first product
    const firstProduct = page.locator('.product-card').first();
    await expect(firstProduct).toBeVisible();
    const productName = await firstProduct.locator('.product-name').innerText();
    await firstProduct.click();

    // Verify item is in cart
    const cartItem = page.locator('.cart-item').filter({ hasText: productName });
    await expect(cartItem).toBeVisible();

    // Modify fractional_ratio (e.g., selling 0.5 of a KG/Unit)
    // We assume there's a fractional ratio input or modal for the cart item
    // Since our Zustand cart supports fractional_ratio, we simulate updating it
    const qtyInput = cartItem.locator('input[type="number"]').first();
    await qtyInput.fill('1.5'); // Example of fractional sale

    // Ensure subtotal calculates correctly
    await expect(page.locator('.cart-total-value')).not.toHaveText('0.00');

    // 3. Click "Pay" and await the 201 API response
    // Select payment method "Cash"
    const paymentSelect = page.locator('select[name="payment_method"]');
    if (await paymentSelect.isVisible()) {
        await paymentSelect.selectOption('cash');
    }

    // Intercept the API call to verify the payload and wait for the response
    const checkoutPromise = page.waitForResponse(
      response => response.url().includes('/api/v1/checkout') && response.status() === 201
    );

    const payButton = page.locator('button:has-text("Pay")');
    await expect(payButton).toBeEnabled();
    await payButton.click();

    // Wait for the backend transaction to complete
    const response = await checkoutPromise;
    const responseBody = await response.json();
    
    // Ensure the API returned a valid invoice number
    expect(responseBody).toHaveProperty('invoice_no');
    expect(responseBody.invoice_no).toBeTruthy();

    // 4. Assert that the Zustand cart clears automatically and the success toast appears
    
    // Verify the cart is empty (Zustand clearCart is triggered via BroadcastChannel or success callback)
    await expect(page.locator('.cart-item')).toHaveCount(0);
    await expect(page.locator('text="Your cart is empty"')).toBeVisible();

    // Verify the success toast appears
    const successToast = page.locator('.go3958315209'); // react-hot-toast class or generic locator
    await expect(page.locator('text=Sale Successful!')).toBeVisible();
    
    // Verify the receipt modal opens
    await expect(page.locator('.receipt-modal')).toBeVisible();
  });
});
