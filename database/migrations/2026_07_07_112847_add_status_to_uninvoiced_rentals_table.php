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
            $table->string('status')->nullable()->after('nomor_so');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uninvoiced_rentals', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
