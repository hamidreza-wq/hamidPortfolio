<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SecurityController extends Controller
{
    private const FILTERS = [
        'all' => 'All activity',
        'auth' => 'Sign-ins and sign-outs',
        'failed' => 'Failed sign-ins',
        'security' => 'Security actions',
    ];

    private const FAILED_ACTIONS = ['authentication.failed', 'authentication.two_factor_failed'];

    public function index(Request $request, TwoFactor $twoFactor)
    {
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'all';
        $log = AuditLog::with('user')->latest()->latest('id');
        match ($filter) {
            'auth' => $log->where('action', 'like', 'authentication.%'),
            'failed' => $log->whereIn('action', self::FAILED_ACTIONS),
            'security' => $log->where('action', 'like', 'security.%'),
            default => null,
        };

        $failed = fn (int $days) => AuditLog::whereIn('action', self::FAILED_ACTIONS)
            ->where('created_at', '>=', now()->subDays($days))->count();
        $user = $request->user();
        $pendingSecret = ! $user->hasTwoFactorEnabled() ? $user->two_factor_secret : null;

        return view('admin.security', [
            'checks' => $this->checks($request, $failed(1)),
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'pendingSecret' => $pendingSecret,
            'qrSvg' => $pendingSecret ? $twoFactor->qrSvg($twoFactor->otpauthUri($user, $pendingSecret)) : null,
            'recoveryRemaining' => count($user->two_factor_recovery_codes ?? []),
            'failed' => ['24 hours' => $failed(1), '7 days' => $failed(7), '30 days' => $failed(30)],
            'failedSources' => AuditLog::whereIn('action', self::FAILED_ACTIONS)
                ->where('created_at', '>=', now()->subDays(7))
                ->whereNotNull('ip_address')
                ->select('ip_address', DB::raw('count(*) as attempts'), DB::raw('max(created_at) as last_seen'))
                ->groupBy('ip_address')->orderByDesc('attempts')->limit(5)->get(),
            'lastLogin' => AuditLog::where('action', 'authentication.login')
                ->where('user_id', $request->user()->id)->latest()->latest('id')->skip(1)->first(),
            'sessions' => $this->sessions($request),
            'tracksSessions' => config('session.driver') === 'database',
            'log' => $log->paginate(30)->withQueryString(),
            'filters' => self::FILTERS,
            'filter' => $filter,
        ]);
    }

    public function logoutOtherSessions(Request $request)
    {
        $validated = $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();

        Auth::logoutOtherDevices($validated['password']);
        $removed = 0;
        if (config('session.driver') === 'database') {
            $removed = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'security.sessions_revoked',
            'ip_address' => $request->ip(),
            'details' => ['removed' => $removed],
        ]);

        return redirect()->route('admin.security')
            ->with('status', 'Every other session has been signed out. This browser stays signed in.');
    }

    /** Step 1: create a secret. Nothing changes at sign-in until it is confirmed. */
    public function startTwoFactor(Request $request, TwoFactor $twoFactor)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.security');
        }
        $user->forceFill([
            'two_factor_secret' => $twoFactor->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return redirect()->to(route('admin.security').'#two-factor')
            ->with('status', 'Scan the QR code with your authenticator app, then enter the code it shows.');
    }

    /** Step 2: prove the app works, then switch it on and issue recovery codes. */
    public function confirmTwoFactor(Request $request, TwoFactor $twoFactor)
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $user = $request->user();
        if ($user->hasTwoFactorEnabled() || ! $user->two_factor_secret) {
            return redirect()->route('admin.security');
        }
        $step = $twoFactor->verify($user->two_factor_secret, $validated['code']);
        if ($step === null) {
            return back()->withErrors(['code' => 'That code did not match. Check the time on your phone is set automatically, then try the newest code.']);
        }
        $codes = $twoFactor->makeRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => $codes['hashed'],
        ])->save();
        $this->audit($request, 'security.two_factor_enabled');

        return redirect()->to(route('admin.security').'#two-factor')
            ->with('status', 'Two-factor sign-in is on. Save your recovery codes now; they will not be shown again.')
            ->with('recoveryCodes', $codes['plain']);
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactor $twoFactor)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.security');
        }
        $codes = $twoFactor->makeRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes['hashed']])->save();
        $this->audit($request, 'security.recovery_codes_regenerated');

        return redirect()->to(route('admin.security').'#two-factor')
            ->with('status', 'New recovery codes issued. The old ones no longer work.')
            ->with('recoveryCodes', $codes['plain']);
    }

    /** Turning it off needs the password and a current code, so a stolen session alone cannot do it. */
    public function disableTwoFactor(Request $request, TwoFactor $twoFactor)
    {
        $validated = $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:12'],
        ]);
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()
            && $twoFactor->verify($user->two_factor_secret, $validated['code'], $user->two_factor_last_step) === null) {
            return back()->withErrors(['code' => 'That authenticator code was not accepted.']);
        }
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
        $this->audit($request, 'security.two_factor_disabled');

        return redirect()->route('admin.security')
            ->with('status', 'Two-factor sign-in is off. Only your password protects this dashboard now.');
    }

    private function audit(Request $request, string $action): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'ip_address' => $request->ip()]);
    }

    /**
     * Read-only configuration checks. Each one reports what the running
     * application actually uses; none of them changes anything.
     *
     * @return list<array{label: string, state: string, detail: string}>
     */
    private function checks(Request $request, int $failedToday): array
    {
        $sameSite = Str::lower((string) config('session.same_site'));
        $lifetime = (int) config('session.lifetime');
        $admins = User::where('is_admin', true)->count();

        return [
            config('app.debug')
                ? ['label' => 'Debug mode', 'state' => 'critical', 'detail' => 'APP_DEBUG is on. Error pages can reveal code, paths and configuration. Turn it off in the server environment.']
                : ['label' => 'Debug mode', 'state' => 'ok', 'detail' => 'Off. Visitors see generic error pages.'],
            app()->isProduction()
                ? ['label' => 'Environment', 'state' => 'ok', 'detail' => 'Running as production.']
                : ['label' => 'Environment', 'state' => 'warning', 'detail' => 'APP_ENV is "'.app()->environment().'". Production-only protections such as HSTS are not active.'],
            $request->isSecure() && str_starts_with((string) config('app.url'), 'https://')
                ? ['label' => 'HTTPS', 'state' => 'ok', 'detail' => 'This request arrived over HTTPS and APP_URL uses https://.']
                : ['label' => 'HTTPS', 'state' => 'critical', 'detail' => 'This request or APP_URL is not HTTPS. Passwords and session cookies could be intercepted.'],
            config('session.secure')
                ? ['label' => 'Secure session cookie', 'state' => 'ok', 'detail' => 'The session cookie is only sent over HTTPS.']
                : ['label' => 'Secure session cookie', 'state' => 'warning', 'detail' => 'The session cookie can be sent over plain HTTP. Set SESSION_SECURE_COOKIE=true.'],
            config('session.http_only')
                ? ['label' => 'HttpOnly session cookie', 'state' => 'ok', 'detail' => 'Page scripts cannot read the session cookie.']
                : ['label' => 'HttpOnly session cookie', 'state' => 'critical', 'detail' => 'Page scripts can read the session cookie. Set SESSION_HTTP_ONLY=true.'],
            in_array($sameSite, ['lax', 'strict'], true)
                ? ['label' => 'SameSite cookie', 'state' => 'ok', 'detail' => 'Set to "'.$sameSite.'", which limits cross-site request forgery.']
                : ['label' => 'SameSite cookie', 'state' => 'warning', 'detail' => 'Set to "'.($sameSite ?: 'none').'". Use lax or strict.'],
            config('session.encrypt')
                ? ['label' => 'Session encryption', 'state' => 'ok', 'detail' => 'Session data is encrypted at rest.']
                : ['label' => 'Session encryption', 'state' => 'warning', 'detail' => 'Session data is stored unencrypted. Set SESSION_ENCRYPT=true.'],
            $lifetime <= 120
                ? ['label' => 'Session lifetime', 'state' => 'ok', 'detail' => "Idle sessions expire after {$lifetime} minutes."]
                : ['label' => 'Session lifetime', 'state' => 'warning', 'detail' => "Idle sessions last {$lifetime} minutes. 120 or less is recommended for an admin area."],
            ['label' => 'Sign-in throttling', 'state' => 'ok', 'detail' => 'At most 5 attempts per minute for each email and IP address, plus a route limit of 5 per minute.'],
            ['label' => 'Security headers', 'state' => 'ok', 'detail' => 'Content-Security-Policy with nonces, X-Frame-Options, nosniff, Referrer-Policy and Permissions-Policy are sent on every response'.(app()->isProduction() ? ', plus HSTS over HTTPS.' : '. HSTS is only sent in production.')],
            $request->user()->hasTwoFactorEnabled()
                ? ['label' => 'Two-factor sign-in', 'state' => 'ok', 'detail' => 'On for your account. Signing in needs your password and an authenticator code.']
                : ['label' => 'Two-factor sign-in', 'state' => 'warning', 'detail' => 'Off for your account. Only a password protects this dashboard. Turn it on below.'],
            $failedToday >= 10
                ? ['label' => 'Failed sign-ins today', 'state' => 'warning', 'detail' => "{$failedToday} failed attempts in the last 24 hours. Check the sources below."]
                : ['label' => 'Failed sign-ins today', 'state' => 'ok', 'detail' => "{$failedToday} failed attempts in the last 24 hours."],
            ['label' => 'Administrator accounts', 'state' => $admins === 1 ? 'ok' : 'warning', 'detail' => $admins === 1 ? 'One administrator account exists.' : "{$admins} administrator accounts exist. Remove any you do not recognise from the server command line."],
        ];
    }

    /** @return list<array{ip: ?string, agent: string, last_active: Carbon, current: bool}> */
    private function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($row) => [
                'ip' => $row->ip_address,
                'agent' => $this->describeAgent((string) $row->user_agent),
                'last_active' => now()->setTimestamp((int) $row->last_activity),
                'current' => hash_equals($request->session()->getId(), (string) $row->id),
            ])->all();
    }

    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown system',
        };

        return "{$browser} on {$system}";
    }
}
