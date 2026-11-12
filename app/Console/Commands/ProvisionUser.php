<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionUser extends Command
{
    protected $signature = 'app:provision-user';

    protected $description = 'Interactively create a local development login (no role or administrative permissions)';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This development-only command is disabled outside the local environment.');

            return self::FAILURE;
        }
        $input = [
            'name' => trim((string) $this->ask('Name')),
            'email' => mb_strtolower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Password (12+ characters, mixed case, number and symbol)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12)->letters()->mixedCase()->numbers()->symbols(), 'confirmed'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create(collect($input)->only(['name', 'email', 'password'])->all());
        $this->info('Local login created. No employee profile or role has been assigned.');

        return self::SUCCESS;
    }
}
