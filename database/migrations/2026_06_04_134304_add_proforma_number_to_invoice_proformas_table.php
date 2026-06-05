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
        Schema::table('invoice_proformas', function (Blueprint $table) {
            $table->string('proforma_number')->nullable()->after('odoo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_proformas', function (Blueprint $table) {
            $table->dropColumn('proforma_number');
        });
    }
};
