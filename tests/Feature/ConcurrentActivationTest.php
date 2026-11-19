<?php

namespace Tests\Feature;

use App\Models\AccountInvitation;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConcurrentActivationTest extends TestCase
{
    public function test_competing_setups_only_save_the_winning_password(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'activation-');
        $connection = array_replace(config('database.connections.sqlite'), ['database' => $database, 'url' => null, 'transaction_mode' => 'IMMEDIATE', 'busy_timeout' => 5000]);
        config(['database.connections.activation' => $connection, 'database.default' => 'activation']);

        try {
            $this->artisan('migrate', ['--database' => 'activation', '--force' => true])->assertSuccessful();
            $user = User::factory()->unverified()->create(['account_status' => 'pending_activation']);
            $token = bin2hex(random_bytes(32));
            AccountInvitation::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);

            $attempts = [];
            foreach (['First!Password123', 'Second!Password123'] as $password) {
                $attempts[] = static function () use ($connection, $password, $token): string {
                    config(['database.connections.activation' => $connection, 'database.default' => 'activation',
                        'queue.default' => 'sync', 'broadcasting.default' => 'null', 'hashing.bcrypt.rounds' => 4]);
                    try {
                        app(InvitationService::class)->activate($token, $password);

                        return $password;
                    } catch (ValidationException $exception) {
                        if (! isset($exception->errors()['token'])) {
                            throw $exception;
                        }

                        return '';
                    }
                };
            }
            $winners = array_values(array_filter(Concurrency::driver('process')->run($attempts)));
            $this->assertCount(1, $winners);
            $this->assertTrue(Hash::check($winners[0], $user->fresh()->password));
            $this->assertSame(1, AuditLog::where('action', 'account.activated')->count());
        } finally {
            DB::purge('activation');
            unlink($database);
        }
    }
}
