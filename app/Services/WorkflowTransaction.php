<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

class WorkflowTransaction
{
    public function run(User $actor, Closure $operation): mixed
    {
        return DB::transaction(function () use ($actor, $operation) {
            Role::whereKey(Role::HR)->lockForUpdate()->firstOrFail();
            $fresh = User::findOrFail($actor->id);
            abort_unless($fresh->canAuthenticate(), 403);

            return $operation($fresh);
        }, 3);
    }
}
