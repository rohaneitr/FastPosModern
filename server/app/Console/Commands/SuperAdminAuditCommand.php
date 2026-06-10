<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class SuperAdminAuditCommand extends Command
{
    protected $signature = 'superadmin:audit {--domain=http://localhost:8000}';
    protected $description = 'Perform a functional ping sweep of all SuperAdmin API endpoints to guarantee 200 OK statuses.';

    public function handle()
    {
        $this->info("Initiating Phase 15: SuperAdmin Functional Lockdown Audit...");
        $domain = rtrim($this->option('domain'), '/');

        // We need a valid SuperAdmin token. For this CLI script, we will create a temporary one.
        $superAdmin = \App\Models\User::where('role', 'SuperAdmin')->first();
        if (!$superAdmin) {
            $this->error("CRITICAL FAILURE: No SuperAdmin user found in the database.");
            return 1;
        }

        $token = $superAdmin->createToken('superadmin-audit-cli')->plainTextToken;
        $this->info("Generated CLI Token for User: {$superAdmin->email}");

        // Fetch all registered routes
        $routes = collect(Route::getRoutes())->filter(function ($route) {
            return str_contains($route->uri(), 'api/v1/superadmin') && in_array('GET', $route->methods());
        });

        $this->info("Found " . $routes->count() . " SuperAdmin GET endpoints to sweep.");

        $failures = 0;

        foreach ($routes as $route) {
            // Replace simple parameters like {id} with '1' for a basic head check.
            $uri = preg_replace('/\{[a-zA-Z0-9_]+\}/', '1', $route->uri());
            $url = $domain . '/' . ltrim($uri, '/');

            $response = Http::withToken($token)->withHeaders([
                'Accept' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                $this->line("<fg=green>[OK 200]</> {$uri}");
            } else {
                $failures++;
                $status = $response->status();
                $this->line("<fg=red>[FAIL {$status}]</> {$uri}");
                $this->error("Response: " . $response->body());
            }
        }

        // Clean up token
        $superAdmin->tokens()->where('name', 'superadmin-audit-cli')->delete();

        if ($failures > 0) {
            $this->error("\nAUDIT FAILED: $failures endpoint(s) returned non-200 responses.");
            return 1;
        }

        $this->info("\nAUDIT PASSED: All SuperAdmin API endpoints are fully operational.");
        return 0;
    }
}
