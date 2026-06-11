<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// Odoo Automatic Synchronization Schedule
Illuminate\Support\Facades\Schedule::command('app:sync-odoo')->when(function () {
    return \App\Models\Setting::getValue('odoo_schedule_enabled', 'false') === 'true';
})->everyMinute()->onOneServer()->runInBackground()->onSuccess(function () {
    \Illuminate\Support\Facades\Log::info('Automated Odoo Sync completed successfully.');
})->when(function() {
    $interval = \App\Models\Setting::getValue('odoo_schedule_interval', 'daily');
    $lastSync = \App\Models\Setting::getValue('odoo_last_sync');
    
    if (!$lastSync) return true; // Never synced before
    
    $lastSyncDate = \Illuminate\Support\Carbon::parse($lastSync);
    $now = now();
    
    return match($interval) {
        'every_30_minutes' => $lastSyncDate->diffInMinutes($now) >= 30,
        'hourly'         => $lastSyncDate->diffInHours($now) >= 1,
        'every_2_hours'  => $lastSyncDate->diffInHours($now) >= 2,
        'every_4_hours'  => $lastSyncDate->diffInHours($now) >= 4,
        'every_6_hours'  => $lastSyncDate->diffInHours($now) >= 6,
        'every_12_hours' => $lastSyncDate->diffInHours($now) >= 12,
        'daily'          => $lastSyncDate->diffInDays($now) >= 1,
        default          => $lastSyncDate->diffInDays($now) >= 1,
    };
});

// Database Maintenance (Vacuum, Analyze, Prune Logs)
Illuminate\Support\Facades\Schedule::command('db:maintenance')->dailyAt('03:00')->runInBackground();

// Auto Sync Uninvoiced Rentals every 30 minutes
Illuminate\Support\Facades\Schedule::command('app:sync-uninvoiced-rentals')->when(function () {
    return \App\Models\Setting::getValue('uninvoiced_rentals_auto_sync_enabled', 'false') === 'true';
})->everyThirtyMinutes()->runInBackground();

// Automatic Database Backups (Linked to UI Settings)
Illuminate\Support\Facades\Schedule::command('app:backup-db')->when(function () {
    $schedule = \App\Models\BackupSchedule::first();
    if (!$schedule || !$schedule->enabled) return false;
    
    // Convert current time to H:i to compare with schedule
    $now = now()->format('H:i');
    return $now === $schedule->time;
})->everyMinute()->runInBackground();
