<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Role-specific authorization channel
Broadcast::channel('role.{roleName}', function ($user, $roleName) {
    if (!$user) return false;
    $userRole = strtoupper($user->role ?? '');
    $targetRole = strtoupper($roleName);
    return $userRole === 'OWNER' || $userRole === $targetRole;
});

// Branch specific channel
Broadcast::channel('branch.{locationId}', function ($user, $locationId) {
    if (!$user) return false;
    if (strtoupper($user->role ?? '') === 'OWNER') return true;
    return (int) ($user->location_id ?? 0) === (int) $locationId;
});
