import { create } from 'zustand';
import { Decimal, formatDecimal } from '../lib/decimal';

export interface CartItem {
  id: number;
  product_id: number;
  variation_id?: number;
  name: string;
  price: string | number; // allow receiving string from API
  quantity: number;
  has_serial_number?: boolean;
  serial_numbers?: string[];
  fractional_ratio?: number;
  dosage_instructions?: string;
  generic_name?: string;
  is_medicine?: boolean;
  unit_conversion_ratio?: number;
}

interface CartStore {
  items: CartItem[];
  taxRate: string | number;
  discountRate: string | number;
  
  // Actions
  addItem: (product: any) => void;
  removeItem: (id: number) => void;
  updateQuantity: (id: number, quantity: number) => void;
  updateItemField: (id: number, field: string, value: any) => void;
  clearCart: () => void;
  setDiscount: (rate: string | number) => void;
  
  // Computed helpers
  getCartTotal: () => string;
}

export const useCartStore = create<CartStore>((set, get) => ({
  items: [],
  taxRate: '0.1000', // 10%
  discountRate: '0.0000',

  addItem: (product) => set((state) => {
    // Check if item already exists in cart
    const existingItemIndex = state.items.findIndex(item => item.product_id === product.id);
    
    if (existingItemIndex >= 0) {
      // Increase quantity
      const newItems = [...state.items];
      newItems[existingItemIndex].quantity += 1;
      return { items: newItems };
    }

    // Add new item
    const newItem: CartItem = {
      id: Date.now(), // Generate a unique ID for the cart line
      product_id: product.id,
      name: product.name,
      price: product.price,
      quantity: 1,
      has_serial_number: product.has_serial_number,
      serial_numbers: [],
      generic_name: product.generic_name,
      is_medicine: product.is_medicine,
      unit_conversion_ratio: product.unit_conversion_ratio,
      fractional_ratio: 1,
    };
    
    return { items: [...state.items, newItem] };
  }),

  removeItem: (id) => set((state) => ({
    items: state.items.filter(item => item.id !== id)
  })),

  updateQuantity: (id, quantity) => set((state) => ({
    items: state.items.map(item => 
      item.id === id ? { ...item, quantity: Math.max(1, quantity) } : item
    )
  })),

  updateItemField: (id, field, value) => set((state) => ({
    items: state.items.map(item => 
      item.id === id ? { ...item, [field]: value } : item
    )
  })),

  clearCart: () => set({ items: [], discountRate: '0.0000' }),
  
  setDiscount: (rate) => set({ discountRate: formatDecimal(rate) }),
  
  getCartTotal: () => {
    const state = get();
    
    let subtotal = new Decimal(0);
    for (const item of state.items) {
      const price = new Decimal(item.price);
      const quantity = new Decimal(item.quantity);
      const fractionalRatio = new Decimal(item.fractional_ratio || 1);
      
      const lineTotal = price.mul(quantity).mul(fractionalRatio);
      subtotal = subtotal.add(lineTotal);
    }
    
    const discountRate = new Decimal(state.discountRate);
    const taxRate = new Decimal(state.taxRate);
    
    const discount = subtotal.mul(discountRate);
    const afterDiscount = subtotal.sub(discount);
    const tax = afterDiscount.mul(taxRate);
    const finalTotal = afterDiscount.add(tax);
    
    return formatDecimal(finalTotal);
  }
}));
