<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/** The page an invited person opens to choose their password. The link works once. */
class AccountSetupController extends Controller
{
    public function show(string $token)
    {
        $user = $this->user($token);

        return response()->view('admin.setup', ['user' => $user, 'token' => $token], $user ? 200 : 404)
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex');
    }

    public function store(Request $request, string $token)
    {
        $user = $this->user($token);
        abort_unless($user, 404);
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'max:200', 'confirmed']]);
        $user->forceFill(['password' => $data['password'], 'setup_token_hash' => null, 'setup_expires_at' => null, 'remember_token' => null])->save();
        AuditLog::create(['user_id' => $user->id, 'action' => 'users.password_set', 'ip_address' => $request->ip()]);

        return redirect()->route('login')->with('status', 'Your password is set. Sign in with your email address and the new password.');
    }

    private function user(string $token): ?User
    {
        if (! preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }

        return User::where('is_admin', true)->where('setup_token_hash', hash('sha256', $token))
            ->where('setup_expires_at', '>', now())->first();
    }
}
