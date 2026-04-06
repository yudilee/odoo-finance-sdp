<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\OdooService;
use App\Services\PrintHubService;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'show_dashboard'  => Setting::get('show_dashboard', '1'),
            'empty_before_sync' => Setting::get('empty_before_sync', '0'),
            'default_bc_manager' => Setting::get('default_bc_manager', ''),
            'default_bc_spv'     => Setting::get('default_bc_spv', ''),
            'enable_pdf_watermark' => Setting::get('enable_pdf_watermark', '1'),
            'journal_paper_size' => Setting::get('journal_paper_size', 'A5'),
            'odoo_deep_sync_journal' => Setting::get('odoo_deep_sync_journal', '0'),
        ];

        $odooConfig = Setting::getOdooConfig();
        $printHubConfig = [
            'url' => Setting::get('print_hub_url', ''),
            'api_key' => Setting::get('print_hub_api_key', ''),
            'timeout' => Setting::get('print_hub_timeout', '15'),
            'default_profile' => Setting::get('print_hub_default_profile', ''),
        ];
        $schedule = [
            'enabled' => Setting::getValue('odoo_schedule_enabled', 'false') === 'true',
            'interval' => Setting::getValue('odoo_schedule_interval', 'daily'),
            'last_sync' => Setting::getValue('odoo_last_sync', null),
        ];

        return view('settings.index', compact('settings', 'odooConfig', 'printHubConfig', 'schedule'));
    }

    public function update(Request $request)
    {
        Setting::set('show_dashboard', $request->has('show_dashboard') ? '1' : '0');
        Setting::set('empty_before_sync', $request->has('empty_before_sync') ? '1' : '0');
        Setting::set('default_bc_manager', $request->input('default_bc_manager', ''));
        Setting::set('default_bc_spv', $request->input('default_bc_spv', ''));
        Setting::set('enable_pdf_watermark', $request->has('enable_pdf_watermark') ? '1' : '0');
        Setting::set('journal_paper_size', $request->input('journal_paper_size', 'A5'));
        Setting::set('odoo_deep_sync_journal', $request->has('odoo_deep_sync_journal') ? '1' : '0');
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

    /**
     * Save Print Hub configuration
     */
    public function savePrintHubConfig(Request $request): JsonResponse
    {
        $request->validate([
            'print_hub_url' => 'required|url',
            'print_hub_api_key' => 'required|string',
            'print_hub_timeout' => 'required|integer|min:1',
            'print_hub_default_profile' => 'nullable|string',
        ]);

        Setting::set('print_hub_url', $request->input('print_hub_url'));
        Setting::set('print_hub_api_key', $request->input('print_hub_api_key'));
        Setting::set('print_hub_timeout', $request->input('print_hub_timeout'));
        Setting::set('print_hub_default_profile', $request->input('print_hub_default_profile', ''));

        return response()->json(['success' => true, 'message' => 'Print Hub configuration saved successfully.']);
    }

    /**
     * Test Print Hub connection
     */
    public function testHubConnection(Request $request): JsonResponse
    {
        $url = $request->input('print_hub_url');
        $apiKey = $request->input('print_hub_api_key');

        $printHub = new PrintHubService($url, $apiKey);
        $result = $printHub->testConnection();
        
        if ($result['success']) {
            $appName = $result['app_name'] ?? 'Unknown App';
            $agentCount = $result['agents'] ?? 0;
            return response()->json([
                'success' => true, 
                'message' => "Successfully connected to Print Hub as '{$appName}'! Found {$agentCount} online agents."
            ]);
        }

        return response()->json([
            'success' => false, 
            'message' => 'Connection failed: ' . ($result['message'] ?? 'Unknown error')
        ]);
    }

    /**
     * Sync data schemas to Print Hub
     */
    public function syncHubSchemas(): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('printhub:register-schemas');
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Schemas synced successfully!',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
