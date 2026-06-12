import { describe, it, expect, beforeEach, jest } from '@jest/globals';
import { DeviceEventEmitter } from 'react-native';
import { DeviceSecurityCore } from '../src/core/DeviceSecurityCore';
import apiClient from '../src/core/apiClient';
import MockAdapter from 'axios-mock-adapter';

// Mock dependencies
jest.mock('expo-application', () => ({
  androidId: 'mock-android-id-12345',
  getIosIdForVendorAsync: jest.fn(() => Promise.resolve('mock-ios-vendor-id')),
}));

jest.mock('expo-crypto', () => ({
  CryptoDigestAlgorithm: { SHA256: 'SHA-256' },
  digestStringAsync: jest.fn((algo: any, raw: any) => Promise.resolve(`hashed_${raw}`)),
}));

const mockSecureStore: Record<string, string> = {};
jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn((key: any) => Promise.resolve(mockSecureStore[key] || null)),
  setItemAsync: jest.fn((key: any, val: any) => {
    mockSecureStore[key] = val;
    return Promise.resolve();
  }),
  deleteItemAsync: jest.fn((key: any) => {
    delete mockSecureStore[key];
    return Promise.resolve();
  }),
}));

// Setup Axios Mock
const mockAxios = new MockAdapter(apiClient);

describe('Mobile Core Architecture', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    Object.keys(mockSecureStore).forEach(key => delete mockSecureStore[key]);
  });

  it('Test Case 1: Fingerprint Immutability', async () => {
    // Execute three times
    const hash1 = await DeviceSecurityCore.getDeviceFingerprint();
    const hash2 = await DeviceSecurityCore.getDeviceFingerprint();
    const hash3 = await DeviceSecurityCore.getDeviceFingerprint();

    // Assert absolute consistency
    expect(hash1).toEqual(hash2);
    expect(hash2).toEqual(hash3);

    // Verify it relies on SecureStore for the 2nd and 3rd calls
    const { CryptoDigestAlgorithm, digestStringAsync } = require('expo-crypto');
    expect(digestStringAsync).toHaveBeenCalledTimes(1); 
    
    // Assert structure
    expect(hash1).toContain('hashed_');
  });

  it('Test Case 2: The 401 Auto-Eject', async () => {
    // Setup Spy on DeviceEventEmitter
    const emitSpy = jest.spyOn(DeviceEventEmitter, 'emit');

    // Pre-populate pseudo local storage
    mockSecureStore['user_token'] = 'dead-token';
    mockSecureStore['user_profile'] = 'dead-profile';

    // Mock an API endpoint returning 401
    mockAxios.onGet('/protected-route').reply(401, { message: 'Unauthorized' });

    // Attempt Fetch
    try {
      await apiClient.get('/protected-route');
    } catch (error) {
      // Expected to fail
    }

    // Assert Local Storage Purged
    expect(mockSecureStore['user_token']).toBeUndefined();
    expect(mockSecureStore['user_profile']).toBeUndefined();

    // Assert Exact Navigation Event Emitted
    expect(emitSpy).toHaveBeenCalledWith('auth-eject', { reason: 'UNAUTHORIZED_OR_REVOKED' });
  });
});
