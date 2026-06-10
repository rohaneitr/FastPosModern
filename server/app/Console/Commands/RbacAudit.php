<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class RbacAudit extends Command
{
    protected $signature = 'rbac:audit';
    protected $description = 'Full RBAC forensic audit across DB, routes, codebase';

    public function handle()
    {
        $this->info("Starting RBAC forensic audit...");

        $dbPermissions = $this->getDbPermissions();
        $routePermissions = $this->getRoutePermissions();
        $codePermissions = $this->getCodePermissions();

        $this->table(['SOURCE', 'COUNT'], [
            ['DB Permissions', count($dbPermissions)],
            ['Route Middleware', count($routePermissions)],
            ['Code References', count($codePermissions)],
        ]);

        $this->compare($dbPermissions, $routePermissions, $codePermissions);
    }

    private function getDbPermissions()
    {
        try {
            return DB::table('permissions')->pluck('name')->toArray();
        } catch (\Exception $e) {
            return []; // Return empty if tables aren't migrated
        }
    }

    private function getRoutePermissions()
    {
        $routes = app('router')->getRoutes();
        $permissions = [];

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            foreach ($middleware as $m) {
                if (is_string($m) && (str_contains($m, 'permission:') || str_contains($m, 'role_or_permission:'))) {
                    // Extract the permission name from the string (e.g. permission:view_reports)
                    $parts = explode(':', $m);
                    if (count($parts) > 1) {
                        $perms = explode('|', $parts[1]);
                        foreach($perms as $p) {
                            $permissions[] = trim($p);
                        }
                    }
                }
            }
        }

        return array_unique($permissions);
    }

    private function getCodePermissions()
    {
        $path = base_path('app');
        $files = File::allFiles($path);

        $matches = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            
            $content = File::get($file->getPathname());

            preg_match_all('/(?:permission|can|Gate::authorize)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i', $content, $found);

            if (!empty($found[1])) {
                $matches = array_merge($matches, $found[1]);
            }
        }

        return array_unique($matches);
    }

    private function compare($db, $routes, $code)
    {
        $this->info("\n=== DESYNC REPORT ===");

        $all = array_unique(array_merge($db, $routes, $code));

        foreach ($all as $perm) {
            $inDb = in_array($perm, $db);
            $inRoute = in_array($perm, $routes);
            $inCode = in_array($perm, $code);

            $status =
                ($inDb ? "DB " : "") .
                ($inRoute ? "ROUTE " : "") .
                ($inCode ? "CODE " : "");

            if ($status === "DB ROUTE CODE ") {
                $this->line("<fg=green>$perm => SYNCED</>");
            } else {
                $this->line("<fg=red>$perm => DESYNCED [$status]</>");
            }
        }
    }
}
