import useSWR from 'swr';
import api from '@/lib/api';

const fetcher = (url: string) => api.get(url).then(res => res.data);

export function useProfitAndLoss(startDate: string, endDate: string) {
  const url = `/reports/pnl?start_date=${startDate}&end_date=${endDate}`;
  
  // Cache for 60 seconds (60000ms) deduping
  const { data, error, isLoading, mutate } = useSWR(url, fetcher, { 
    revalidateOnFocus: false,
    dedupingInterval: 60000 
  });

  return {
    data,
    isLoading,
    error,
    refresh: mutate
  };
}

// Stubs for future reports
export function useSalesSummary(startDate: string, endDate: string) {
  return { data: null, isLoading: false, error: null, refresh: () => {} };
}

export function useInventoryValuation() {
  return { data: null, isLoading: false, error: null, refresh: () => {} };
}
