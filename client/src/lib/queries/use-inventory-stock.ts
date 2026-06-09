import useSWR from 'swr';
import api from '@/lib/api';
import { queryKeys } from './keys';

const fetcher = (url: string) => api.get(url).then((res) => res.data?.data || res.data || []);

export function useInventoryStock() {
  const { data, error, isLoading, mutate } = useSWR(queryKeys.inventoryStock, fetcher, {
    revalidateOnFocus: false,
    dedupingInterval: 15000,
  });

  return {
    stocks: (data || []) as any[],
    isLoading,
    isError: !!error,
    refresh: mutate,
  };
}
