import { describe, it, expect, beforeEach } from 'vitest';
import { useCartStore } from '../store/useCartStore';

describe('POS Cart Logic (Zustand)', () => {
  beforeEach(() => {
    // Reset the store before each test
    useCartStore.getState().clearCart();
    useCartStore.setState({ taxRate: 0.10 }); // reset to default 10% tax
  });

  it('Test 1: Add item. Ensure quantity is 1', () => {
    const product = { id: 1, name: 'Widget A', price: 100, has_serial_number: false };
    
    useCartStore.getState().addItem(product);
    
    const items = useCartStore.getState().items;
    expect(items.length).toBe(1);
    expect(items[0].product_id).toBe(1);
    expect(items[0].quantity).toBe(1);
  });

  it('Test 2: Add same item again. Ensure quantity increments to 2', () => {
    const product = { id: 1, name: 'Widget A', price: 100, has_serial_number: false };
    
    useCartStore.getState().addItem(product);
    useCartStore.getState().addItem(product);
    
    const items = useCartStore.getState().items;
    expect(items.length).toBe(1); // Array should not duplicate
    expect(items[0].quantity).toBe(2);
  });

  it('Test 3: Apply a 10% discount. Ensure the cartTotal correctly reflects the discounted amount', () => {
    const product = { id: 1, name: 'Widget A', price: 100, has_serial_number: false };
    useCartStore.getState().addItem(product); // Subtotal: 100
    
    // Set 10% discount
    useCartStore.getState().setDiscount(0.10);
    
    // Subtotal: 100
    // Discount (10%): 10
    // After Discount: 90
    // Tax (10%): 9
    // Final Total: 99
    
    const total = useCartStore.getState().getCartTotal();
    expect(total).toBe('99.0000');
  });

  it('Test 4: Clear cart. Ensure array is empty and total is 0', () => {
    const product = { id: 1, name: 'Widget A', price: 100, has_serial_number: false };
    useCartStore.getState().addItem(product);
    
    // Clear cart
    useCartStore.getState().clearCart();
    
    const items = useCartStore.getState().items;
    const total = useCartStore.getState().getCartTotal();
    
    expect(items.length).toBe(0);
    // Depending on whether we've refactored, total might be 0 or '0.0000'
    // But since the setup uses 0, let's keep it loose or update it later.
    // For now we will test the strict equality for '0.0000' soon, let's just do `Number(total)`.
    expect(total).toBe('0.0000');
  });

  it('Test 5: Financial Precision Verification. Adding 0.1 and 0.2 must exactly equal 0.3300 after 10% tax', () => {
    useCartStore.setState({ taxRate: 0.10, discountRate: 0 });
    
    useCartStore.getState().addItem({ id: 1, name: 'Item 1', price: 0.1, has_serial_number: false });
    useCartStore.getState().addItem({ id: 2, name: 'Item 2', price: 0.2, has_serial_number: false });
    
    const total = useCartStore.getState().getCartTotal();
    
    // In pure JS, 0.1 + 0.2 = 0.30000000000000004
    // plus 10% tax = 0.33000000000000007.
    // We expect exactly '0.3300' to match Backend SCALE=4.
    expect(total).toBe('0.3300');
  });
});
