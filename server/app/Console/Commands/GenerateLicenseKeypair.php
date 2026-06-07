<?php

namespace App\Console\Commands;

use App\Domain\Tenant\Services\LicenseKeyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generates a prime256v1 ECDSA keypair and writes it to .env.
 *
 * Usage:
 *   php artisan license:generate-keypair
 *   php artisan license:generate-keypair --force   # overwrite existing keys
 */
class GenerateLicenseKeypair extends Command
{
    protected $signature   = 'license:generate-keypair {--force : Overwrite existing keys}';
    protected $description = 'Generate a prime256v1 ECDSA keypair for the license signing engine';

    public function handle(): int
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->error('.env file not found at: ' . $envPath);
            return self::FAILURE;
        }

        $envContent = File::get($envPath);

        // Guard against accidental overwrite
        if (
            !$this->option('force') &&
            (str_contains($envContent, 'LICENSE_PRIVATE_KEY=') || str_contains($envContent, 'LICENSE_PUBLIC_KEY='))
        ) {
            $this->warn('Keys already exist in .env. Use --force to overwrite.');
            return self::SUCCESS;
        }

        $this->info('🔐 Generating prime256v1 (P-256) ECDSA keypair…');

        try {
            ['private_pem' => $privatePem, 'public_pem' => $publicPem] = LicenseKeyService::generateKeyPair();
        } catch (\RuntimeException $e) {
            $this->error('Keypair generation failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Collapse newlines into literal \n for single-line .env storage
        $privateEnv = str_replace(["\r\n", "\n"], '\\n', trim($privatePem));
        $publicEnv  = str_replace(["\r\n", "\n"], '\\n', trim($publicPem));

        // Remove any existing lines and append fresh ones
        $envContent = preg_replace('/^LICENSE_PRIVATE_KEY=.*$/m', '', $envContent);
        $envContent = preg_replace('/^LICENSE_PUBLIC_KEY=.*$/m', '', $envContent);
        $envContent = rtrim($envContent) . "\n\n";
        $envContent .= "LICENSE_PRIVATE_KEY=\"{$privateEnv}\"\n";
        $envContent .= "LICENSE_PUBLIC_KEY=\"{$publicEnv}\"\n";

        File::put($envPath, $envContent);

        $this->info('✅ Keypair written to .env');
        $this->newLine();
        $this->line('<comment>Public Key (safe to share / embed in client apps):</comment>');
        $this->line($publicPem);

        $this->newLine();
        $this->warn('⚠️  PRIVATE KEY is now in .env — keep it secret, never commit it.');
        $this->newLine();
        $this->info('Run `php artisan config:clear` to apply the new keys.');

        return self::SUCCESS;
    }
}
