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
        Schema::table('invoice_subscriptions', function (Blueprint $table) {
            $table->decimal('invoice_amount', 15, 2)->nullable()->after('price_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_subscriptions', function (Blueprint $table) {
            $table->dropColumn('invoice_amount');
        });
    }
};
