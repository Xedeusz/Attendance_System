<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    public function record(string $action, Model $subject, array $metadata = [], bool $includeRequestMetadata = true): void
    {
        AuditLog::create([
            'actor_user_id' => auth()->id(), 'action' => $action,
            'subject_type' => class_basename($subject), 'subject_id' => $subject->getKey(),
            'metadata' => $metadata,
            'ip_address' => ! $includeRequestMetadata || app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => ! $includeRequestMetadata || app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
        app(WorkspaceSync::class)->record($action, $subject, $metadata);
    }
}
