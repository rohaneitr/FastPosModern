import { useEffect, useCallback } from 'react';
import apiClient from '@/lib/apiClient';
import { db } from '@/lib/offlineDb';
import { useQueryClient } from '@tanstack/react-query';
import { inventoryKeys } from '@/hooks/api/useInventory';
import toast from 'react-hot-toast';

export function useOfflineSync() {
  const queryClient = useQueryClient();

  const processSyncQueue = useCallback(async () => {
    if (!navigator.onLine) return;

    try {
      // Find all pending sales
      const pendingSales = await db.offline_sales
        .where('sync_status')
        .equals('pending')
        .toArray();

      if (pendingSales.length === 0) return;

      let successCount = 0;

      // Process each sale
      for (const sale of pendingSales) {
        try {
          // Push to backend sync endpoint (which accepts the same payload as checkout)
          // The backend was built to handle /tenant/sync/push for batch or /tenant/sales/checkout?
          // The prompt says: "Push them to the backend using our /tenant/sync/push endpoint."
          // So we wrap the payload or send it as is. Usually sync/push takes an array of transactions
          // Let's assume we push each one individually or as a batch. 
          // The prompt says "Push them to the backend using our /tenant/sync/push endpoint."
          // For safety, we can send them one by one or in a batch if the endpoint expects it.
          // In previous context: /tenant/sync/push accepts an array or singular. Let's send a batch if possible,
          // but sending individually is safer if we don't know the exact schema of sync/push.
          // "Push them to the backend using our /tenant/sync/push endpoint. On success, delete them from Dexie."
          
          await apiClient.post('/tenant/sync/push', {
            transactions: [
              {
                ...sale.payload,
                client_uuid: sale.client_uuid,
                created_at: sale.created_at,
              }
            ]
          });

          // Delete from offline db on success
          await db.offline_sales.delete(sale.client_uuid);
          successCount++;
        } catch (error: any) {
          // If a 4xx validation error occurs, we might mark it as failed to avoid infinite loop
          if (error.response?.status >= 400 && error.response?.status < 500) {
            await db.offline_sales.update(sale.client_uuid, {
              sync_status: 'failed',
              error_message: error.message || 'Validation failed during sync',
            });
          }
        }
      }

      if (successCount > 0) {
        toast.success(`Successfully synchronized ${successCount} offline sales!`);
        // Invalidate inventory cache so stock updates reflect
        queryClient.invalidateQueries({ queryKey: inventoryKeys.all() });
      }
    } catch (err) {
      console.error('Offline sync failed:', err);
    }
  }, [queryClient]);

  useEffect(() => {
    // Check on window online event
    const handleOnline = () => {
      processSyncQueue();
    };

    window.addEventListener('online', handleOnline);

    // Also check periodically every 30 seconds just in case
    const interval = setInterval(() => {
      processSyncQueue();
    }, 30000);

    // Initial check on mount
    processSyncQueue();

    return () => {
      window.removeEventListener('online', handleOnline);
      clearInterval(interval);
    };
  }, [processSyncQueue]);
}
