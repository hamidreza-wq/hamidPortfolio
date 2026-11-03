<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('portfolio:admin:create')]
#[Description('Create or update the single-owner portfolio administrator.')]
class CreatePortfolioAdmin extends Command
{
    public function handle(): int
    {
        $name = $this->components->ask('Administrator name');
        $email = $this->components->ask('Administrator email');
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

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password), 'is_admin' => true],
        );

        $this->components->info('Administrator account saved.');

        return self::SUCCESS;
    }
}
