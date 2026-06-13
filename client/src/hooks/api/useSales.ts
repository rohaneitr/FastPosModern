import { useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient, { ValidationError } from '@/lib/apiClient';
import { inventoryKeys } from './useInventory';
import { db } from '@/lib/offlineDb';
import { v4 as uuidv4 } from 'uuid';

export interface CheckoutItemPayload {
  product_id: number;
  quantity: number;
  price: string | number;
}

export interface CheckoutPayload {
  items: CheckoutItemPayload[];
  total: string | number;
  payment_method: string;
  is_offline_sync?: boolean;
}

export interface CheckoutResponse {
  transaction_id: number;
  invoice_no: string;
  final_total: number;
  payment_status: string;
}

export function useSales() {
  const queryClient = useQueryClient();

  const checkoutMutation = useMutation<CheckoutResponse, Error, { payload: CheckoutPayload; idempotencyKey?: string }>({
    mutationFn: async ({ payload, idempotencyKey }) => {
      const config = idempotencyKey ? { headers: { 'X-Idempotency-Key': idempotencyKey } } : {};
      const client_uuid = idempotencyKey || uuidv4();

      const handleOfflineFallback = async () => {
        await db.offline_sales.add({
          client_uuid,
          payload,
          created_at: Date.now(),
          sync_status: 'pending',
        });

        // Fake 200 Success so the cashier is uninterrupted
        return {
          transaction_id: Date.now(),
          invoice_no: `OFFLINE-${client_uuid.substring(0, 8).toUpperCase()}`,
          final_total: Number(payload.total),
          payment_status: 'paid',
        } as CheckoutResponse;
      };

      if (typeof window !== 'undefined' && !navigator.onLine) {
        return handleOfflineFallback();
      }

      try {
        return await apiClient.post<CheckoutResponse>('/tenant/sales/checkout', payload, config);
      } catch (error: any) {
        // Only catch actual network drops or server crashes (5xx). 
        // DO NOT catch 422 ValidationErrors (like stock limits) — those must go to the UI.
        const isNetworkFailure = 
          error.message === 'Network Error' || 
          error.code === 'ECONNABORTED' || 
          (error.response?.status >= 500);

        if (isNetworkFailure) {
          return handleOfflineFallback();
        }

        throw error;
      }
    },
    onSuccess: () => {
      // Invalidate inventory cache instantly when a sale succeeds
      queryClient.invalidateQueries({ queryKey: inventoryKeys.all() });
    },
  });

  return {
    checkoutMutation,
  };
}
