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
        Schema::table('print_logs', function (Blueprint $table) {
            $table->string('print_mode')->default('default')->after('invoice_name');
            $table->dropUnique(['invoice_name']);
            $table->unique(['invoice_name', 'print_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('print_logs', function (Blueprint $table) {
            $table->dropUnique(['invoice_name', 'print_mode']);
            $table->unique(['invoice_name']);
            $table->dropColumn('print_mode');
        });
    }
};
