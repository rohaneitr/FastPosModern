<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Clean up legacy spaghetti tables
        Schema::dropIfExists('pharmacy_batch_transactions');
        Schema::dropIfExists('pharmacy_batches');

        // 2. Unify FEFO/FIFO tracking inside the core inventory ledger
        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->index()->after('unit_cost');
            $table->string('lot_number')->nullable()->index()->after('expiry_date');
        });

        // 3. Pharmacy Vertical: Medicine Metadata
        Schema::create('medicines_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('generic_name')->index();
            $table->string('strength')->nullable();
            $table->string('dosage_form')->nullable();
            $table->boolean('is_rx_required')->default(false);
            $table->timestamps();
        });

        // 4. Pharmacy Vertical: Prescriptions (Cart-level)
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('doctor_name')->nullable();
            $table->string('patient_id')->nullable();
            $table->string('file_path')->nullable(); // Uploaded Rx image
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Connect Prescriptions & Dosage to Transaction Lines
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete()->after('warranty_duration');
            $table->string('dosage_instructions')->nullable()->after('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->dropColumn(['prescription_id', 'dosage_instructions']);
        });

        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medicines_meta');

        Schema::table('inventory_layers', function (Blueprint $table) {
            $table->dropColumn(['expiry_date', 'lot_number']);
        });
    }
};
