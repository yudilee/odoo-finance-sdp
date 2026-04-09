<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoice_drivers', 'invoice_others', 'invoice_rentals', 'invoice_vehicles'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'invoice_date_due')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->date('invoice_date_due')->nullable()->after('invoice_date');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['invoice_drivers', 'invoice_others', 'invoice_rentals', 'invoice_vehicles'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'invoice_date_due')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('invoice_date_due');
                });
            }
        }
    }
};
