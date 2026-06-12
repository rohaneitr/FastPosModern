import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { Decimal, formatDecimal } from '../lib/decimal';
import { globalSync } from '../lib/sync/broadcast';

export interface CartItem {
  id: number;
  product_id: number;
  variation_id?: number;
  name: string;
  price: string | number; // allow receiving string from API
  quantity: number;
  enable_sr_no?: boolean;
  enable_imei?: boolean;
  enable_warranty?: boolean;
  warranty_duration?: string;
  expiry_date?: string;
  serial_numbers?: string[];
  imei_numbers?: string[];
  fractional_ratio?: number;
  dosage_instructions?: string;
  generic_name?: string;
  is_medicine?: boolean;
  is_rx_required?: boolean;
  unit_conversion_ratio?: number;
  stockError?: string;
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
  
  // Hydration state
  hasHydrated: boolean;
  setHasHydrated: (state: boolean) => void;
  
  // Error handling
  setCartItemError: (product_id: number, error: string | undefined) => void;
}

export const useCartStore = create<CartStore>()(
  persist(
    (set, get) => ({
      items: [],
      taxRate: '0.1000', // 10%
      discountRate: '0.0000',
      hasHydrated: false,

      setHasHydrated: (state) => set({ hasHydrated: state }),

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
          enable_sr_no: product.enable_sr_no,
          enable_imei: product.enable_imei,
          enable_warranty: product.enable_warranty,
          warranty_duration: product.warranty_duration,
          expiry_date: product.expiry_date,
          serial_numbers: [],
          imei_numbers: [],
          generic_name: product.generic_name,
          is_medicine: product.is_medicine,
          is_rx_required: product.is_rx_required,
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
          item.id === id ? { ...item, quantity: Math.max(1, quantity), stockError: undefined } : item
        )
      })),

      updateItemField: (id, field, value) => set((state) => ({
        items: state.items.map(item => 
          item.id === id ? { ...item, [field]: value, stockError: undefined } : item
        )
      })),

      setCartItemError: (product_id, error) => set((state) => ({
        items: state.items.map(item => 
          item.product_id === product_id ? { ...item, stockError: error } : item
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
    }),
    {
      name: 'fastpos-cart-storage',
      storage: createJSONStorage(() => sessionStorage),
      onRehydrateStorage: () => (state) => {
        state?.setHasHydrated(true);
      },
    }
  )
);

if (typeof window !== 'undefined') {
  globalSync.subscribe('CART_CLEARED', () => {
    useCartStore.getState().clearCart();
  });
}
