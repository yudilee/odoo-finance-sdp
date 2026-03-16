<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('odoo_id')->nullable(); // __export__ ID
            $table->date('date');
            $table->string('journal_name'); // e.g. "Kas Jakarta"
            $table->string('move_name'); // e.g. "KJKT/2026/00427"
            $table->string('partner_name')->nullable();
            $table->string('ref')->nullable();
            $table->decimal('amount_total_signed', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index('date');
            $table->index('journal_name');
            $table->index('move_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
