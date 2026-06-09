import useSWR from 'swr';
import api from '@/lib/api';
import { queryKeys } from './keys';

const fetcher = (url: string) => api.get(url).then((res) => res.data?.data || res.data || []);

export function useLocations() {
  const { data, error, isLoading } = useSWR(queryKeys.locations, fetcher, {
    revalidateOnFocus: false,
    dedupingInterval: 60000, // Locations rarely change
  });

  return {
    locations: (data || []) as any[],
    isLoading,
    isError: !!error,
  };
}
