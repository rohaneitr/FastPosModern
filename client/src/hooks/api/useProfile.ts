/**
 * useProfile.ts — Golden Standard Hook Pattern
 *
 * This file is the REFERENCE ARCHITECTURE for all TanStack Query v5 hooks
 * in FastPOS. Every new query/mutation hook MUST follow this pattern:
 *
 *   1. Define strict TypeScript interfaces for request payload and response.
 *   2. Define a centralized queryKey factory (array, never a plain string).
 *   3. Write a typed fetcher function that uses apiClient (not the old api.ts).
 *   4. Export a useQuery hook with explicit generic parameters.
 *   5. Export a useMutation hook that invalidates related keys on success.
 *   6. Export a typed Hook return interface so callers get IDE autocomplete.
 *
 * API Endpoints (from IAM module Routes/api.php):
 *   GET  /api/v1/profile          → ProfileController@getProfile
 *   PUT  /api/v1/profile          → ProfileController@updateProfile
 *   PUT  /api/v1/profile/password → ProfileController@changePassword
 *   POST /api/v1/profile/avatar   → ProfileController@updateAvatar
 *
 * NOTE ON ENVELOPE UNWRAPPING:
 * apiClient.ts unwraps the Laravel ApiResponse envelope automatically.
 * The fetcher functions below receive the inner `data` object directly —
 * NOT the full { success, message, data } shape.
 *
 * @version Phase 4 — Frontend Architecture
 */

