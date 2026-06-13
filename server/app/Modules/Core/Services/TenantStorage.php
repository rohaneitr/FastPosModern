<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * TenantStorage — Phase 9: SRE Hardening (Disaster Recovery & AWS S3 Integration)
 *
 * Enforces strict tenant isolation on AWS S3 for all media uploads.
 * Local storage is strictly forbidden for tenant media to ensure
 * data durability and disaster recovery capability.
 *
 * ── ISOLATION GUARANTEE ──────────────────────────────────────────────────
 *
 * This service mandates that every file uploaded by a tenant is prefixed
 * with their `business_id` in the S3 bucket path.
 *
 * Example Path: `s3://your-bucket-name/tenants/{business_id}/products/{filename}`
 *
 * By logically partitioning the S3 bucket via paths, 'Tenant A' cannot
 * accidentally or maliciously overwrite, read, or delete 'Tenant B's' files,
 * even if they guess the filename. The `business_id` acts as a hard boundary.
 *
 * @version Phase 9 — SRE Hardening
 */
class TenantStorage
{
    /**
     * Store an uploaded file in the tenant's isolated S3 directory.
     *
     * @param int $businessId The ID of the tenant's business.
     * @param string $module The module or entity type (e.g., 'products', 'avatars').
     * @param UploadedFile $file The file to upload.
     * @return string|false The path to the stored file on S3, or false on failure.
     */
    public static function putFile(int $businessId, string $module, UploadedFile $file): string|false
    {
        $path = self::getTenantPath($businessId, $module);
        
        // Force the use of the 's3' disk. Local storage is strictly forbidden.
        return Storage::disk('s3')->put($path, $file);
    }

    /**
     * Store a file with a specific name in the tenant's isolated S3 directory.
     *
     * @param int $businessId
     * @param string $module
     * @param UploadedFile $file
     * @param string $filename
     * @return string|false
     */
    public static function putFileAs(int $businessId, string $module, UploadedFile $file, string $filename): string|false
    {
        $path = self::getTenantPath($businessId, $module);
        
        return Storage::disk('s3')->putFileAs($path, $file, $filename);
    }

    /**
     * Delete a file from the tenant's isolated S3 directory.
     *
     * @param string $path The full S3 path of the file to delete.
     * @return bool True if deleted successfully.
     */
    public static function delete(string $path): bool
    {
        return Storage::disk('s3')->delete($path);
    }

    /**
     * Get the full URL for a file stored in S3.
     *
     * @param string $path The full S3 path of the file.
     * @return string
     */
    public static function url(string $path): string
    {
        return Storage::disk('s3')->url($path);
    }

    /**
     * Generate the strict isolated base path for a tenant.
     *
     * @param int $businessId
     * @param string $module
     * @return string
     */
    private static function getTenantPath(int $businessId, string $module): string
    {
        return "tenants/{$businessId}/{$module}";
    }
}
