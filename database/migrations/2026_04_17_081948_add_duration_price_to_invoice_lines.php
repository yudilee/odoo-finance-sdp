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
        Schema::table('invoice_driver_lines', function (Blueprint $table) {
            $table->decimal('duration_price', 15, 2)->nullable()->after('price_unit');
        });

        Schema::table('invoice_other_lines', function (Blueprint $table) {
            $table->decimal('duration_price', 15, 2)->nullable()->after('price_unit');
        });

        Schema::table('invoice_rental_lines', function (Blueprint $table) {
            $table->decimal('duration_price', 15, 2)->nullable()->after('price_unit');
        });

        Schema::table('invoice_vehicle_lines', function (Blueprint $table) {
            $table->decimal('duration_price', 15, 2)->nullable()->after('price_unit');
        });

        Schema::table('invoice_subscriptions', function (Blueprint $table) {
            $table->decimal('duration_price', 15, 2)->nullable()->after('price_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_driver_lines', function (Blueprint $table) {
            $table->dropColumn('duration_price');
        });

        Schema::table('invoice_other_lines', function (Blueprint $table) {
            $table->dropColumn('duration_price');
        });

        Schema::table('invoice_rental_lines', function (Blueprint $table) {
            $table->dropColumn('duration_price');
        });

        Schema::table('invoice_vehicle_lines', function (Blueprint $table) {
            $table->dropColumn('duration_price');
        });

        Schema::table('invoice_subscriptions', function (Blueprint $table) {
            $table->dropColumn('duration_price');
        });
    }
};
