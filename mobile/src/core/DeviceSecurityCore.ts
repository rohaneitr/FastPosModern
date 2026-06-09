import * as Application from 'expo-application';
import * as SecureStore from 'expo-secure-store';
import * as Crypto from 'expo-crypto';
import { Platform } from 'react-native';

const FINGERPRINT_KEY = 'STATIC_DEVICE_FINGERPRINT';

export class DeviceSecurityCore {
  /**
   * Retrieves or generates an immutable, SHA-256 hashed hardware footprint.
   */
  public static async getDeviceFingerprint(): Promise<string> {
    // 1. Check if we already minted and securely stored the hash
    let storedHash = await SecureStore.getItemAsync(FINGERPRINT_KEY);
    if (storedHash) {
      return storedHash;
    }

    // 2. Extract Native Static Identifiers
    let rawIdentifier = '';
    
    if (Platform.OS === 'android') {
      rawIdentifier = Application.androidId || 'fallback_android_id';
    } else {
      // iOS identifierForVendor can change on reinstall. 
      // We mint a fresh UUID and store it in Keychain for absolute persistence.
      const vendorId = await Application.getIosIdForVendorAsync();
      rawIdentifier = vendorId || `ios_${Date.now()}_${Math.random()}`;
    }

    // 3. Apply SHA-256 Cryptographic Hash
    const hashedFingerprint = await Crypto.digestStringAsync(
      Crypto.CryptoDigestAlgorithm.SHA256,
      rawIdentifier
    );

    // 4. Persist in Encrypted Keystore/Keychain
    await SecureStore.setItemAsync(FINGERPRINT_KEY, hashedFingerprint);

    return hashedFingerprint;
  }

  /**
   * Used strictly for absolute factory resets.
   */
  public static async purgeFingerprint(): Promise<void> {
    await SecureStore.deleteItemAsync(FINGERPRINT_KEY);
  }
}
