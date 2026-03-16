<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('import');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        
        if ($user && Hash::check($request->password, $user->password)) {
            $this->completeLogin($user, $request);
            return redirect()->intended(route('import'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    protected function completeLogin($user, Request $request): void
    {
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        
        \App\Models\UserSession::recordLogin(
            $user->id,
            session()->getId(),
            $request->ip(),
            $request->userAgent()
        );
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('import');
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'auth_source' => 'local',
        ]);

        Auth::login($user);
        return redirect()->route('import');
    }

    public function logout(Request $request)
    {
        \App\Models\UserSession::where('session_id', session()->getId())
            ->update(['is_current' => false]);
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
