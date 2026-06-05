<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_proforma_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_proforma_id')->constrained()->cascadeOnDelete();
            $table->string('sale_order_id')->nullable();
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->string('uom')->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('rental_qty', 15, 2)->nullable();
            $table->decimal('price_unit', 15, 2)->default(0);
            $table->decimal('duration_price', 15, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->string('product_name')->nullable();
            $table->string('license_plate')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_proforma_lines');
    }
};
