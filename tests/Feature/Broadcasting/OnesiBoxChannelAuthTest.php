<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Facades\Broadcast;

/**
 * Invoke the registered channel authorization callback directly with a given user.
 * Returns true/false (the boolean the callback returned).
 *
 * This bypasses the HTTP `/broadcasting/auth` endpoint because the null broadcaster
 * used in test env (phpunit.xml BROADCAST_CONNECTION=null) returns 200 OK regardless
 * of channel auth result.
 */
function invokeOnesiBoxChannelCallback(User $user, int $boxId): bool
{
    // In test env BROADCAST_CONNECTION=null (see phpunit.xml), so Broadcast::driver()
    // returns the NullBroadcaster where routes/channels.php registrations live.
    $broadcaster = Broadcast::driver();

    $reflection = new ReflectionClass(Broadcaster::class);
    $channelsProperty = $reflection->getProperty('channels');
    /** @var array<string, callable> $channels */
    $channels = $channelsProperty->getValue($broadcaster);

    throw_unless(isset($channels['onesibox.{id}']), RuntimeException::class, 'Channel onesibox.{id} is not registered');

    $callback = $channels['onesibox.{id}'];
    $result = $callback($user, $boxId);

    return (bool) $result;
}

test('admin can subscribe to onesibox channel', function (): void {
    $box = OnesiBox::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect(invokeOnesiBoxChannelCallback($admin, $box->id))->toBeTrue();
});

test('super-admin can subscribe to onesibox channel', function (): void {
    $box = OnesiBox::factory()->create();
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    expect(invokeOnesiBoxChannelCallback($superAdmin, $box->id))->toBeTrue();
});

test('caregiver of the box can subscribe', function (): void {
    $box = OnesiBox::factory()->create();
    $caregiver = User::factory()->create();
    $caregiver->assignRole('caregiver');
    $box->caregivers()->attach($caregiver->id, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    expect(invokeOnesiBoxChannelCallback($caregiver, $box->id))->toBeTrue();
});

test('unrelated user cannot subscribe to onesibox channel', function (): void {
    $box = OnesiBox::factory()->create();
    $stranger = User::factory()->create();
    $stranger->assignRole('caregiver');

    expect(invokeOnesiBoxChannelCallback($stranger, $box->id))->toBeFalse();
});

test('nonexistent box returns false', function (): void {
    $user = User::factory()->create();
    $user->assignRole('caregiver');

    expect(invokeOnesiBoxChannelCallback($user, 99999))->toBeFalse();
});
