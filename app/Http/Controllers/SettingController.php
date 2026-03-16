<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\OdooService;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'show_dashboard' => Setting::get('show_dashboard', '1'),
            'empty_before_sync' => Setting::get('empty_before_sync', '0'),
        ];

        $odooConfig = Setting::getOdooConfig();
        $schedule = [
            'enabled' => Setting::getValue('odoo_schedule_enabled', 'false') === 'true',
            'interval' => Setting::getValue('odoo_schedule_interval', 'daily'),
            'last_sync' => Setting::getValue('odoo_last_sync', null),
        ];

        return view('settings.index', compact('settings', 'odooConfig', 'schedule'));
    }

    public function update(Request $request)
    {
        Setting::set('show_dashboard', $request->has('show_dashboard') ? '1' : '0');
        Setting::set('empty_before_sync', $request->has('empty_before_sync') ? '1' : '0');
        return back()->with('success', 'Settings updated successfully.');
    }

    public function emptyDatabase()
    {
        \App\Models\JournalLine::query()->delete();
        \App\Models\JournalEntry::query()->delete();
        
        return back()->with('success', 'Database cleared successfully.');
    }

    /**
     * Save Odoo configuration
     */
    public function saveOdooConfig(Request $request): JsonResponse
    {
        $request->validate([
            'odoo_url' => 'required|url',
            'odoo_db' => 'required|string',
            'odoo_user' => 'required|string',
            'odoo_password' => 'required|string',
        ]);

        Setting::set('odoo_url', $request->input('odoo_url'));
        Setting::set('odoo_db', $request->input('odoo_db'));
        Setting::set('odoo_user', $request->input('odoo_user'));
        Setting::set('odoo_password', $request->input('odoo_password'));

        return response()->json(['success' => true, 'message' => 'Odoo configuration saved successfully.']);
    }

    /**
     * Test Odoo connection
     */
    public function testOdooConnection(): JsonResponse
    {
        $odoo = new OdooService();
        $result = $odoo->testConnection();
        return response()->json($result);
    }

    /**
     * Get schedule configuration
     */
    public function getSchedule(): JsonResponse
    {
        return response()->json([
            'enabled' => Setting::getValue('odoo_schedule_enabled', 'false') === 'true',
            'interval' => Setting::getValue('odoo_schedule_interval', 'daily'),
            'last_sync' => Setting::getValue('odoo_last_sync', null),
        ]);
    }

    /**
     * Save schedule configuration
     */
    public function saveSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'interval' => 'required|in:hourly,every_2_hours,every_4_hours,every_6_hours,every_12_hours,daily',
        ]);

        Setting::setValue('odoo_schedule_enabled', $validated['enabled'] ? 'true' : 'false');
        Setting::setValue('odoo_schedule_interval', $validated['interval']);

        return response()->json([
            'success' => true,
            'message' => $validated['enabled'] 
                ? "Auto-sync enabled ({$validated['interval']})" 
                : 'Auto-sync disabled',
        ]);
    }
}
