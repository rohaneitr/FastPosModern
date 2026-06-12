<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('total_before_tax', 22, 4)->default(0)->after('status');
            $table->decimal('tax_amount', 22, 4)->default(0)->after('total_before_tax');
            $table->decimal('discount_amount', 22, 4)->default(0)->after('tax_amount');
            $table->string('discount_type')->nullable()->after('discount_amount');
            $table->decimal('shipping_charges', 22, 4)->default(0)->after('discount_type');
            $table->decimal('grand_total', 22, 4)->default(0)->change();
            $table->decimal('amount_due', 22, 4)->default(0)->after('grand_total');
            $table->string('payment_status')->default('due')->after('amount_due');
        });

        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->decimal('item_tax', 22, 4)->default(0)->after('purchase_price');
            $table->decimal('quantity', 22, 4)->change();
            $table->decimal('purchase_price', 22, 4)->change();
            $table->decimal('sub_total', 22, 4)->change();
        });

        Schema::create('supplier_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->decimal('amount', 22, 4);
            $table->string('type'); // credit, debit
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ledgers');

        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn('item_tax');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'total_before_tax', 'tax_amount', 'discount_amount', 'discount_type',
                'shipping_charges', 'amount_due', 'payment_status'
            ]);
        });
    }
};
