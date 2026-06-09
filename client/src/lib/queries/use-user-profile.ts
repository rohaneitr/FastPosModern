import useSWR from 'swr';
import api from '@/lib/api';
import { queryKeys } from './keys';

const fetcher = (url: string) => api.get(url).then((res) => res.data);

/**
 * SWR hook to re-hydrate the user session.
 * Used by AuthGuard to validate that the Sanctum cookie is still active.
 */
export function useUserProfile(enabled = true) {
  const { data, error, isLoading, mutate } = useSWR(
    enabled ? queryKeys.user : null,
    fetcher,
    {
      revalidateOnFocus: false,
      shouldRetryOnError: false,
      dedupingInterval: 10000,
    }
  );

  return {
    user: data,
    isLoading,
    isError: !!error,
    isUnauthenticated: error?.response?.status === 401,
    refresh: mutate,
  };
}
