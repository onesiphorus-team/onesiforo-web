<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Roles;
use App\Models\User;
use App\Notifications\NewUserRegisteredNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminsOfNewRegistration implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                Roles::SuperAdmin->value,
                Roles::Admin->value,
            ]))
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new NewUserRegisteredNotification($event->user));
        }
    }
}
