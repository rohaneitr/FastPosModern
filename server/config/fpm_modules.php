<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FastPOS Module Registry
    |--------------------------------------------------------------------------
    |
    | Master dictionary of all modules available in the platform.
    | The superadmin panel reads this to dynamically render assignment checkboxes.
    |
    */

    'core' => [
        'name' => 'Core POS',
        'slug' => 'core',
        'description' => 'The essential POS and Inventory engine.',
        'is_default' => true,
    ],
    'crm' => [
        'name' => 'CRM & Loyalty',
        'slug' => 'crm',
        'description' => 'Advanced customer tracking, store credit, and bulk messaging.',
        'is_default' => false,
    ],
    'hr' => [
        'name' => 'HR Management',
        'slug' => 'hr',
        'description' => 'Staff scheduling, payroll, and advanced permissions.',
        'is_default' => false,
    ],
    'serial_tracking' => [
        'name' => 'Serial & IMEI Tracking',
        'slug' => 'serial_tracking',
        'description' => 'Advanced item-level tracking for electronics and warranties.',
        'is_default' => false,
    ],
    'pharmacy' => [
        'name' => 'Pharmacy Vertical',
        'slug' => 'pharmacy',
        'description' => 'Prescription management, batch expiry, and FEFO routing.',
        'is_default' => false,
    ],
    'restaurant' => [
        'name' => 'Restaurant Vertical',
        'slug' => 'restaurant',
        'description' => 'KDS, table management, and recipe-based inventory depletion.',
        'is_default' => false,
    ],
    'hardware_builder' => [
        'name' => 'PC/Hardware Builder',
        'slug' => 'hardware_builder',
        'description' => 'Compatibility engines for custom PC or CCTV assembly.',
        'is_default' => false,
    ],
    'manufacturing' => [
        'name' => 'Manufacturing',
        'slug' => 'manufacturing',
        'description' => 'Raw material conversion, wastage tracking, and BOMs.',
        'is_default' => false,
    ],
];
