<?php

namespace App\Modules\Finance\Services;

use App\Models\ChartOfAccount;

class TenantAccountResolver
{
    public const CASH = '1000';
    public const AR = '1200';
    public const INVENTORY = '1300';
    public const AP = '2000';
    public const TAX_PAYABLE = '2200';
    public const SALES = '4000';
    public const COGS = '5000';
    public const DISCOUNT = '5100';
    public const COST_VARIANCE = '5200';
    public const CASH_DISCREPANCY = '5300';

    /**
     * Request-level cache to prevent repeated DB hits for the same tenant/account pair
     * 
     * @var array
     */
    protected static array $resolvedCache = [];

    /**
     * Get the ID of the requested system account for the given business.
     * Throws an exception if the account is missing to ensure ledger integrity.
     */
    public static function resolve(int $businessId, string $code): int
    {
        $cacheKey = "{$businessId}_{$code}";

        if (isset(self::$resolvedCache[$cacheKey])) {
            return self::$resolvedCache[$cacheKey];
        }

        $account = ChartOfAccount::where('business_id', $businessId)
            ->where('code', $code)
            ->first();

        if (!$account) {
            throw new \RuntimeException("Critical Ledger Error: Missing system account [$code] for Business [$businessId]. Ensure tenant provisioning completed successfully.");
        }

        self::$resolvedCache[$cacheKey] = $account->id;

        return $account->id;
    }

    /**
     * Clear the cache (useful for long-running processes or test environments).
     */
    public static function clearCache(): void
    {
        self::$resolvedCache = [];
    }
}
