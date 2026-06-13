/**
 * apiClient.ts — FastPOS Enterprise API Client
 *
 * Single Axios instance with:
 *   - Automatic ApiResponse envelope unwrapping (success responses return `data` directly)
 *   - Typed error surfaces per HTTP status code
 *   - Sanctum httpOnly cookie auth (withCredentials: true)
 *   - Hardware hash + tenant isolation headers injected per request
 *   - 401 → clearAuth() + /login redirect (never fires on the login endpoint itself)
 *   - 403 → throws AccessDeniedError (caught by calling component)
 *   - 422 → throws ValidationError with field errors map
 *   - 402/429/409/503/5xx → toast notifications with no redirect
 *
 * NOTE: SWR/query fetchers that call apiClient.get(...).then(res => res)
 * receive the unwrapped data directly (NOT an AxiosResponse wrapper),
 * because the response interceptor returns `response.data.data` on success.
 *
 * @version Phase 4 — Frontend Architecture
 */

import axios, {
  type AxiosInstance,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from 'axios';
import toast from 'react-hot-toast';
import { useRateLimitStore } from '@/store/useRateLimitStore';

// ── Types ─────────────────────────────────────────────────────────────────────

/** Shape of every success response from the FastPOS Laravel backend. */
export interface ApiSuccessEnvelope<T = unknown> {
  success: true;
  message: string;
  data: T;
  meta?: PaginationMeta;
  links?: PaginationLinks;
  code?: never;
}

/** Shape of every error response from the FastPOS Laravel backend. */
export interface ApiErrorEnvelope {
  success: false;
  message: string;
  code: string;
  errors?: Record<string, string[]>;
  debug?: unknown;
}

export type ApiEnvelope<T = unknown> = ApiSuccessEnvelope<T> | ApiErrorEnvelope;

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

/** Thrown by the interceptor when the server returns 403. */
export class AccessDeniedError extends Error {
  readonly code = 'FORBIDDEN';
  constructor(message = 'You do not have permission to perform this action.') {
    super(message);
    this.name = 'AccessDeniedError';
  }
}

/**
 * Thrown by the interceptor when the server returns 422.
 * `errors` is the direct field-error map from Laravel's ValidationException.
 * React Hook Form setError() can iterate this directly.
 */
export class ValidationError extends Error {
  readonly code = 'VALIDATION_FAILED';
  readonly errors: Record<string, string[]>;
  constructor(errors: Record<string, string[]>, message = 'The given data was invalid.') {
    super(message);
    this.name = 'ValidationError';
    this.errors = errors;
  }
}

// ── Base URL ──────────────────────────────────────────────────────────────────

/**
 * Resolves the API base URL.
 * - SSR (Node.js): uses the internal Docker network hostname so server
 *   components can reach the backend without going through nginx.
 * - Browser: uses the public-facing URL from the env variable.
 */
function resolveBaseUrl(): string {
  if (typeof window === 'undefined') {
    // Internal Docker hostname — never exposed to the browser
    return process.env.NEXT_PUBLIC_API_INTERNAL_URL ?? 'http://backend:8000/api/v1';
  }
  return process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8002/api/v1';
}

// ── Instance ──────────────────────────────────────────────────────────────────

const apiClient: AxiosInstance = axios.create({
  baseURL: resolveBaseUrl(),
  withCredentials: true,          // Sanctum httpOnly cookie transport
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest', // Required for Laravel to detect Ajax
  },
  timeout: 15_000,
});

// ── Request Interceptor ────────────────────────────────────────────────────────

apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig): InternalAxiosRequestConfig => {
    // Client-only header injection (hardware lock + tenant isolation)
    if (typeof window !== 'undefined') {
      const hwHash = localStorage.getItem('pos_hardware_hash');
      if (hwHash) {
        config.headers['X-Device-Hash'] = hwHash;
      }

      // Row-level tenant/branch isolation — injected on every request so the
      // backend can cross-check against the authenticated user's business_id.
      const tenantId   = localStorage.getItem('fastpos_tenant_id');
      const locationId = localStorage.getItem('fastpos_location_id');

      if (tenantId)   config.headers['X-Tenant-ID']   = tenantId;
      if (locationId) config.headers['X-Location-ID'] = locationId;
    }

    return config;
  },
  (error) => Promise.reject(error),
);

// ── Response Interceptor ───────────────────────────────────────────────────────

