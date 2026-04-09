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
        Schema::table('invoice_rentals', function (Blueprint $table) {
            $table->string('contract_ref')->nullable()->after('ref');
        });

        Schema::table('invoice_vehicles', function (Blueprint $table) {
            $table->string('contract_ref')->nullable()->after('ref');
        });

        Schema::table('invoice_others', function (Blueprint $table) {
            $table->string('contract_ref')->nullable()->after('ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_rentals', function (Blueprint $table) {
            $table->dropColumn('contract_ref');
        });

        Schema::table('invoice_vehicles', function (Blueprint $table) {
            $table->dropColumn('contract_ref');
        });

        Schema::table('invoice_others', function (Blueprint $table) {
            $table->dropColumn('contract_ref');
        });
    }
};
