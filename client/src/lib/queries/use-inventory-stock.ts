import useSWR from 'swr';
import { useEffect } from 'react';
import api from '@/lib/api';
import { queryKeys } from './keys';
import { globalSync } from '@/lib/sync/broadcast';

const fetcher = (url: string) => api.get(url).then((res) => res.data?.data || res.data || []);

export function useInventoryStock() {
  const { data, error, isLoading, mutate } = useSWR(queryKeys.inventoryStock, fetcher, {
    revalidateOnFocus: false,
    dedupingInterval: 15000,
  });

  useEffect(() => {
    const unsubscribe = globalSync.subscribe('INVENTORY_MUTATED', () => {
      mutate();
    });
    return () => unsubscribe();
  }, [mutate]);

  return {
    stocks: (data || []) as any[],
    isLoading,
    isError: !!error,
    refresh: mutate,
  };
}
