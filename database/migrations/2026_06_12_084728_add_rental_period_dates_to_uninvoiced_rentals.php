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
        Schema::table('uninvoiced_rentals', function (Blueprint $table) {
            $table->string('start_rental_period')->nullable()->after('tanggal_periode_belum_cetak');
            $table->string('end_rental_period')->nullable()->after('start_rental_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uninvoiced_rentals', function (Blueprint $table) {
            $table->dropColumn(['start_rental_period', 'end_rental_period']);
        });
    }
};
