<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\JournalController;

// ──────────────────────────────────────────────
// Guest Routes
// ──────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

// ──────────────────────────────────────────────
// Authenticated Routes
// ──────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Logout
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Redirect root to Import
    Route::redirect('/', '/import');

    // Import Data Routes
    Route::get('/import', [ImportController::class, 'index'])->name('import');
    Route::post('/import/odoo/config', [ImportController::class, 'saveOdooConfig'])->name('import.odoo.config');
    Route::post('/import/odoo/test', [ImportController::class, 'testOdooConnection'])->name('import.odoo.test');
    Route::post('/import/odoo/sync', [ImportController::class, 'syncOdoo'])->name('import.odoo.sync');
    Route::get('/import/odoo/schedule', [ImportController::class, 'getSchedule'])->name('import.odoo.schedule.get');
    Route::post('/import/odoo/schedule', [ImportController::class, 'saveSchedule'])->name('import.odoo.schedule.save');
    Route::get('/import/history', [ImportController::class, 'history'])->name('import.history');

    // Journal Entries
    Route::get('/journals', [JournalController::class, 'index'])->name('journals.index');
    Route::get('/journals/export-pdf', [JournalController::class, 'printAllPdf'])->name('journals.print-all');
    Route::post('/journals/export-selected-pdf', [JournalController::class, 'printSelectedPdf'])->name('journals.print-selected');
    Route::get('/journals/{entry}', [JournalController::class, 'show'])->name('journals.show');
    Route::get('/journals/{entry}/pdf', [JournalController::class, 'printPdf'])->name('journals.print');

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
    });
});
