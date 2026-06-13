import { useQuery, useMutation, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import apiClient, { ValidationError } from '@/lib/apiClient';
import { db } from '@/lib/offlineDb';

export const inventoryKeys = {
  all:      () => ['inventory'] as const,
  products: (search: string, page: number) => ['inventory', 'products', search, page] as const,
};

export interface Product {
  id: number;
  name: string;
  sku: string;
  barcode_type: string;
  category_id: number | null;
  brand_id: number | null;
  unit_id: number | null;
  purchase_price: number;
  selling_price: number;
  alert_quantity: number;
  current_stock: number;
  is_active: boolean;
  image_path: string | null;
  category?: { id: number; name: string; } | null;
  brand?: { id: number; name: string; } | null;
  unit?: { id: number; name: string; short_name: string; } | null;
}

export interface PaginatedProducts {
  data: Product[];
  _meta: {
    current_page: number;
    last_page: number;
    total: number;
  };
}

export interface CreateProductPayload {
  name: string;
  sku: string | null;
  category_id: number | null;
  brand_id: number | null;
  unit_id: number | null;
  purchase_price: number;
  selling_price: number;
  alert_quantity: number;
  current_stock: number;
}

export function useInventory(search = '', page = 1) {
  const query = useQuery<PaginatedProducts, Error>({
    queryKey: inventoryKeys.products(search, page),
    queryFn: async () => {
      if (typeof window !== 'undefined' && !navigator.onLine) {
        // OFFLINE: Bypass apiClient. Instead, query Dexie directly.
        const searchLower = search.toLowerCase();
        const results = await db.products
          .filter(p => p.name.toLowerCase().includes(searchLower))
          .offset((page - 1) * 15)
          .limit(15)
          .toArray();

        // Mock the Envelope so the POS UI pagination/grid doesn't break
        return {
          data: results,
          _meta: {
            current_page: page,
            last_page: 1, // Assume 1 page for simplicity in offline mode
            total: results.length
          }
        } as PaginatedProducts;
      }

      // ONLINE: Keep existing API fetch
      return apiClient.get<PaginatedProducts>('/products', { params: { search, page } });
    },
    staleTime: 60 * 1000,
  });

  return {
    products: query.data?.data ?? [],
    meta: query.data?._meta ?? null,
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    isError: query.isError,
    error: query.error,
    refetch: query.refetch,
  };
}

export function useCreateProduct() {
  const queryClient = useQueryClient();

  const mutation = useMutation<Product, Error, CreateProductPayload>({
    mutationFn: (payload) => apiClient.post<Product>('/products', payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: inventoryKeys.all() });
    },
  });

  return {
    createProduct: mutation.mutateAsync,
    isPending: mutation.isPending,
    error: mutation.error,
  };
}

export function useDeleteProduct() {
  const queryClient = useQueryClient();

  const mutation = useMutation<void, Error, number>({
    mutationFn: (id) => apiClient.delete(`/products/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: inventoryKeys.all() });
    },
  });

  return {
    deleteProduct: mutation.mutateAsync,
    isPending: mutation.isPending,
  };
}
