<?php

namespace App\Domain\Tenant\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * ECDSA License Key Service  (Gate 2)
 * ─────────────────────────────────────────────────────────────────────────────
 * Generates and verifies cryptographically signed license tokens.
 *
 * Token anatomy (Base64Url-encoded JSON envelope):
 *
 *   {
 *     "payload": {                   ← the claims object
 *       "tenant_id":    int,
 *       "plan_id":      int,
 *       "type":         "web"|"hybrid"|"mobile",
 *       "features":     string[],
 *       "issued_at":    ISO-8601,
 *       "expires_at":   ISO-8601,
 *       "hardware_hash": string|null
 *     },
 *     "signature": "<hex ECDSA signature of sha256(canonical-payload)>"
 *   }
 *
 * Keys are stored in config/app (pulled from env):
 *   LICENSE_PRIVATE_KEY  – PEM ECDSA private key  (prime256v1 / P-256)
 *   LICENSE_PUBLIC_KEY   – PEM ECDSA public  key
 *
 * Generate a fresh keypair (run once, then store output in .env):
 *   php artisan license:generate-keypair
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LicenseKeyService
{
    // ─── Feature map per plan type ────────────────────────────────────────────

    private const FEATURE_MAP = [
        'web'    => ['pos', 'inventory', 'reporting', 'crm', 'multi_location'],
        'hybrid' => ['pos', 'inventory', 'reporting', 'crm', 'multi_location', 'offline_sync', 'device_locking'],
        'mobile' => ['pos', 'mobile_native', 'offline_sync', 'device_locking'],
    ];

    // ─── Constructor ──────────────────────────────────────────────────────────

    public function __construct(
        private readonly ?string $privateKeyPem = null,
        private readonly ?string $publicKeyPem  = null,
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Generate a signed ECDSA license token.
     *
     * @param  int         $tenantId
     * @param  int         $planId
     * @param  string      $type          'web' | 'hybrid' | 'mobile'
     * @param  Carbon|null $expiresAt     Defaults to +1 month
     * @param  string|null $hardwareHash  Optional device binding hash
     * @return string  Base64Url-encoded signed token
     */
    public function generateKey(
        int     $tenantId,
        int     $planId,
        string  $type          = 'web',
        ?Carbon $expiresAt     = null,
        ?string $hardwareHash  = null,
    ): string {
        $type      = in_array($type, ['web', 'hybrid', 'mobile'], true) ? $type : 'web';
        $issuedAt  = Carbon::now();
        $expiresAt = $expiresAt ?? $issuedAt->copy()->addMonth();

        $payload = [
            'tenant_id'     => $tenantId,
            'plan_id'       => $planId,
            'type'          => $type,
            'features'      => self::FEATURE_MAP[$type] ?? self::FEATURE_MAP['web'],
            'issued_at'     => $issuedAt->toIso8601String(),
            'expires_at'    => $expiresAt->toIso8601String(),
            'hardware_hash' => $hardwareHash,
        ];

        $signature = $this->sign($payload);

        $envelope = [
            'payload'   => $payload,
            'signature' => $signature,
        ];

        return $this->base64UrlEncode(json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Verify a signed license token.
     *
     * @param  string $licenseKey   The Base64Url token returned by generateKey()
     * @return array{
     *   valid:    bool,
     *   expired:  bool,
     *   payload:  array|null,
     *   error:    string|null
     * }
     */
    public function verifyLicense(string $licenseKey): array
    {
        // 1. Decode envelope
        $json = $this->base64UrlDecode($licenseKey);
        if ($json === false) {
            return $this->failure('License token is not valid Base64Url.');
        }

        $envelope = json_decode($json, true);
        if (!is_array($envelope) || !isset($envelope['payload'], $envelope['signature'])) {
            return $this->failure('License token structure is malformed.');
        }

        $payload   = $envelope['payload'];
        $signature = $envelope['signature'];

        // 2. Verify ECDSA signature
        if (!$this->verify($payload, $signature)) {
            return $this->failure('License signature is invalid — possible tampering detected.');
        }

        // 3. Check expiry
        $expired = false;
        if (isset($payload['expires_at'])) {
            try {
                $expired = Carbon::parse($payload['expires_at'])->isPast();
            } catch (\Throwable) {
                return $this->failure('License expires_at field is unparseable.');
            }
        }

        return [
            'valid'   => !$expired,
            'expired' => $expired,
            'payload' => $payload,
            'error'   => $expired ? 'License has expired.' : null,
        ];
    }

    // ─── Signing ──────────────────────────────────────────────────────────────

    /**
     * Sign the payload with the ECDSA private key.
     * Returns a hex-encoded DER signature.
     */
    private function sign(array $payload): string
    {
        $privateKey = $this->loadPrivateKey();
        $data       = $this->canonicalizePayload($payload);

        $signatureBytes = '';
        $ok = openssl_sign($data, $signatureBytes, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$ok) {
            throw new RuntimeException('ECDSA signing failed: ' . openssl_error_string());
        }

        return bin2hex($signatureBytes);
    }

    /**
     * Verify the hex DER signature against the public key.
     */
    private function verify(array $payload, string $hexSignature): bool
    {
        try {
            $publicKey      = $this->loadPublicKey();
            $data           = $this->canonicalizePayload($payload);
            $signatureBytes = hex2bin($hexSignature);

            if ($signatureBytes === false) {
                return false;
            }

            $result = openssl_verify($data, $signatureBytes, $publicKey, OPENSSL_ALGO_SHA256);
            return $result === 1;
        } catch (\Throwable $e) {
            Log::warning('LicenseKeyService::verify failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ─── Key loading ──────────────────────────────────────────────────────────

    /**
     * Load the ECDSA private key from constructor arg or env/config.
     */
    private function loadPrivateKey(): \OpenSSLAsymmetricKey
    {
        $pem = $this->privateKeyPem ?? config('app.license_private_key') ?? env('LICENSE_PRIVATE_KEY');

        if (!$pem) {
            throw new RuntimeException(
                'LICENSE_PRIVATE_KEY is not set. Run: php artisan license:generate-keypair'
            );
        }

        // Env variables collapse newlines to literal \n — restore them.
        $pem = str_replace('\\n', "\n", $pem);

        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('Failed to load ECDSA private key: ' . openssl_error_string());
        }

        return $key;
    }

    /**
     * Load the ECDSA public key from constructor arg or env/config.
     */
    private function loadPublicKey(): \OpenSSLAsymmetricKey
    {
        $pem = $this->publicKeyPem ?? config('app.license_public_key') ?? env('LICENSE_PUBLIC_KEY');

        if (!$pem) {
            throw new RuntimeException(
                'LICENSE_PUBLIC_KEY is not set. Run: php artisan license:generate-keypair'
            );
        }

        $pem = str_replace('\\n', "\n", $pem);

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('Failed to load ECDSA public key: ' . openssl_error_string());
        }

        return $key;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Produce a deterministic, canonical JSON string of the payload
     * for signing (keys sorted, consistent serialisation).
     */
    private function canonicalizePayload(array $payload): string
    {
        ksort($payload);
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string|false
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        return base64_decode($padded, true);
    }

    private function failure(string $error): array
    {
        return ['valid' => false, 'expired' => false, 'payload' => null, 'error' => $error];
    }

    // ─── Static keypair generator (used by Artisan command) ──────────────────

    /**
     * Generate a fresh prime256v1 (P-256) ECDSA keypair.
     * Returns ['private_pem' => ..., 'public_pem' => ...].
     */
    public static function generateKeyPair(): array
    {
        $config = [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];

        $privateKey = openssl_pkey_new($config);
        if ($privateKey === false) {
            throw new RuntimeException('Failed to generate ECDSA keypair: ' . openssl_error_string());
        }

        openssl_pkey_export($privateKey, $privatePem);
        $details   = openssl_pkey_get_details($privateKey);
        $publicPem = $details['key'];

        return [
            'private_pem' => $privatePem,
            'public_pem'  => $publicPem,
        ];
    }
}
