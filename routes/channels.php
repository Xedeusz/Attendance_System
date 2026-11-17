<?php

use App\Models\User;
use App\Services\WorkspaceSync;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('workspace.{id}.{version}.{role}', function (User $user, string $id, string $version, string $role): bool {
    $fresh = $user->fresh();

    return $fresh && $fresh->canAuthenticate() && WorkspaceSync::channel($fresh) === 'workspace.'.$id.'.'.$version.'.'.$role;
});
