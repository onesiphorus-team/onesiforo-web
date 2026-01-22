<?php

declare(strict_types=1);

use App\Listeners\NotifyAdminsOfNewRegistration;
use App\Models\User;
use App\Notifications\NewUserRegisteredNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Oltrematica\RoleLite\Models\Role;

beforeEach(function (): void {
    Role::query()->firstOrCreate(['name' => 'super-admin']);
    Role::query()->firstOrCreate(['name' => 'admin']);
    Role::query()->firstOrCreate(['name' => 'caregiver']);
});

it('listens to the Registered event', function (): void {
    Event::fake();
    Event::assertListening(Registered::class, NotifyAdminsOfNewRegistration::class);
});

it('sends notification to all admins when a new user registers', function (): void {
    Notification::fake();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $caregiver = User::factory()->create();
    $caregiver->assignRole('caregiver');

    $newUser = User::factory()->create();

    $listener = new NotifyAdminsOfNewRegistration;
    $listener->handle(new Registered($newUser));

    Notification::assertSentTo($superAdmin, NewUserRegisteredNotification::class);
    Notification::assertSentTo($admin, NewUserRegisteredNotification::class);
    Notification::assertNotSentTo($caregiver, NewUserRegisteredNotification::class);
    Notification::assertNotSentTo($newUser, NewUserRegisteredNotification::class);
});

it('does not send notifications when there are no admins', function (): void {
    Notification::fake();

    $caregiver = User::factory()->create();
    $caregiver->assignRole('caregiver');

    $newUser = User::factory()->create();

    $listener = new NotifyAdminsOfNewRegistration;
    $listener->handle(new Registered($newUser));

    Notification::assertNothingSent();
});

it('notification contains correct user information', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $newUser = User::factory()->create([
        'name' => 'New Test User',
        'email' => 'newuser@test.com',
    ]);

    $listener = new NotifyAdminsOfNewRegistration;
    $listener->handle(new Registered($newUser));

    Notification::assertSentTo($admin, NewUserRegisteredNotification::class, function ($notification) use ($newUser): bool {
        $mailData = $notification->toArray($newUser);

        return $mailData['new_user_id'] === $newUser->id
            && $mailData['new_user_name'] === 'New Test User'
            && $mailData['new_user_email'] === 'newuser@test.com';
    });
});
