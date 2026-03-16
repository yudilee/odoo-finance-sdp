<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // 'odoo_manual', 'odoo_scheduled'
            $table->string('filename')->nullable();
            $table->timestamp('imported_at');
            $table->integer('items_count')->default(0);
            $table->json('summary_json')->nullable();
            $table->string('status')->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index('imported_at');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
