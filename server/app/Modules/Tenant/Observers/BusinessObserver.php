<?php

namespace App\Modules\Tenant\Observers;

use App\Modules\Tenant\Models\Business;
use App\Models\ChartOfAccount;

class BusinessObserver
{
    /**
     * Handle the Business "created" event.
     */
    public function created(Business $business): void
    {
        // Define the standardized Chart of Accounts for a new tenant
        $systemAccounts = [
            ['code' => '1000', 'name' => 'Cash on Hand', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => '1300', 'name' => 'Inventory Asset', 'type' => 'asset'],
            
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '2200', 'name' => 'Tax Payable', 'type' => 'liability'],
            
            ['code' => '3000', 'name' => 'Owner Equity', 'type' => 'equity'],
            
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue'],
            
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            ['code' => '5100', 'name' => 'Discount Expense', 'type' => 'expense'],
            ['code' => '5200', 'name' => 'Cost Variance', 'type' => 'expense'],
            ['code' => '5300', 'name' => 'Cash Discrepancy Expense', 'type' => 'expense'],
            ['code' => '5400', 'name' => 'FX Rounding Variance / Gain & Loss', 'type' => 'revenue'],
        ];

        $now = now();
        $insertData = [];

        foreach ($systemAccounts as $account) {
            $insertData[] = [
                'business_id' => $business->id,
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ChartOfAccount::insert($insertData);
    }
}