import { useQuery, useMutation, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import apiClient, { type ValidationError } from '@/lib/apiClient';
import { useAuthStore } from '@/store/useAuthStore';

// ── Query Key Factory ─────────────────────────────────────────────────────────
// Keys are arrays (not strings) so TanStack Query can do smart partial
// invalidation. e.g. invalidating profileKeys.all() also invalidates
// profileKeys.detail() — structured scoping.

export const profileKeys = {
  /** Invalidates EVERYTHING in the profile namespace */
  all:        ()    => ['profile']              as const,
  /** The authenticated user's own profile */
  detail:     ()    => ['profile', 'detail']    as const,
  /** Recent activity log entries */
  activities: ()    => ['profile', 'activities'] as const,
} as const;

// ── Types ─────────────────────────────────────────────────────────────────────

/** A Spatie role object as returned by the backend (with guard_name) */
export interface UserRole {
  id: number;
  name: string;
  guard_name: string;
}

/** A Spatie permission object */
export interface UserPermission {
  id: number;
  name: string;
  guard_name: string;
}

/**
 * The authenticated user profile shape.
 * Mirrors the User model returned by ProfileController@getProfile
 * after ->load(['roles', 'permissions', 'business']).
 *
 * NOTE: ProfileController currently returns the raw Eloquent model.
 * Once it is updated to use ApiResponse trait, apiClient will unwrap
 * it automatically. This interface covers both cases because the shape
 * of the user object itself does not change.
 */
export interface UserProfile {
  id: number;
  first_name: string;
  last_name: string | null;
  email: string;
  phone: string | null;
  address: string | null;
  timezone: string | null;
  language: 'en' | 'bn';
  avatar: string | null;
  is_super_admin: boolean;
  business_id: number | null;
  two_factor_enabled: boolean;
  preferences: Record<string, unknown> | null;
  roles: UserRole[];
  permissions: UserPermission[];
  business: {
    id: number;
    name: string;
    subdomain: string;
    is_active: boolean;
  } | null;
  created_at: string;
  updated_at: string;
}

/** Payload for PUT /profile */
export interface UpdateProfilePayload {
  first_name: string;
  last_name?: string | null;
  email: string;
  phone?: string | null;
  address?: string | null;
  timezone?: string | null;
}

/** Payload for PUT /profile/password */
export interface ChangePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

// ── Fetchers ──────────────────────────────────────────────────────────────────
// Pure async functions, not hooks. Kept separate so they can be called
// imperatively (e.g. server-side prefetch, one-shot form submit).

/**
 * Fetch the authenticated user's profile.
 * apiClient unwraps the envelope → returns UserProfile directly.
 */
async function fetchProfile(): Promise<UserProfile> {
  return apiClient.get<UserProfile>('/profile');
}

/**
 * Update the authenticated user's profile fields.
 * Returns the updated UserProfile (unwrapped by apiClient).
 */
async function updateProfile(payload: UpdateProfilePayload): Promise<UserProfile> {
  return apiClient.put<UserProfile>('/profile', payload);
}

// ── useProfile — Query Hook ───────────────────────────────────────────────────

/** Return type of the useProfile hook — fully typed for IDE autocomplete */
export interface UseProfileReturn {
  /** The user profile data, or undefined while loading */
  profile: UserProfile | undefined;
  /** True on initial load before any data has been fetched */
  isLoading: boolean;
  /** True when a background refetch is in progress */
  isFetching: boolean;
  /** True if the query encountered an error */
  isError: boolean;
  /** The raw error object (may be ValidationError or AxiosError) */
  error: Error | null;
  /** True when profile data has been successfully loaded at least once */
  isSuccess: boolean;
  /** Call this to force an immediate refetch */
  refetch: UseQueryResult<UserProfile>['refetch'];
}

/**
 * useProfile
 *
 * Fetches and caches the authenticated user's profile.
 * Provides a stable identity — the same QueryClient cache entry is reused
 * wherever this hook is called in the component tree.
 *
 * @param enabled - Set to false to skip the query (e.g. if user is not logged in)
 *
 * @example
 *   const { profile, isLoading } = useProfile();
 *   if (isLoading) return <Skeleton />;
 *   return <h1>Welcome, {profile.first_name}</h1>;
 */
export function useProfile(enabled = true): UseProfileReturn {
  const query = useQuery<UserProfile, Error>({
    queryKey:  profileKeys.detail(),
    queryFn:   fetchProfile,
    enabled,
    // Override global staleTime for the profile — it changes rarely
    // but when it does (name, avatar) we want it to feel instant via mutation.
    staleTime: 10 * 60 * 1000, // 10 minutes
  });

  return {
    profile:    query.data,
    isLoading:  query.isLoading,
    isFetching: query.isFetching,
    isError:    query.isError,
    error:      query.error,
    isSuccess:  query.isSuccess,
    refetch:    query.refetch,
  };
}

// ── useUpdateProfile — Mutation Hook ──────────────────────────────────────────

/** Return type of the useUpdateProfile hook */
export interface UseUpdateProfileReturn {
  /**
   * Call this with the new profile fields.
   * Returns a Promise<UserProfile> — await it in form onSubmit handlers.
   */
  updateProfile: (payload: UpdateProfilePayload) => Promise<UserProfile>;
  /** True while the mutation network request is in flight */
  isPending: boolean;
  /** True if the last mutation call succeeded */
  isSuccess: boolean;
  /** True if the last mutation call failed */
  isError: boolean;
  /** The error from the last failed mutation attempt */
  error: Error | null;
  /** Resets isSuccess/isError state (call between form submits if needed) */
  reset: () => void;
}

/**
 * useUpdateProfile
 *
 * Mutates the authenticated user's profile and automatically:
 *  1. Invalidates profileKeys.detail() so useProfile() refetches fresh data
 *  2. Syncs the Zustand useAuthStore with updated name/permissions
 *     so the sidebar and nav bar reflect changes without a page reload.
 *
 * Error Handling:
 *  - 422 ValidationError: The `error` field will be a ValidationError instance.
 *    Call `error.errors` to get the field map and pass to react-hook-form setError().
 *  - Other errors: re-thrown as-is, caught by the component's try/catch or onError.
 *
 * @example
 *   const { updateProfile, isPending, error } = useUpdateProfile();
 *
 *   const onSubmit = async (data: UpdateProfilePayload) => {
 *     try {
 *       await updateProfile(data);
 *       toast.success('Profile saved!');
 *     } catch (err) {
 *       if (err instanceof ValidationError) {
 *         Object.entries(err.errors).forEach(([field, messages]) => {
 *           form.setError(field as keyof UpdateProfilePayload, {
 *             message: messages[0],
 *           });
 *         });
 *       }
 *     }
 *   };
 */
export function useUpdateProfile(): UseUpdateProfileReturn {
  const queryClient = useQueryClient();

  // Read the Zustand setAuth action once — stable reference
  const setAuth      = useAuthStore((state) => state.setAuth);
  const currentUser  = useAuthStore((state) => state.user);
  const permissions  = useAuthStore((state) => state.permissions);
  const locationId   = useAuthStore((state) => state.location_id);

  const mutation = useMutation<UserProfile, Error, UpdateProfilePayload>({
    mutationFn: updateProfile,

    onSuccess: (updatedProfile) => {
      // 1. Write the fresh data directly into the cache so useProfile()
      //    shows the new name/email immediately without waiting for a refetch.
      queryClient.setQueryData<UserProfile>(
        profileKeys.detail(),
        updatedProfile,
      );

      // 2. Invalidate the profile key so a background refetch is triggered.
      //    This ensures the cache stays in sync even if setQueryData had stale
      //    permission or relationship data.
      queryClient.invalidateQueries({ queryKey: profileKeys.all() });

      // 3. Sync the Zustand auth store so the navbar/sidebar reflects the
      //    updated name and email without a full page reload.
      //    We preserve existing permissions and location_id — those are not
      //    changed by a profile update.
      if (currentUser) {
        setAuth(
          {
            ...currentUser,
            first_name: updatedProfile.first_name,
            last_name:  updatedProfile.last_name,
            email:      updatedProfile.email,
            phone:      updatedProfile.phone,
            avatar:     updatedProfile.avatar,
          },
          permissions,
          locationId,
        );
      }
    },

    // onError is intentionally omitted here — apiClient already shows a toast
    // for 5xx errors. 422 ValidationErrors are surfaced via the error field
    // so the form can call setError() without needing a separate callback.
  });

  return {
    updateProfile: mutation.mutateAsync,
    isPending:     mutation.isPending,
    isSuccess:     mutation.isSuccess,
    isError:       mutation.isError,
    error:         mutation.error,
    reset:         mutation.reset,
  };
}
