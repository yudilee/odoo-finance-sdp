<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('frequency')->default('daily');
            $table->string('time')->default('00:00');
            $table->integer('day_of_week')->nullable();
            $table->integer('day_of_month')->nullable();
            $table->text('remark')->nullable();
            // Pruning settings
            $table->boolean('prune_enabled')->default(true);
            $table->integer('keep_daily')->default(7);
            $table->integer('keep_weekly')->default(4);
            $table->integer('keep_monthly')->default(6);
            // Session cleanup
            $table->boolean('session_cleanup_enabled')->default(true);
            $table->integer('session_cleanup_days')->default(7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
