<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PrintHubService;

class PreferenceController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $service = new PrintHubService();
        $printersData = $service->getPrinters();
        $printers = $printersData['success'] ? $printersData['printers'] : [];
        
        $queuesData = $service->getQueues();
        $queues = $queuesData['success'] ? $queuesData['queues'] : [];

        $agentsData = $service->getOnlineAgents();
        $agents = $agentsData['success'] ? $agentsData['agents'] : [];
        
        $docTypes = [
            'kuitansi'        => 'Kuitansi',
            'journal'         => 'Journal Entry',
            'invoice_driver'  => 'Invoice Driver',
            'invoice_other'   => 'Invoice Other (INVOT/INVOW)',
            'invoice_rental'  => 'Invoice Rental (INVRC/INVRS)',
            'invoice_vehicle' => 'Invoice Jual Kendaraan',
        ];

        return view('profile.preferences', compact('user', 'printers', 'queues', 'agents', 'docTypes'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        
        $preferences = $user->preferences ?? [];
        $preferences['default_printer'] = $request->default_printer;
        
        foreach (['kuitansi', 'journal', 'invoice_driver', 'invoice_other', 'invoice_rental', 'invoice_vehicle'] as $type) {
            $queue = $request->input("pq_{$type}_queue");
            $agentId = $request->input("pq_{$type}_agent_id");
            $printer = $request->input("pq_{$type}_printer");

            if ($queue || $agentId || $printer) {
                // Initialize print_queues array if it doesn't exist
                if (!isset($preferences['print_queues'])) {
                    $preferences['print_queues'] = [];
                }
                
                $preferences['print_queues'][$type] = array_filter([
                    'queue'    => $queue ? $queue : null,
                    'agent_id' => $agentId ? (int) $agentId : null,
                    'printer'  => $printer ? $printer : null,
                ], fn($v) => $v !== null);
            } else {
                if (isset($preferences['print_queues'][$type])) {
                    unset($preferences['print_queues'][$type]);
                }
            }
        }

        $user->preferences = $preferences;
        $user->save();
        
        return back()->with('success', 'Preferences updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'The provided current password does not match our records.');
        }

        $user->password = \Illuminate\Support\Facades\Hash::make($request->new_password);
        $user->save();

        return back()->with('success', 'Password updated successfully.');
    }
}
