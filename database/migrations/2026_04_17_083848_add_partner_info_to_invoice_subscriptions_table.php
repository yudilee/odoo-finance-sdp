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
            $table->string('partner_npwp')->nullable()->after('partner_name');
            $table->text('partner_address')->nullable()->after('partner_npwp');
            $table->text('partner_address_complete')->nullable()->after('partner_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['partner_npwp', 'partner_address', 'partner_address_complete']);
        });
    }
};
