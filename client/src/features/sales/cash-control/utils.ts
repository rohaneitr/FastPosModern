/**
 * Generates or retrieves a persistent, cryptographically secure device fingerprint.
 * Binds the session to the local browser instance using native Web Crypto APIs.
 */
export async function getOrCreateDeviceFingerprint(): Promise<string> {
    const STORAGE_KEY = 'fpm_device_id';
    const storedHash = localStorage.getItem(STORAGE_KEY);
    
    if (storedHash) {
        return storedHash;
    }

    // Construct a deterministic base vector
    const userAgent = navigator.userAgent;
    const language = navigator.language;
    const screenRes = `${window.screen.width}x${window.screen.height}`;
    const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    
    // Inject UUIDv4 to guarantee uniqueness across identical hardware setups
    const uniqueHardwareToken = crypto.randomUUID();

    const fingerprintPayload = `${userAgent}|${language}|${screenRes}|${timeZone}|${uniqueHardwareToken}`;

    // Hash payload securely using SHA-256
    const encoder = new TextEncoder();
    const data = encoder.encode(fingerprintPayload);
    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
    
    // Convert buffer to hex string
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map(byte => byte.toString(16).padStart(2, '0')).join('');
    
    localStorage.setItem(STORAGE_KEY, hashHex);
    return hashHex;
}
