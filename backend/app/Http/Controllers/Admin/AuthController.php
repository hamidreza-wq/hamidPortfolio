<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function create()
    {
        return view('admin.login');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $validated['email'] = Str::lower(trim($validated['email']));
        $key = 'admin-login:'.hash('sha256', Str::transliterate($validated['email'].'|'.$request->ip()));

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withErrors(['email' => 'Too many sign-in attempts. Try again in a minute.'])
                ->onlyInput('email');
        }

        if (! Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => true,
        ])) {
            RateLimiter::hit($key, 60);
            AuditLog::create([
                'action' => 'authentication.failed',
                'ip_address' => $request->ip(),
            ]);

            return back()
                ->withErrors(['email' => 'Those administrator credentials were not accepted.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'authentication.login',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request)
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'authentication.logout',
            'ip_address' => $request->ip(),
        ]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portfolio.home');
    }
}
