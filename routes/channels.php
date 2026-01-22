<?php

declare(strict_types=1);

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

    return $onesiBox !== null && $onesiBox->userCanView($user);
});

// Channel for appliances to receive real-time command notifications
// The appliance authenticates via Sanctum token and can only listen to its own channel
Broadcast::channel('appliance.{id}', function (OnesiBox $onesiBox, int $id) {
    return $onesiBox->id === $id;
});
