<?php

declare(strict_types=1);

use App\Enums\Roles;
use App\Models\OnesiBox;
use App\Models\User;
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

Broadcast::channel('onesibox.{id}', function (User $user, int $id) {
    $onesiBox = OnesiBox::find($id);

    if ($onesiBox === null) {
        return false;
    }

    if ($user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin)) {
        return true;
    }

    return $onesiBox->userCanView($user);
});

// Channel for appliances to receive real-time command notifications.
// The appliance authenticates via Sanctum token; the `{identifier}` placeholder
// accepts either the appliance's serial_number or its numeric id. This lets the
// client subscribe with whichever identifier its config exposes (legacy installs
// carry an opaque appliance_id UUID; newer installs use serial_number).
Broadcast::channel('appliance.{identifier}', function (OnesiBox $onesiBox, string $identifier) {
    return $identifier === $onesiBox->serial_number
        || $identifier === (string) $onesiBox->id;
});
