<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const PENDING = 'two_factor.pending';

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

        if (! Auth::validate([
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
        /** @var User $user */
        $user = Auth::getLastAttempted();

        if ($user->hasTwoFactorEnabled()) {
            // The password was right, but nobody is signed in until the code is too.
            $request->session()->regenerate();
            $request->session()->put(self::PENDING, ['id' => $user->id, 'expires' => now()->addMinutes(5)->timestamp]);

            return redirect()->route('admin.two-factor');
        }

        return $this->completeLogin($request, $user, 'password');
    }

    public function challenge(Request $request)
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login')->withErrors(['email' => 'Sign in again to continue.']);
        }

        return view('admin.two-factor-challenge');
    }

    public function verifyChallenge(Request $request, TwoFactor $twoFactor)
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:12'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Sign in again to continue.']);
        }

        $key = 'admin-two-factor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Try again in a minute.']);
        }

        if (filled($validated['recovery_code'] ?? null)) {
            $remaining = $twoFactor->useRecoveryCode($user->two_factor_recovery_codes ?? [], $validated['recovery_code']);
            if ($remaining !== null) {
                $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'security.recovery_code_used',
                    'ip_address' => $request->ip(),
                    'details' => ['remaining' => count($remaining)],
                ]);
                RateLimiter::clear($key);

                return $this->completeLogin($request, $user, 'recovery code');
            }
        } elseif (filled($validated['code'] ?? null)) {
            $step = $twoFactor->verify($user->two_factor_secret, $validated['code'], $user->two_factor_last_step);
            if ($step !== null) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                RateLimiter::clear($key);

                return $this->completeLogin($request, $user, 'authenticator code');
            }
        }

        RateLimiter::hit($key, 60);
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'authentication.two_factor_failed',
            'ip_address' => $request->ip(),
        ]);

        return back()->withErrors(['code' => 'That code was not accepted. Codes change every 30 seconds.']);
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

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING);
        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING);

            return null;
        }
        $user = User::where('is_admin', true)->find($pending['id'] ?? null);

        return $user?->hasTwoFactorEnabled() ? $user : null;
    }

    private function completeLogin(Request $request, User $user, string $method)
    {
        $request->session()->forget(self::PENDING);
        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'authentication.login',
            'ip_address' => $request->ip(),
            'details' => ['method' => $method],
        ]);

        return redirect()->route('admin.dashboard');
    }
}
