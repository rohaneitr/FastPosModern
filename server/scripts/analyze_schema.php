<?php
$json = file_get_contents('schema_dump.json');
$schema = json_decode($json, true);

$mermaid = "erDiagram\n";
$relationships = "";

$tablesWithoutTenant = [];
// Based on typical SaaS POS, business_id is the tenant id.
$coreTablesList = ['products', 'transactions', 'contacts', 'invoices', 'users', 'cash_registers', 'inventory_layers', 'transaction_lines', 'transaction_items', 'purchases'];

$markdown = "# Database Analysis\n\n";

foreach ($schema as $tableName => $data) {
    if ($tableName === 'migrations' || $tableName === 'personal_access_tokens' || $tableName === 'password_reset_tokens' || $tableName === 'failed_jobs') {
        continue; // skip laravel internals
    }
    
    $mermaid .= "    {$tableName} {\n";
    $hasTenantId = false;
    
    foreach ($data['columns'] as $col) {
        $type = preg_replace('/[^a-zA-Z0-9_]/', '', $col['data_type']);
        $mermaid .= "        {$type} {$col['column_name']}\n";
        if (in_array($col['column_name'], ['business_id', 'tenant_id', 'company_id'])) {
            $hasTenantId = true;
        }
    }
    $mermaid .= "    }\n";
    
    foreach ($data['foreign_keys'] as $fk) {
        // Simple relationship representation
        $relationships .= "    {$fk['foreign_table_name']} ||--o{ {$tableName} : \"{$fk['column_name']}\"\n";
    }

    // Check tenant column
    $isCore = false;
    foreach ($coreTablesList as $core) {
        if (str_contains($tableName, $core)) $isCore = true;
    }
    
    if ($isCore && !$hasTenantId) {
        $tablesWithoutTenant[] = $tableName;
    }
}

$markdown .= "## Entity-Relationship Diagram\n\n```mermaid\n" . $mermaid . $relationships . "```\n\n";

$markdown .= "## Multi-tenancy Audit\n\n";
$markdown .= "The following core tables are missing a `business_id` (or `tenant_id` / `company_id`) column:\n";
if (empty($tablesWithoutTenant)) {
    $markdown .= "- All audited core tables have a tenant identifier.\n";
} else {
    foreach ($tablesWithoutTenant as $t) {
        $markdown .= "- **{$t}**\n";
    }
}

file_put_contents('analysis_result.txt', $markdown);
echo "Analysis done.\n";
