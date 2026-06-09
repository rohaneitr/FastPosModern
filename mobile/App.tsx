import React, { useState, useEffect } from 'react';
import { StatusBar } from 'expo-status-bar';
import AsyncStorage from '@react-native-async-storage/async-storage';
import LoginScreen from './src/screens/LoginScreen';
import POSScreen from './src/screens/POSScreen';

export default function App() {
  const [isLoggedIn, setIsLoggedIn] = useState<boolean | null>(null);

  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    const token = await AsyncStorage.getItem('auth_token');
    setIsLoggedIn(!!token);
  };

  // Show nothing while checking auth status
  if (isLoggedIn === null) return null;

  return (
    <>
      <StatusBar style="light" />
      {isLoggedIn ? (
        <POSScreen onLogout={() => setIsLoggedIn(false)} />
      ) : (
        <LoginScreen onLoginSuccess={() => setIsLoggedIn(true)} />
      )}
    </>
  );
}
