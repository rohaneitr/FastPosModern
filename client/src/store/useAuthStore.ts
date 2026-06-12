import { create } from 'zustand';

interface AuthState {
  user: any;
  permissions: string[];
  location_id: number | null;
  setAuth: (user: any, permissions: string[], location_id: number | null) => void;
  clearAuth: () => void;
  hasPermission: (permission: string) => boolean;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  permissions: [],
  location_id: null,
  setAuth: (user, permissions, location_id) => set({ user, permissions, location_id }),
  clearAuth: () => set({ user: null, permissions: [], location_id: null }),
  hasPermission: (permission: string) => {
    const { permissions } = get();
    // SuperAdmin or BusinessAdmin bypass
    if (permissions.includes('*') || permissions.includes('all')) return true;
    return permissions.includes(permission);
  },
}));
