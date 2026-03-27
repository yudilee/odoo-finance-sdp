<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('odoo_id')->nullable()->index();
            $table->string('name')->unique();           // INVCR/2025/...
            $table->string('partner_name');              // Customer name
            $table->date('invoice_date');
            $table->string('payment_term')->nullable();  // e.g. "60 Days"
            $table->string('ref')->nullable();           // Customer Reference
            $table->string('journal_name')->default('Invoice Penjualan Kendaraan');
            $table->decimal('amount_untaxed', 15, 2)->default(0);
            $table->decimal('amount_tax', 15, 2)->default(0);
            $table->decimal('amount_total', 15, 2)->default(0);
            $table->string('partner_bank')->nullable();
            $table->string('manager_name')->nullable();  // BC Manager
            $table->string('spv_name')->nullable();      // BC SPV
            $table->text('partner_address')->nullable();
            $table->text('partner_address_complete')->nullable();
            $table->string('partner_npwp')->nullable();  // NPWP
            $table->timestamps();
        });

        Schema::create('invoice_vehicle_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_vehicle_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();  // VIN / Chassis from description
            $table->string('license_plate')->nullable();  // No Polisi from lot_id/name
            $table->string('product_name')->nullable();   // Vehicle model from product_id/name
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('price_unit', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_vehicle_lines');
        Schema::dropIfExists('invoice_vehicles');
    }
};
