<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('period_odoo_id')->unique(); // e.g. __export__.rental_period_invoice_1903_83e00859
            $table->integer('period_numeric_id')->nullable()->index(); // raw numeric odoo id

            // Sale Order fields
            $table->string('so_name')->nullable()->index();          // R/2025/00014
            $table->string('partner_name')->nullable();              // Customer display name
            $table->string('rental_status')->nullable();             // Reserved / Pickedup / Returned
            $table->string('rental_type')->nullable();               // Subscription / Retail
            $table->date('actual_start_rental')->nullable();
            $table->date('actual_end_rental')->nullable();
            $table->string('period_type')->nullable();               // Monthly / Weekly

            // Period fields
            $table->string('product_name')->nullable();              // Vehicle
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('invoice_date')->nullable()->index();
            $table->decimal('price_unit', 18, 2)->default(0);
            $table->string('rental_uom')->nullable();                // month / day

            // Invoice fields
            $table->string('invoice_name')->nullable();              // INVRS/2025/03192
            $table->string('invoice_ref')->nullable();               // Full display (with PO refs)
            $table->string('invoice_state')->nullable();             // draft / posted
            $table->string('payment_state')->nullable();             // paid / not_paid / partial

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_subscriptions');
    }
};
