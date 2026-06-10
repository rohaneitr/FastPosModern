import { create } from 'zustand';

export type OrderStatus = 'Draft' | 'Scheduled' | 'In-Progress' | 'Completed' | 'Cancelled';

export interface ProductionOrder {
    id: number;
    order_number: string;
    product_name: string; // denormalized for UI
    quantity: number;
    status: OrderStatus;
    isProcessing?: boolean; // UI-only state to lock card during API calls
    updated_at: string;
}

interface KanbanState {
    orders: ProductionOrder[];
    setOrders: (orders: ProductionOrder[]) => void;
    moveOrderOptimistic: (orderId: number, newStatus: OrderStatus) => void;
    rollbackOrder: (orderId: number, previousStatus: OrderStatus) => void;
    reconcileOrderFromSocket: (socketOrder: any) => void;
    setProcessing: (orderId: number, isProcessing: boolean) => void;
}

export const useKanbanStore = create<KanbanState>((set) => ({
    orders: [],
    
    setOrders: (orders) => set({ orders }),

    moveOrderOptimistic: (orderId, newStatus) => set((state) => ({
        orders: state.orders.map(order => 
            order.id === orderId 
                ? { ...order, status: newStatus, isProcessing: true } 
                : order
        )
    })),

    rollbackOrder: (orderId, previousStatus) => set((state) => ({
        orders: state.orders.map(order => 
            order.id === orderId 
                ? { ...order, status: previousStatus, isProcessing: false } 
                : order
        )
    })),

    setProcessing: (orderId, isProcessing) => set((state) => ({
        orders: state.orders.map(order => 
            order.id === orderId 
                ? { ...order, isProcessing } 
                : order
        )
    })),

    reconcileOrderFromSocket: (socketOrder) => set((state) => {
        const exists = state.orders.find(o => o.id === socketOrder.id);
        if (exists) {
            // Only overwrite if the socket payload is newer (prevent overwriting optimistic local moves prematurely)
            const socketTime = new Date(socketOrder.updated_at).getTime();
            const localTime = new Date(exists.updated_at).getTime();
            
            if (socketTime >= localTime) {
                return {
                    orders: state.orders.map(o => o.id === socketOrder.id ? { ...o, ...socketOrder, isProcessing: false } : o)
                };
            }
            return state;
        } else {
            return { orders: [...state.orders, { ...socketOrder, isProcessing: false }] };
        }
    }),
}));
