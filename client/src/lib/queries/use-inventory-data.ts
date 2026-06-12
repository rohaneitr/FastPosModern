import { useState, useMemo } from 'react';
import { useInventoryStock } from './use-inventory-stock';
import { useLocations } from './use-locations';
import { mutate as globalMutate } from 'swr';
import { queryKeys } from './keys';
import api from '@/lib/api';
import { globalSync } from '@/lib/sync/broadcast';

export interface AdjustPayload {
  product_id: number;
  location_id: string | number;
  adjustment_type: 'increase' | 'decrease';
  quantity: number;
  reason: string;
}

export interface TransferPayload {
  product_id: number;
  from_location_id: string | number;
  to_location_id: string | number;
  quantity: number;
  note: string;
}

export function useInventoryData() {
  const { stocks, isLoading: isStocksLoading, isError: isStocksError, refresh: refreshStock } = useInventoryStock();
  const { locations, isLoading: isLocationsLoading } = useLocations();

  const [searchQuery, setSearchQuery] = useState('');

  const filteredStocks = useMemo(() => {
    if (!searchQuery) return stocks;
    const lowerQuery = searchQuery.toLowerCase();
    return stocks.filter(
      (s: any) =>
        s.product_name?.toLowerCase().includes(lowerQuery) ||
        (s.sku || '').toLowerCase().includes(lowerQuery)
    );
  }, [stocks, searchQuery]);

  const adjustStock = async (payload: AdjustPayload) => {
    const previousStocks = [...stocks];
    
    // Optimistic Update
    globalMutate(queryKeys.inventoryStock, (current: any[] = []) => {
      return current.map(s => {
        // If we match exactly by product_id and location_id, optimistically update
        // We only have product_name / location_name in the UI typically, 
        // but we can try to find by ID if the API includes it
        if (s.product_id === payload.product_id || s.id === payload.product_id) {
          const qty = Number(s.qty_available);
          const adj = Number(payload.quantity);
          return {
            ...s,
            qty_available: payload.adjustment_type === 'increase' ? qty + adj : Math.max(0, qty - adj)
          };
        }
        return s;
      });
    }, false);

    try {
      await api.post('/inventory/adjust', payload);
      globalSync.broadcast('INVENTORY_MUTATED');
      refreshStock();
    } catch (error) {
      // Rollback
      globalMutate(queryKeys.inventoryStock, previousStocks, false);
      throw error;
    }
  };

  const transferStock = async (payload: TransferPayload) => {
    // Pessimistic or simplistic for transfers due to complexity of multi-location updates
    try {
      await api.post('/inventory/transfer', payload);
      globalSync.broadcast('INVENTORY_MUTATED');
      refreshStock();
    } catch (error) {
      throw error;
    }
  };

  return {
    stocks,
    filteredStocks,
    isLoading: isStocksLoading,
    isError: isStocksError,
    locations,
    locationsLoading: isLocationsLoading,
    searchQuery,
    setSearchQuery,
    adjustStock,
    transferStock,
    refreshStock,
  };
}
