
try {
    \Illuminate\Support\Facades\DB::beginTransaction();

    // 1. Identify the Super Admin
    $superAdminId = 1;

    // 2. Clear related tables first to avoid FK constraint violations
    $tablesToClear = [
        'support_tickets',
        'tenant_media',
        'logs',
        'device_activations',
        'licenses',
        'subscriptions',
        'user_devices',
        'user_profiles',
        'payments',
        'products',
        'categories',
        'brands',
        'contacts',
        'transactions',
        'expenses',
        'expense_categories',
        'attendances',
        'payrolls',
        'stock_transfers',
        'locations',
        'invoice_layouts',
        'announcements',
        'notifications',
        'audit_logs',
        'email_logs'
    ];

    foreach ($tablesToClear as $table) {
        if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
            \Illuminate\Support\Facades\DB::table($table)->delete();
            echo "Cleared $table\n";
        }
    }

    // 3. Clear businesses (tenants)
    if (\Illuminate\Support\Facades\Schema::hasTable('businesses')) {
        \Illuminate\Support\Facades\DB::table('businesses')->delete();
        echo "Cleared businesses\n";
    }

    // 4. Delete all users EXCEPT the Super Admin
    if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
        $deletedUsers = \Illuminate\Support\Facades\DB::table('users')->where('id', '!=', $superAdminId)->delete();
        echo "Deleted $deletedUsers users (kept Super Admin ID $superAdminId)\n";
    }

    \Illuminate\Support\Facades\DB::commit();
    echo "SUCCESS: Database cleanup complete.\n";
} catch (\Exception $e) {
    \Illuminate\Support\Facades\DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
