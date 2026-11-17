<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditVersion
{
    public static function token(Model $model): string
    {
        $values = $model->fresh()->getRawOriginal();
        ksort($values);
        $snapshot = [$model::class, $model->getKey(), $values];
        if ($model instanceof Employee) {
            $snapshot[] = $model->user()->first()?->getRawOriginal();
            $snapshot[] = $model->currentAssignment()->first()?->getRawOriginal();
        }

        return hash_hmac('sha256', json_encode($snapshot), config('app.key'));
    }

    public static function check(Model $model, ?string $token): void
    {
        if (! $token || ! hash_equals(self::token($model), $token)) {
            throw ValidationException::withMessages(['_edit_version' => 'This record changed after the form was opened. Your entries were preserved. Review the latest saved data before saving again.']);
        }
    }
}
