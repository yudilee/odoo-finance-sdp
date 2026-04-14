<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_rental_lines', function (Blueprint $table) {
            $table->decimal('rental_qty', 10, 2)->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_rental_lines', function (Blueprint $table) {
            $table->dropColumn('rental_qty');
        });
    }
};
