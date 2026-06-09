import axios from 'axios';
import toast from 'react-hot-toast';
import { useRateLimitStore } from '../store/useRateLimitStore';

const getBaseUrl = () => {
  if (typeof window === 'undefined') {
    return 'http://backend:8000/api/v1';
  }
  return '/api/v1';
};

const BASE_URL = getBaseUrl();

const api = axios.create({
  baseURL: BASE_URL,
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 15000,
});

api.defaults.withCredentials = true;
api.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
api.defaults.headers.common['Accept'] = 'application/json';

// Request interceptor — attach hardware hash if present
api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const hwHash = localStorage.getItem('pos_hardware_hash');
    if (hwHash) {
      config.headers['X-Hardware-Hash'] = hwHash;
    }
  }
  return config;
});

// Response interceptor — handle 401 (auto-logout) and 402 (subscription wall)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (typeof window === 'undefined') return Promise.reject(error);

    const status = error.response?.status;
    const dataCode = error.response?.data?.code || error.response?.data?.error_code;

    // ── DEVICE REVOKED: Hard logout if the device session is kicked ──
    if (dataCode === 'DEVICE_REVOKED') {
      localStorage.removeItem('fastpos_token');
      localStorage.removeItem('fastpos_user');
      localStorage.removeItem('pos_license_key');
      localStorage.removeItem('pos_hardware_hash');
      sessionStorage.removeItem('fastpos_token');
      sessionStorage.removeItem('fastpos_user');
      if (typeof window !== 'undefined') {
        window.location.href = '/login?reason=device_revoked';
      }
      return Promise.reject(error);
    }

    // ── 403: Global RBAC Forbidden Handler ──
    if (status === 403) {
      if (typeof window !== 'undefined') {
        window.location.href = '/unauthorized';
      }
      return Promise.reject(error);
    }

    // ── 401 & 419: session expired or CSRF mismatch → clear storage and redirect to login ──────────
    if (status === 401 || status === 419) {
      const isLoginEndpoint = error.config?.url?.includes('/login');
      const isOnLoginPage = window.location.pathname.includes('/login');
      
      if (!isLoginEndpoint && !isOnLoginPage) {
        sessionStorage.removeItem('fastpos_token');
        sessionStorage.removeItem('fastpos_user');
        localStorage.removeItem('fastpos_token');
        localStorage.removeItem('fastpos_user');
        window.location.href = '/login';
      }
    }

    // ── 402: subscription issue → redirect to billing suspended ──────────────
    if (status === 402) {
      const errorCode: string = error.response?.data?.error_code ?? 'SUBSCRIPTION_REQUIRED';
      // Store the reason so the settings page can display it as a banner
      sessionStorage.setItem('fastpos_402_code', errorCode);
      
      const isBillingPage = window.location.pathname.includes('/billing');
      if (!isBillingPage) {
        window.location.href = '/business/billing';
      }
    }

    // ── 503: maintenance mode ──────────────
    if (status === 503) {
      if (!window.location.pathname.includes('/maintenance')) {
        window.location.href = '/maintenance';
      }
    }

    // ── 429: Rate Limiting ──────────────
    if (status === 429) {
      const retryAfter = error.response?.headers?.['retry-after'];
      const seconds = retryAfter ? parseInt(retryAfter, 10) : 60;
      
      if (typeof window !== 'undefined') {
        toast.error(`Too many requests. Please try again in ${seconds} seconds.`, {
          duration: 5000,
          id: 'rate-limit-toast', // Prevents duplicate toasts
        });
        
        useRateLimitStore.getState().setRateLimited(seconds);
      }
    }

    return Promise.reject(error);
  }
);

export default api;
