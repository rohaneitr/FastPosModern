'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';
import { useAuthStore } from '@/store/useAuthStore';

interface User {
  id: number;
  username: string;
  email: string;
  roles?: { name: string }[];
  // Add other necessary user fields
}

interface AuthContextType {
  user: User | null;
  loading: boolean;
  login: (data: any, rememberMe: boolean) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    // Check cookie-based session on initialization
    const getCookie = (name: string) => {
      const value = `; ${document.cookie}`;
      const parts = value.split(`; ${name}=`);
      if (parts.length === 2) return parts.pop()?.split(';').shift();
      return null;
    };

    const sessionCookie = getCookie('fastpos_session');
    const storedUser = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');

    if (sessionCookie && storedUser) {
      try {
        const parsedUser = JSON.parse(storedUser);
        setUser(parsedUser);
        // Hydrate store properly so UI elements depending on useAuthStore work immediately
        const role = getCookie('fastpos_user_role');
        useAuthStore.getState().setAuth(
          parsedUser, 
          parsedUser.permissions || (role ? [role] : []), 
          parsedUser.business?.location_id || null
        );
      } catch {
        clearStorage();
      }
    } else if (!sessionCookie && storedUser) {
      // Token expired/removed but user data still remains
      clearStorage();
    }
    setLoading(false);
  }, []);

  const clearStorage = () => {
    sessionStorage.removeItem('fastpos_token');
    sessionStorage.removeItem('fastpos_user');
    localStorage.removeItem('fastpos_token');
    localStorage.removeItem('fastpos_user');
    document.cookie = 'fastpos_session=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    document.cookie = 'fastpos_user_role=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    document.cookie = 'fastpos_business_status=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    useAuthStore.getState().clearAuth();
  };

  const login = async (payload: any, rememberMe: boolean) => {
    const response = await api.post('/login', { ...payload, remember_me: rememberMe });
    
    const { user: userData, access_token: token } = response.data;
    
    clearStorage();

    const maxAge = rememberMe ? 'max-age=604800;' : ''; // 7 days or session-only
    document.cookie = `fastpos_session=${token}; path=/; ${maxAge}`;
    
    const role = userData?.roles?.[0]?.name || '';
    document.cookie = `fastpos_user_role=${role}; path=/; ${maxAge}`;
    
    // Optional: Keep user object in localStorage for rich profile data
    if (rememberMe) {
      localStorage.setItem('fastpos_user', JSON.stringify(userData));
    } else {
      sessionStorage.setItem('fastpos_user', JSON.stringify(userData));
    }
    
    if (userData?.business?.status) {
      document.cookie = `fastpos_business_status=${userData.business.status}; path=/; ${maxAge}`;
    }

    useAuthStore.getState().setAuth(userData, userData?.permissions || (role ? [role] : []), userData?.business?.location_id || null);
    setUser(userData);
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } catch (err) {
    } finally {
      clearStorage();
      setUser(null);
      window.location.href = '/login';
    }
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
