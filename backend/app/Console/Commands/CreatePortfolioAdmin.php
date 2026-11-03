<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('portfolio:admin:create')]
#[Description('Create the single-owner administrator, or explicitly reset the existing administrator.')]
class CreatePortfolioAdmin extends Command
{
    public function handle(): int
    {
        $name = $this->components->ask('Administrator name');
        $email = $this->components->ask('Administrator email');
        $email = is_string($email) ? strtolower(trim($email)) : $email;
        $password = $this->secret('Password (minimum 12 characters)');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $existingAdmin = User::query()->where('is_admin', true)->first();
        if ($existingAdmin && $existingAdmin->email !== $email) {
            $this->components->error('An administrator already exists. Enter the existing administrator email to update that account.');

            return self::FAILURE;
        }

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser && ! $existingUser->is_admin) {
            $this->components->error('That email belongs to a non-administrator. This command will not promote the account.');

            return self::FAILURE;
        }
        if ($existingAdmin && ! $this->confirm('Reset this administrator password and revoke remembered sign-ins?', false)) {
            $this->components->error('Administrator reset cancelled.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($existingUser, $name, $email, $password): void {
            $user = $existingUser ?? new User;
            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make($password);
            $user->is_admin = true;
            $user->remember_token = null;
            $user->save();
            AuditLog::create([
                'user_id' => $user->id,
                'action' => $existingUser ? 'administrator.reset' : 'administrator.created',
            ]);
        });

        $this->components->info('Administrator account saved.');

        return self::SUCCESS;
    }
}