apiClient.interceptors.response.use(
  /**
   * SUCCESS path — HTTP 2xx
   *
   * Unwrap the Laravel ApiResponse envelope so callers receive the inner
   * `data` payload directly instead of the full Axios response object.
   *
   * If `response.data.success` is true  → return `response.data.data`
   * If `response.data.success` is false → (shouldn't happen on 2xx, but
   *   guard against malformed responses) → return the raw response.
   *
   * IMPORTANT: The return type here is `any` because Axios generics only
   * propagate if you type every call-site. The AxiosInstance is still used
   * with explicit generic params (e.g. apiClient.get<Product>(...)) in
   * query files to get typed results.
   */
  (response: AxiosResponse<ApiEnvelope>): any => {
    const envelope = response.data;

    // Only unwrap well-formed envelopes; pass through anything else (health check, etc.)
    if (envelope && typeof envelope === 'object' && 'success' in envelope) {
      if (envelope.success === true) {
        // Attach pagination meta/links as top-level siblings so SWR hooks
        // can read them without needing the full envelope.
        const unwrapped = envelope.data;

        if (envelope.meta || envelope.links) {
          // Attach pagination meta/links as top-level siblings so SWR hooks
          // can access them without deserializing the full envelope.
          // We box the unwrapped value in a plain object rather than spreading
          // (avoids TS2698 "spread types may only be created from object types").
          return {
            data:   unwrapped,
            _meta:  envelope.meta  ?? null,
            _links: envelope.links ?? null,
          };
        }

        return unwrapped;
      }
    }

    // Non-envelope response (e.g. binary, health check) — return as-is
    return response.data;
  },

  /**
   * ERROR path — HTTP 4xx / 5xx
   *
   * Each status code is handled deterministically:
   *   401 / 419 → clearAuth + redirect to /login (skip on login endpoint itself)
   *   402        → redirect to /business/billing (subscription wall)
   *   403        → throw AccessDeniedError (caller handles UI)
   *   422        → throw ValidationError with errors map (React Hook Form)
   *   429        → toast + populate rate-limit store
   *   409        → toast (deadlock)
   *   503        → redirect to /maintenance
   *   5xx        → toast (generic server error)
   *
   * Special: DEVICE_REVOKED error_code → hard logout regardless of status
   */
  (error): Promise<never> => {
    // Server-side: reject without any browser APIs
    if (typeof window === 'undefined') return Promise.reject(error);

    const status    = error.response?.status as number | undefined;
    const envelope  = error.response?.data as ApiErrorEnvelope | undefined;
    const errorCode = envelope?.code ?? (error.response?.data?.error_code as string | undefined);
    const message   = envelope?.message ?? 'An unexpected error occurred.';

    // ── DEVICE_REVOKED: hard logout regardless of HTTP status ──────────────────
    if (errorCode === 'DEVICE_REVOKED') {
      _hardLogout('/login?reason=device_revoked');
      return Promise.reject(error);
    }

    switch (status) {
      // ── 401 / 419: Session expired or CSRF mismatch ──────────────────────────
      case 401:
      case 419: {
        const isLoginEndpoint = (error.config?.url as string | undefined)?.includes('/login');
        const isOnLoginPage   = window.location.pathname.includes('/login');

        if (!isLoginEndpoint && !isOnLoginPage) {
          // Trigger Zustand logout action before redirect
          // Dynamic import avoids circular dependency at module init time
          import('@/store/useAuthStore').then(({ useAuthStore }) => {
            useAuthStore.getState().clearAuth();
          });
          _hardLogout('/login');
        }
        break;
      }

      // ── 402: Subscription required ────────────────────────────────────────────
      case 402: {
        const reason = errorCode ?? 'SUBSCRIPTION_REQUIRED';
        sessionStorage.setItem('fastpos_402_code', reason);

        const isBillingPage = window.location.pathname.includes('/billing');
        if (!isBillingPage) {
          window.location.href = '/business/billing';
        }
        break;
      }

      // ── 403: Access denied — throw so component can handle it ─────────────────
      case 403: {
        toast.error(message, { id: 'forbidden-toast' });
        return Promise.reject(new AccessDeniedError(message));
      }

      // ── 422: Validation — throw structured errors for React Hook Form ──────────
      case 422: {
        const fieldErrors = envelope?.errors ?? {};
        return Promise.reject(new ValidationError(fieldErrors, message));
      }

      // ── 429: Rate limit exceeded ──────────────────────────────────────────────
      case 429: {
        const retryAfter = error.response?.headers?.['retry-after'];
        const seconds    = retryAfter ? parseInt(retryAfter, 10) : null;
        const toastMsg   = seconds
          ? `Too many requests. Please try again in ${seconds}s.`
          : message;

        toast.error(toastMsg, { duration: 5_000, id: 'rate-limit-toast' });

        if (seconds) {
          useRateLimitStore.getState().setRateLimited(seconds);
        }
        break;
      }

      // ── 409: Database deadlock / resource conflict ─────────────────────────────
      case 409: {
        toast.error(message || 'System busy resolving a conflict. Please retry.', {
          id: 'conflict-toast',
        });
        break;
      }

      // ── 503: Maintenance mode ─────────────────────────────────────────────────
      case 503: {
        if (!window.location.pathname.includes('/maintenance')) {
          window.location.href = '/maintenance';
        }
        break;
      }

      // ── 5xx: Generic server error ─────────────────────────────────────────────
      default: {
        if (status !== undefined && status >= 500 && status !== 503) {
          toast.error(message || 'A server error occurred. Please try again later.', {
            id: 'server-error-toast',
          });
        }
        break;
      }
    }

    return Promise.reject(error);
  },
);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Clears all FastPOS auth artifacts from browser storage and navigates.
 * Called on 401/419 and DEVICE_REVOKED. Never throws.
 */
function _hardLogout(redirectPath: string): void {
  try {
    ['fastpos_token', 'fastpos_user', 'pos_license_key', 'pos_hardware_hash'].forEach(
      (key) => localStorage.removeItem(key),
    );
    ['fastpos_token', 'fastpos_user'].forEach(
      (key) => sessionStorage.removeItem(key),
    );
  } finally {
    window.location.href = redirectPath;
  }
}

export default apiClient;
