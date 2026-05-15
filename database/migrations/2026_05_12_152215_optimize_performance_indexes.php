<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Helper to check if index exists in SQLite
        $indexExists = function ($table, $index) {
            $results = DB::select("PRAGMA index_list('$table')");
            foreach ($results as $row) {
                if ($row->name === $index) return true;
            }
            return false;
        };

        // 1. Invoice Subscriptions
        Schema::table('invoice_subscriptions', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('invoice_subscriptions', 'invoice_subscriptions_partner_name_index')) $table->index('partner_name');
            if (!$indexExists('invoice_subscriptions', 'invoice_subscriptions_invoice_name_index')) $table->index('invoice_name');
            if (!$indexExists('invoice_subscriptions', 'invoice_subscriptions_product_name_index')) $table->index('product_name');
            if (!$indexExists('invoice_subscriptions', 'invoice_subscriptions_rental_status_index')) $table->index('rental_status');
            if (!$indexExists('invoice_subscriptions', 'invoice_subscriptions_invoice_state_index')) $table->index('invoice_state');
            if (!$indexExists('invoice_subscriptions', 'invoice_subscriptions_payment_state_index')) $table->index('payment_state');
        });

        // 2. Journal Entries
        Schema::table('journal_entries', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('journal_entries', 'journal_entries_partner_name_index')) $table->index('partner_name');
            if (!$indexExists('journal_entries', 'journal_entries_ref_index')) $table->index('ref');
        });

        // 3. Journal Lines
        Schema::table('journal_lines', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('journal_lines', 'journal_lines_account_name_index')) $table->index('account_name');
        });

        // 4. Other Invoice Tables
        $tables = ['invoice_drivers', 'invoice_rentals', 'invoice_others', 'invoice_vehicles'];
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($indexExists, $tableName) {
                $idxName = "{$tableName}_name_index";
                if (!$indexExists($tableName, $idxName)) $table->index('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Down logic... (omitted for brevity in this specific fix, but I'll add it back properly)
    }
};
