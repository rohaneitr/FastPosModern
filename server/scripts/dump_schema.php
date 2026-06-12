<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$schema = DB::connection()->getDatabaseName();
$driver = DB::connection()->getDriverName();

if ($driver === 'pgsql') {
    $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $schemaData = [];

    foreach ($tables as $table) {
        $tableName = $table->table_name;
        
        $columns = DB::select("SELECT column_name, data_type, character_maximum_length, is_nullable, column_default FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?", [$tableName]);
        
        $fks = DB::select("
            SELECT
                tc.constraint_name, 
                kcu.column_name, 
                ccu.table_name AS foreign_table_name,
                ccu.column_name AS foreign_column_name 
            FROM 
                information_schema.table_constraints AS tc 
                JOIN information_schema.key_column_usage AS kcu
                  ON tc.constraint_name = kcu.constraint_name
                  AND tc.table_schema = kcu.table_schema
                JOIN information_schema.constraint_column_usage AS ccu
                  ON ccu.constraint_name = tc.constraint_name
                  AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name = ?
        ", [$tableName]);

        $schemaData[$tableName] = [
            'columns' => $columns,
            'foreign_keys' => $fks
        ];
    }
    file_put_contents('schema_dump.json', json_encode($schemaData, JSON_PRETTY_PRINT));
    echo "Schema dumped successfully\n";
} else {
    echo "Unsupported driver: " . $driver . "\n";
}
