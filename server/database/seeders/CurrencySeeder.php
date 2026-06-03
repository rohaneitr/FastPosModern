<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'symbol_native' => '৳', 'decimal_digits' => 2, 'name_bn' => 'বাংলাদেশি টাকা'],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'symbol_native' => '$', 'decimal_digits' => 2, 'name_bn' => 'মার্কিন ডলার'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'symbol_native' => '€', 'decimal_digits' => 2, 'name_bn' => 'ইউরো'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'symbol_native' => '£', 'decimal_digits' => 2, 'name_bn' => 'ব্রিটিশ পাউন্ড'],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'symbol_native' => '₹', 'decimal_digits' => 2, 'name_bn' => 'ভারতীয় রুপি'],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'symbol_native' => 'د.إ', 'decimal_digits' => 2, 'name_bn' => 'আমিরাতি দিরহাম'],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'symbol_native' => 'ر.س', 'decimal_digits' => 2, 'name_bn' => 'সৌদি রিয়াল'],
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'symbol_native' => 'RM', 'decimal_digits' => 2, 'name_bn' => 'মালয়েশিয়ান রিঙ্গিত'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'symbol_native' => 'S$', 'decimal_digits' => 2, 'name_bn' => 'সিঙ্গাপুর ডলার'],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']],
                array_merge($currency, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Seed default exchange rates (USD base)
        $defaultRates = [
            'BDT' => 119.50,
            'USD' => 1.0,
            'EUR' => 0.92,
            'GBP' => 0.79,
            'INR' => 83.50,
            'AED' => 3.67,
            'SAR' => 3.75,
            'MYR' => 4.72,
            'SGD' => 1.35,
        ];

        foreach ($defaultRates as $target => $rate) {
            DB::table('exchange_rates')->updateOrInsert(
                ['base_currency' => 'USD', 'target_currency' => $target],
                [
                    'rate' => $rate,
                    'source' => 'manual',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
