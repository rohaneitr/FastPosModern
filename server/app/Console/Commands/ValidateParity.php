<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ValidateParity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parity:validate {endpoint} {--legacy-url=} {--modern-url=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a parallel run validating API responses of old vs new systems.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $endpoint = $this->argument('endpoint');
        
        $legacyUrl = $this->option('legacy-url') ?? env('LEGACY_API_URL', 'http://localhost:8000/api');
        $modernUrl = $this->option('modern-url') ?? env('APP_URL', 'http://localhost:8001') . '/api/v1';

        $this->info("Starting Parallel Validation for endpoint: /{$endpoint}");
        $this->line("Legacy System: {$legacyUrl}");
        $this->line("Modern System: {$modernUrl}");
        $this->line("--------------------------------------------------");

        try {
            $this->info("Fetching from Legacy API...");
            // In a real scenario, you'd inject auth tokens here
            $legacyResponse = Http::timeout(10)->get("{$legacyUrl}/{$endpoint}");
            
            $this->info("Fetching from Modern API...");
            $modernResponse = Http::timeout(10)->get("{$modernUrl}/{$endpoint}");

        } catch (\Exception $e) {
            $this->error("Connection failed: " . $e->getMessage());
            return Command::FAILURE;
        }

        if (!$legacyResponse->successful()) {
            $this->warn("Legacy API returned status: " . $legacyResponse->status());
        }
        
        if (!$modernResponse->successful()) {
            $this->warn("Modern API returned status: " . $modernResponse->status());
        }

        $legacyData = $legacyResponse->json();
        $modernData = $modernResponse->json();

        // Perform diff analysis
        $this->info("Analyzing data structural parity...");

        if (is_array($legacyData) && is_array($modernData)) {
            $legacyCount = count($legacyData['data'] ?? $legacyData);
            $modernCount = count($modernData['data'] ?? $modernData);

            $this->line("Legacy Record Count: {$legacyCount}");
            $this->line("Modern Record Count: {$modernCount}");

            if ($legacyCount !== $modernCount) {
                $this->error("❌ FAIL: Record counts do not match!");
            } else {
                $this->info("✅ PASS: Record counts match.");
            }

            // Quick key mapping check on first record
            $legacyItem = ($legacyData['data'] ?? $legacyData)[0] ?? null;
            $modernItem = ($modernData['data'] ?? $modernData)[0] ?? null;

            if ($legacyItem && $modernItem) {
                $legacyKeys = array_keys($legacyItem);
                $modernKeys = array_keys($modernItem);
                
                $missingKeys = array_diff($legacyKeys, $modernKeys);
                if (count($missingKeys) > 0) {
                    $this->warn("⚠️ WARNING: Modern API is missing some legacy keys:");
                    foreach ($missingKeys as $key) {
                        $this->line(" - {$key}");
                    }
                } else {
                    $this->info("✅ PASS: Modern API contains all necessary legacy keys.");
                }
            }

        } else {
            $this->error("❌ FAIL: Invalid JSON response from one or both APIs.");
            return Command::FAILURE;
        }

        $this->line("--------------------------------------------------");
        $this->info("Validation Complete.");
        
        return Command::SUCCESS;
    }
}
