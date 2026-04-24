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
            $table->dateTime('actual_start')->nullable()->change();
            $table->dateTime('actual_end')->nullable()->change();
        });

        Schema::table('invoice_subscriptions', function (Blueprint $table) {
            $table->dateTime('actual_start_rental')->nullable()->change();
            $table->dateTime('actual_end_rental')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_rental_lines', function (Blueprint $table) {
            $table->date('actual_start')->nullable()->change();
            $table->date('actual_end')->nullable()->change();
        });

        Schema::table('invoice_subscriptions', function (Blueprint $table) {
            $table->date('actual_start_rental')->nullable()->change();
            $table->date('actual_end_rental')->nullable()->change();
        });
    }
};
