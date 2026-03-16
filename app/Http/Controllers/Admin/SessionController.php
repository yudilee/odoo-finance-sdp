<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use App\Models\BackupSchedule;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $query = UserSession::with('user')->orderBy('last_active_at', 'desc');
        
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->filled('device')) {
            $query->where('device_type', $request->device);
        }
        
        $sessions = $query->paginate(20);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        
        $stats = [
            'total_sessions' => UserSession::count(),
            'online_now' => UserSession::where('last_active_at', '>=', now()->subMinutes(5))->count(),
            'today_logins' => UserSession::whereDate('created_at', today())->count(),
            'unique_users_today' => UserSession::whereDate('last_active_at', today())->distinct('user_id')->count('user_id'),
        ];

        $schedule = BackupSchedule::first() ?? new BackupSchedule([
            'session_cleanup_enabled' => true,
            'session_cleanup_days' => 7,
        ]);
        
        return view('admin.sessions.index', compact('sessions', 'users', 'stats', 'schedule'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'session_cleanup_enabled' => 'nullable|boolean',
            'session_cleanup_days' => 'required|integer|min:1|max:365',
        ]);

        BackupSchedule::updateOrCreate(
            ['id' => 1],
            [
                'session_cleanup_enabled' => $request->boolean('session_cleanup_enabled'),
                'session_cleanup_days' => $request->input('session_cleanup_days'),
            ]
        );

        return back()->with('success', 'Session cleanup settings updated.');
    }

    public function cleanup(Request $request)
    {
        $schedule = BackupSchedule::first();
        $days = $schedule->session_cleanup_days ?? 7;

        $deleted = UserSession::where('last_active_at', '<', now()->subDays($days))->delete();
        return back()->with('success', "Cleanup completed. Removed {$deleted} inactive session(s).");
    }

    public function terminate(UserSession $session)
    {
        $userName = $session->user->name ?? 'Unknown';
        $session->delete();
        
        return back()->with('success', "Session for {$userName} has been terminated.");
    }
}
