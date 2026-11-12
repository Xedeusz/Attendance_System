<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) {
    throw new RuntimeException('Browser fixtures are restricted to local development.');
}

if (($argv[1] ?? '') === 'create') {
    $password = bin2hex(random_bytes(20)).'aA!';
    $user = User::create(['name' => 'Browser QA', 'email' => 'browser-qa-'.bin2hex(random_bytes(10)).'@example.test', 'password' => $password]);
    echo json_encode(['id' => $user->id, 'email' => $user->email, 'password' => $password]);
} elseif (($argv[1] ?? '') === 'cleanup') {
    $user = User::findOrFail((int) ($argv[2] ?? 0));
    if (! str_starts_with($user->email, 'browser-qa-') || ! str_ends_with($user->email, '@example.test')) {
        throw new RuntimeException('Refusing to remove a non-fixture account.');
    }
    DB::transaction(function () use ($user) {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();
    });
}
