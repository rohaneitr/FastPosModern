import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import { DeviceSecurityCore } from './DeviceSecurityCore';
import { DeviceEventEmitter } from 'react-native';

export const BASE_URL = 'http://localhost:8002/api/v1';

const apiClient = axios.create({
  baseURL: BASE_URL,
  timeout: 10000,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Request Interceptor: Inject Token & Hardware Fingerprint
apiClient.interceptors.request.use(
  async (config) => {
    try {
      const token = await SecureStore.getItemAsync('user_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }

      const fingerprint = await DeviceSecurityCore.getDeviceFingerprint();
      config.headers['X-Device-Fingerprint'] = fingerprint;
    } catch (e) {
      console.error('Failed to append security headers', e);
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response Interceptor: 401 Zombie Session Ejector
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // 1. Purge Local Auth States
      await SecureStore.deleteItemAsync('user_token');
      await SecureStore.deleteItemAsync('user_profile');

      // 2. Broadcast Ejection Event to Root Navigator
      DeviceEventEmitter.emit('auth-eject', { reason: 'UNAUTHORIZED_OR_REVOKED' });
    }
    return Promise.reject(error);
  }
);

export default apiClient;
