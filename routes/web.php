<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingController;

use App\Http\Controllers\InvoiceDriverController;
use App\Http\Controllers\InvoiceOtherController;
use App\Http\Controllers\InvoiceRentalController;
use App\Http\Controllers\InvoiceVehicleController;
use App\Http\Controllers\InvoiceSubscriptionController;
use App\Http\Controllers\Admin\PrintLogController;

// ──────────────────────────────────────────────
// Guest Routes
// ──────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

// ──────────────────────────────────────────────
// Authenticated Routes
// ──────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Logout
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard / Landing
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ──────────────────────────────────────────
    // Admin/Manager Routes (requires admin role)
    // ──────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        // Import Data Routes
        Route::get('/import', [ImportController::class, 'index'])->name('import');
        Route::post('/import/odoo/sync', [ImportController::class, 'syncOdoo'])->name('import.odoo.sync');
        Route::get('/import/history', [ImportController::class, 'history'])->name('import.history');

        // Journal entries
        Route::group(['prefix' => 'journals', 'as' => 'journals.'], function () {
            Route::get('/', [JournalController::class, 'index'])->name('index');
            Route::get('/export-pdf', [JournalController::class, 'printAllPdf'])->name('print-all');
            Route::get('/export-html', [JournalController::class, 'printAllHtml'])->name('print-all-html');
            Route::post('/export-selected-pdf', [JournalController::class, 'printSelectedPdf'])->name('print-selected');
            Route::post('/export-selected-html', [JournalController::class, 'printSelectedHtml'])->name('print-selected-html');
            
            // Print Hub
            Route::get('/printers', [JournalController::class, 'getPrinters'])->name('printers');
            Route::post('/print-hub-selected', [JournalController::class, 'printSelectedViaHub'])->name('print-hub-selected');
            Route::post('/{entry}/print-hub', [JournalController::class, 'printViaHub'])->name('print-hub');

            Route::get('/{entry}', [JournalController::class, 'show'])->name('show');
            Route::get('/{entry}/pdf', [JournalController::class, 'printPdf'])->name('print');
            Route::get('/{entry}/html', [JournalController::class, 'printHtml'])->name('print-html');
        });

        // Settings
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/empty-database', [SettingController::class, 'emptyDatabase'])->name('settings.empty-database');
    });

    // Invoice Driver
    Route::group(['prefix' => 'invoice-driver', 'as' => 'invoice-driver.', 'middleware' => 'role:invoice'], function () {
        Route::get('/', [InvoiceDriverController::class, 'index'])->name('index');
        Route::post('/sync', [InvoiceDriverController::class, 'sync'])->name('sync');
        Route::post('/print-selected', [InvoiceDriverController::class, 'printSelectedPdf'])->name('print-selected');
        Route::post('/print-selected-html', [InvoiceDriverController::class, 'printSelectedHtml'])->name('print-selected-html');
        Route::get('/{invoice}', [InvoiceDriverController::class, 'show'])->name('show');
        Route::get('/{invoice}/pdf', [InvoiceDriverController::class, 'printPdf'])->name('print');
        Route::get('/{invoice}/html', [InvoiceDriverController::class, 'printHtml'])->name('print-html');
    });

    // Invoice Other
    Route::group(['prefix' => 'invoice-other', 'as' => 'invoice-other.', 'middleware' => 'role:invoice'], function () {
        Route::get('/', [InvoiceOtherController::class, 'index'])->name('index');
        Route::post('/sync', [InvoiceOtherController::class, 'sync'])->name('sync');
        Route::post('/print-selected', [InvoiceOtherController::class, 'printSelectedPdf'])->name('print-selected');
        Route::post('/print-selected-html', [InvoiceOtherController::class, 'printSelectedHtml'])->name('print-selected-html');
        Route::get('/{invoice}', [InvoiceOtherController::class, 'show'])->name('show');
        Route::get('/{invoice}/pdf', [InvoiceOtherController::class, 'printPdf'])->name('print');
        Route::get('/{invoice}/html', [InvoiceOtherController::class, 'printHtml'])->name('print-html');
    });

    // Invoice Rental
    Route::group(['prefix' => 'invoice-rental', 'as' => 'invoice-rental.', 'middleware' => 'role:invoice'], function () {
        Route::get('/', [InvoiceRentalController::class, 'index'])->name('index');
        Route::post('/sync', [InvoiceRentalController::class, 'sync'])->name('sync');
        Route::post('/print-selected', [InvoiceRentalController::class, 'printSelectedPdf'])->name('print-selected');
        Route::post('/print-selected-html', [InvoiceRentalController::class, 'printSelectedHtml'])->name('print-selected-html');
        Route::get('/{invoice}', [InvoiceRentalController::class, 'show'])->name('show');
        Route::get('/{invoice}/pdf', [InvoiceRentalController::class, 'printPdf'])->name('print');
        Route::get('/{invoice}/html', [InvoiceRentalController::class, 'printHtml'])->name('print-html');
    });

    // Invoice Vehicle (Penjualan Kendaraan)
    Route::group(['prefix' => 'invoice-vehicle', 'as' => 'invoice-vehicle.', 'middleware' => 'role:invoice'], function () {
        Route::get('/', [InvoiceVehicleController::class, 'index'])->name('index');
        Route::post('/sync', [InvoiceVehicleController::class, 'sync'])->name('sync');
        Route::post('/print-selected', [InvoiceVehicleController::class, 'printSelectedPdf'])->name('print-selected');
        Route::post('/print-selected-html', [InvoiceVehicleController::class, 'printSelectedHtml'])->name('print-selected-html');
        Route::get('/{invoice}', [InvoiceVehicleController::class, 'show'])->name('show');
        Route::get('/{invoice}/pdf', [InvoiceVehicleController::class, 'printPdf'])->name('print');
        Route::get('/{invoice}/html', [InvoiceVehicleController::class, 'printHtml'])->name('print-html');
    });

    // Invoice Subscription (Check Invoice Subscription – Rental Periods)
    Route::group(['prefix' => 'invoice-subscription', 'as' => 'invoice-subscription.', 'middleware' => 'role:invoice'], function () {
        Route::get('/', [InvoiceSubscriptionController::class, 'index'])->name('index');
        Route::post('/sync', [InvoiceSubscriptionController::class, 'sync'])->name('sync');
        Route::post('/preferences', [InvoiceSubscriptionController::class, 'updatePreferences'])->name('preferences.update');
        Route::post('/preferences/reset', [InvoiceSubscriptionController::class, 'resetPreferences'])->name('preferences.reset');
        Route::post('/export', [InvoiceSubscriptionController::class, 'export'])->name('export');
    });



    // ──────────────────────────────────────────
    // Admin Routes (requires admin role)
    // ──────────────────────────────────────────
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        // User Management
        Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [\App\Http\Controllers\Admin\UserController::class, 'create'])->name('users.create');
        Route::post('users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');

        // Database Backups
        Route::get('backups', [\App\Http\Controllers\Admin\BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [\App\Http\Controllers\Admin\BackupController::class, 'create'])->name('backups.create');
        Route::get('backups/{filename}/download', [\App\Http\Controllers\Admin\BackupController::class, 'download'])->name('backups.download');
        Route::post('backups/{filename}/restore', [\App\Http\Controllers\Admin\BackupController::class, 'restore'])->name('backups.restore');
        Route::post('backups/restore-file', [\App\Http\Controllers\Admin\BackupController::class, 'restoreFromFile'])->name('backups.restore-file');
        Route::delete('backups/{filename}', [\App\Http\Controllers\Admin\BackupController::class, 'delete'])->name('backups.destroy');
        Route::post('backups/schedule', [\App\Http\Controllers\Admin\BackupController::class, 'updateSchedule'])->name('backups.schedule');
        Route::post('backups/delete-batch', [\App\Http\Controllers\Admin\BackupController::class, 'deleteBatch'])->name('backups.delete-batch');
        Route::post('backups/prune', [\App\Http\Controllers\Admin\BackupController::class, 'prune'])->name('backups.prune');

        // Session Manager
        Route::get('sessions', [\App\Http\Controllers\Admin\SessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions/settings', [\App\Http\Controllers\Admin\SessionController::class, 'updateSettings'])->name('sessions.settings');
        Route::post('sessions/cleanup', [\App\Http\Controllers\Admin\SessionController::class, 'cleanup'])->name('sessions.cleanup');
        Route::delete('sessions/{session}', [\App\Http\Controllers\Admin\SessionController::class, 'terminate'])->name('sessions.terminate');

        // Print Logs
        Route::get('print-logs', [PrintLogController::class, 'index'])->name('print_logs.index');
        Route::post('print-logs/bulk-reset', [PrintLogController::class, 'resetBulk'])->name('print_logs.reset_bulk');
        Route::post('print-logs/{printLog}/reset', [PrintLogController::class, 'reset'])->name('print_logs.reset');

        // Odoo Settings (Relocated)
        Route::post('odoo/config', [SettingController::class, 'saveOdooConfig'])->name('settings.odoo.config');
        Route::post('odoo/test', [SettingController::class, 'testOdooConnection'])->name('settings.odoo.test');
        Route::get('odoo/schedule', [SettingController::class, 'getSchedule'])->name('settings.odoo.schedule.get');
        Route::post('odoo/schedule', [SettingController::class, 'saveSchedule'])->name('settings.odoo.schedule.save');

        // Print Hub Settings
        Route::post('print-hub/config', [SettingController::class, 'savePrintHubConfig'])->name('settings.print_hub.config');
        Route::post('print-hub/test', [SettingController::class, 'testHubConnection'])->name('settings.print_hub.test');
        Route::post('print-hub/sync-schemas', [SettingController::class, 'syncHubSchemas'])->name('settings.print_hub.sync_schemas');
    });

    // ──────────────────────────────────────────
    // User Profile / Preferences
    // ──────────────────────────────────────────
    Route::group(['prefix' => 'profile', 'as' => 'profile.'], function () {
        Route::get('/preferences', [\App\Http\Controllers\PreferenceController::class, 'index'])->name('preferences');
        Route::post('/preferences', [\App\Http\Controllers\PreferenceController::class, 'update'])->name('preferences.update');
    });
});
