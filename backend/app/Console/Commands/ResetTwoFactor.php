<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Last-resort recovery for an administrator who has lost both their
 * authenticator and their recovery codes. Needs server shell access.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'portfolio:two-factor:reset {email : The administrator account email}';

    protected $description = 'Turn off two-factor sign-in for one administrator account';

    public function handle(): int
    {
        $user = User::where('email', Str::lower(trim($this->argument('email'))))->where('is_admin', true)->first();
        if (! $user) {
            $this->error('No administrator account uses that email address.');

            return self::FAILURE;
        }
        if (! $this->confirm("Turn off two-factor sign-in for {$user->email}?")) {
            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
        AuditLog::create(['user_id' => $user->id, 'action' => 'security.two_factor_reset_cli']);
        $this->info('Two-factor sign-in is off. Sign in with the password and set it up again from the Security page.');

        return self::SUCCESS;
    }
}
