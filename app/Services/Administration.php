<?php

namespace App\Services;

use App\Models\Role;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Administration
{
    public function run(Closure $operation): mixed
    {
        try {
            return DB::transaction(function () use ($operation) {
                // Serialize infrequent administrative writes and last-HR/assignment invariants.
                Role::whereKey(Role::HR)->lockForUpdate()->firstOrFail();
                $actor = auth()->user()?->fresh();
                abort_unless($actor?->isHr() && $actor->canAuthenticate(), 403);

                return $operation();
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages(['record' => 'This change conflicts with an existing or referenced record. Refresh and check the identifiers.']);
            }
            throw $exception;
        }
    }
}
