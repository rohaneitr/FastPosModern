<?php

namespace App\Modules\Kernel;

use Illuminate\Support\Facades\Log;

class ModuleDependencyException extends \Exception {}

class ModuleManifest
{
    /**
     * Validate if a module can be activated based on its defined dependencies.
     *
     * @param string $slug
     * @param array $tenantActiveModules
     * @return bool
     * @throws ModuleDependencyException
     */
    public function validateDependencies(string $slug, array $tenantActiveModules): bool
    {
        $manifestPath = app_path("Modules/" . ucfirst($slug) . "/module.json");
        
        if (!file_exists($manifestPath)) {
            Log::warning("Module manifest not found for slug: {$slug}");
            // For testing purposes, we assume missing manifest means no dependencies
            return true; 
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        if (isset($manifest['dependencies'])) {
            foreach ($manifest['dependencies'] as $dep) {
                if (!in_array($dep, $tenantActiveModules)) {
                    throw new ModuleDependencyException("Cannot activate {$slug}. Missing core dependency: {$dep}");
                }
            }
        }

        return true;
    }
}
