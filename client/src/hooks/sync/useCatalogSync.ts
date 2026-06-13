import { useEffect, useCallback, useState } from 'react';
import apiClient from '@/lib/apiClient';
import { db } from '@/lib/offlineDb';
import { useQueryClient } from '@tanstack/react-query';
import { inventoryKeys, Product } from '@/hooks/api/useInventory';

export interface CatalogPullResponse {
  products: Product[];
  server_timestamp: string;
}

export function useCatalogSync() {
  const queryClient = useQueryClient();
  const [isPulling, setIsPulling] = useState(false);

  const pullCatalog = useCallback(async () => {
    if (!navigator.onLine || isPulling) return;

    setIsPulling(true);
    try {
      // 1. Fetch last pulled timestamp
      const syncState = await db.sync_state.get('global');
      const lastPulledAt = syncState?.last_pulled_at || null;

      // 2. Fetch delta changes from server
      const response = await apiClient.get<CatalogPullResponse>('/tenant/sync/pull', {
        params: { since: lastPulledAt }
      });

      if (response && response.products && response.products.length > 0) {
        // 3. Bulk upsert downloaded catalog to Dexie
        await db.products.bulkPut(response.products);
        
        // 4. Save server timestamp back to sync_state
        await db.sync_state.put({
          id: 'global',
          last_pulled_at: response.server_timestamp
        });

        // 5. Invalidate inventory cache to refresh UI
        queryClient.invalidateQueries({ queryKey: inventoryKeys.all() });
      }
    } catch (error) {
      console.error('Catalog pull failed:', error);
    } finally {
      setIsPulling(false);
    }
  }, [queryClient, isPulling]);

  useEffect(() => {
    const handleOnline = () => {
      pullCatalog();
    };

    window.addEventListener('online', handleOnline);
    
    // Initial pull on mount
    pullCatalog();

    return () => {
      window.removeEventListener('online', handleOnline);
    };
  }, [pullCatalog]);

  return { isPulling, pullCatalog };
}
