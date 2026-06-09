import { useState, useMemo } from 'react';
import { useInventoryStock } from './use-inventory-stock';
import { useLocations } from './use-locations';
import api from '@/lib/api';

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
    await api.post('/inventory/adjust', payload);
    refreshStock();
  };

  const transferStock = async (payload: TransferPayload) => {
    await api.post('/inventory/transfer', payload);
    refreshStock();
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
