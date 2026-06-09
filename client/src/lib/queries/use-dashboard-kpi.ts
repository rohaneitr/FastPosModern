import useSWR from 'swr';
import api from '@/lib/api';
import { queryKeys } from './keys';

const fetcher = (url: string) => api.get(url).then((res) => res.data);

export function useDashboardKPI() {
  const { data, error, isLoading, mutate } = useSWR(queryKeys.dashboardKPI, fetcher, {
    revalidateOnFocus: false,
    dedupingInterval: 30000, // 30s dedup — dashboard data doesn't need real-time refresh
  });

  return {
    kpi: data,
    isLoading,
    isError: !!error,
    refresh: mutate,
  };
}
