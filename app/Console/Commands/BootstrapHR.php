<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BootstrapHR extends Command
{
    protected $signature = 'app:bootstrap-hr';

    protected $description = 'Provision the first HR account interactively; disabled once any HR account exists';

    public function handle(AuditService $audit): int
    {
        if (User::where('role_id', Role::HR)->exists()) {
            $this->error('An HR account already exists. Use protected HR Account Management.');

            return self::FAILURE;
        }
        $data = ['name' => trim((string) $this->ask('HR name')), 'email' => mb_strtolower(trim((string) $this->ask('HR email'))), 'password' => $this->secret('Password (12+ characters, mixed case, number and symbol)'), 'password_confirmation' => $this->secret('Confirm password')];
        $validator = Validator::make($data, ['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:254', 'unique:users,email'], 'password' => ['required', 'string', PasswordPolicy::rule(), 'confirmed']]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $created = DB::transaction(function () use ($data, $audit) {
            Role::whereKey(Role::HR)->lockForUpdate()->firstOrFail();
            if (User::where('role_id', Role::HR)->exists()) {
                return false;
            }
            $user = User::create(collect($data)->only(['name', 'email', 'password'])->all());
            $user->forceFill(['role_id' => Role::HR, 'account_status' => 'active', 'email_verified_at' => now()])->save();
            $audit->record('hr.bootstrap_created', $user, ['channel' => 'authorized_cli']);

            return true;
        });
        $created ? $this->info('Initial HR account created. No credentials were printed.') : $this->error('Another HR account was created concurrently. Bootstrap refused.');

        return $created ? self::SUCCESS : self::FAILURE;
    }
}
