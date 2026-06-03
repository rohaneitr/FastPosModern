import axios from 'axios';

// Use NEXT_PUBLIC_API_URL from .env.local; fall back to localhost:8002.
// Both the CORS allowed_origins and SANCTUM_STATEFUL_DOMAINS on the server
// must match whichever origin the browser actually uses, so we read it from
// one canonical place (the env var) rather than hard-coding it here.
const BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8002/api/v1';

const api = axios.create({
  baseURL: BASE_URL,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 15000,
});

// Request interceptor — attach Bearer token from localStorage on every request
api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('fastpos_token');
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
  }
  return config;
});

// Response interceptor — auto-logout on 401
// Storage is cleared BEFORE the redirect to prevent a race condition where
// the login page's useEffect sees a stale token and tries to re-redirect.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && typeof window !== 'undefined') {
      // Don't redirect if we're already making a login request
      const isLoginRequest = error.config?.url?.includes('/login');
      if (!isLoginRequest) {
        localStorage.removeItem('fastpos_token');
        localStorage.removeItem('fastpos_user');
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

export default api;
