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
    // Check both storage types on initialization
    const storedToken = sessionStorage.getItem('fastpos_token') || localStorage.getItem('fastpos_token');
    const storedUser = sessionStorage.getItem('fastpos_user') || localStorage.getItem('fastpos_user');

    if (storedToken && storedUser) {
      try {
        setUser(JSON.parse(storedUser));
      } catch {
        clearStorage();
      }
    }
    setLoading(false);
  }, []);

  const clearStorage = () => {
    sessionStorage.removeItem('fastpos_token');
    sessionStorage.removeItem('fastpos_user');
    localStorage.removeItem('fastpos_token');
    localStorage.removeItem('fastpos_user');
    document.cookie = 'fastpos_business_status=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    useAuthStore.getState().clearAuth();
  };

  const login = async (payload: any, rememberMe: boolean) => {
    const response = await api.post('/login', { ...payload, remember_me: rememberMe });
    
    const { user: userData, access_token: token } = response.data;
    
    clearStorage();

    if (rememberMe) {
      localStorage.setItem('fastpos_user', JSON.stringify(userData));
      localStorage.setItem('fastpos_token', token);
    } else {
      sessionStorage.setItem('fastpos_user', JSON.stringify(userData));
      sessionStorage.setItem('fastpos_token', token);
    }
    
    if (userData?.business?.status) {
      document.cookie = `fastpos_business_status=${userData.business.status}; path=/`;
    }

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
