<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the licenses.license_key column from VARCHAR(255) → TEXT
 * so it can hold the full Base64Url-encoded ECDSA token (~800+ chars).
 *
 * Also drops the UNIQUE index first (SQLite cannot ALTER a column with an
 * index in-place) then re-creates it on the new TEXT column.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support ALTER COLUMN directly, so we use a
        // table-rebuild pattern via doctrine/dbal-free column modification.
        Schema::table('licenses', function (Blueprint $table) {
            // Drop the unique index before changing the column type.
            // Laravel/SQLite will recreate it via the ->unique() call below.
            $table->dropUnique(['license_key']);
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->text('license_key')->change();
        });

        // Re-create the unique constraint on the TEXT column.
        // NOTE: SQLite only indexes the first 255 chars of TEXT for UNIQUE,
        // which is fine — token prefixes are already unique per tenant/time.
        Schema::table('licenses', function (Blueprint $table) {
            $table->unique('license_key');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropUnique(['license_key']);
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->string('license_key')->change();
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->unique('license_key');
        });
    }
};
