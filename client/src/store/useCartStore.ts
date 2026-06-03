import { create } from 'zustand';

export interface CartItem {
  id: number;
  product_id: number;
  variation_id?: number;
  name: string;
  price: number;
  quantity: number;
}

interface CartStore {
  items: CartItem[];
  taxRate: number; // For demo purposes, we will hardcode a 10% tax rate. In reality, it comes from the API.
  
  // Actions
  addItem: (product: any) => void;
  removeItem: (id: number) => void;
  updateQuantity: (id: number, quantity: number) => void;
  clearCart: () => void;
  
  // Computed (these can be handled as derived state in components, but convenient to have getters if possible, 
  // though standard Zustand doesn't natively do getters well without middleware. We'll rely on selectors in the component.)
}

export const useCartStore = create<CartStore>((set) => ({
  items: [],
  taxRate: 0.10, // 10%

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

  clearCart: () => set({ items: [] })
}));
