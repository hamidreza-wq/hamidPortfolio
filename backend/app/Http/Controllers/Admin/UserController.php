<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Owner-only. Invites use a one-time set-password link (shown once, stored
 * hashed, valid for 48 hours), so no password ever travels by email or chat.
 */
class UserController extends Controller
{
    public const LINK_HOURS = 48;

    public function index()
    {
        $users = User::where('is_admin', true)->orderBy('name')->get();
        $lastSignIns = AuditLog::where('action', 'authentication.login')->whereIn('user_id', $users->pluck('id'))
            ->selectRaw('user_id, max(created_at) as at')->groupBy('user_id')->pluck('at', 'user_id');

        return view('admin.users', ['users' => $users, 'lastSignIns' => $lastSignIns, 'roles' => Roles::ROLES, 'permissions' => Roles::PERMISSIONS]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_keys(Roles::ROLES))],
            'password' => ['required', 'current_password'],
        ], ['email.unique' => 'That email address already has an account.']);
        $token = Str::random(48);
        $user = DB::transaction(function () use ($data, $token, $request) {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'], 'email' => Str::lower(trim($data['email'])),
                'password' => Hash::make(Str::random(64)), 'is_admin' => true, 'role' => $data['role'],
                'setup_token_hash' => hash('sha256', $token), 'setup_expires_at' => now()->addHours(self::LINK_HOURS),
            ])->save();
            $this->audit($request, 'users.invited', $user, ['role' => $data['role']]);

            return $user;
        });

        return back()->with('status', "{$user->name} was added as ".Roles::label($user->role).'. Send them the link below; it works once and expires in '.self::LINK_HOURS.' hours.')
            ->with('setupLink', ['name' => $user->name, 'url' => route('admin.setup', $token)]);
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(Roles::ROLES))],
            'password' => ['required', 'current_password'],
        ]);
        $this->guard($request, $user, $data['role']);
        $before = $user->adminRole();
        $user->forceFill(['role' => $data['role']])->save();
        $this->audit($request, 'users.role_changed', $user, ['from' => $before, 'to' => $data['role']]);

        return back()->with('status', "{$user->name} is now ".Roles::label($data['role']).'. It applies from their next page load.');
    }

    /** A new one-time link: for someone who lost their invite, or to let them choose a new password. */
    public function link(Request $request, User $user)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        abort_unless($user->is_admin, 404);
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'Change your own password from the server command line, or ask another owner.']);
        }
        $token = Str::random(48);
        $user->forceFill(['setup_token_hash' => hash('sha256', $token), 'setup_expires_at' => now()->addHours(self::LINK_HOURS)])->save();
        $this->audit($request, 'users.link_issued', $user);

        return back()->with('status', "New set-password link for {$user->name}. It replaces any earlier link.")
            ->with('setupLink', ['name' => $user->name, 'url' => route('admin.setup', $token)]);
    }

    /** Removes dashboard access and signs them out everywhere. The account stays, so the activity log still names them. */
    public function revoke(Request $request, User $user)
    {
        $request->validate(['password' => ['required', 'current_password'], 'confirm' => ['accepted']]);
        $this->guard($request, $user, null);
        DB::transaction(function () use ($request, $user) {
            $user->forceFill([
                'is_admin' => false, 'role' => null, 'password' => Hash::make(Str::random(64)), 'remember_token' => null,
                'setup_token_hash' => null, 'setup_expires_at' => null,
                'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
            ])->save();
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            $this->audit($request, 'users.access_removed', $user);
        });

        return back()->with('status', "{$user->name} no longer has access and has been signed out everywhere.");
    }

    /** Nobody changes or removes themselves here, and there is always at least one owner. */
    private function guard(Request $request, User $user, ?string $newRole): void
    {
        abort_unless($user->is_admin, 404);
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'You can\'t change or remove your own access. Ask another owner.']);
        }
        $owners = User::where('is_admin', true)->get()->filter(fn (User $u) => $u->adminRole() === 'owner');
        if ($user->adminRole() === 'owner' && $newRole !== 'owner' && $owners->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'There must always be at least one owner.']);
        }
    }

    private function audit(Request $request, string $action, User $user, array $details = []): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'subject_type' => User::class,
            'subject_id' => $user->id, 'ip_address' => $request->ip(), 'details' => ['name' => $user->name] + $details]);
    }
}
