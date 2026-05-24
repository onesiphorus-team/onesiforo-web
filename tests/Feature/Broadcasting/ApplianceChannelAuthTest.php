<?php

declare(strict_types=1);

use App\Models\OnesiBox;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Facades\Broadcast;

/**
 * Invoke the registered appliance channel authorization callback directly.
 *
 * Mirrors the helper in OnesiBoxChannelAuthTest. Bypasses the HTTP
 * /broadcasting/auth endpoint because in test env BROADCAST_CONNECTION=null.
 */
function invokeApplianceChannelCallback(OnesiBox $box, string $identifier): bool
{
    $broadcaster = Broadcast::driver();

    $reflection = new ReflectionClass(Broadcaster::class);
    $channelsProperty = $reflection->getProperty('channels');
    /** @var array<string, callable> $channels */
    $channels = $channelsProperty->getValue($broadcaster);

    throw_unless(isset($channels['appliance.{identifier}']), RuntimeException::class, 'Channel appliance.{identifier} is not registered');

    $callback = $channels['appliance.{identifier}'];

    return (bool) $callback($box, $identifier);
}

test('appliance can subscribe using its serial_number', function (): void {
    $box = OnesiBox::factory()->create(['serial_number' => 'OB-ABC-001']);

    expect(invokeApplianceChannelCallback($box, 'OB-ABC-001'))->toBeTrue();
});

test('appliance can subscribe using its numeric id', function (): void {
    $box = OnesiBox::factory()->create(['serial_number' => 'OB-ABC-002']);

    expect(invokeApplianceChannelCallback($box, (string) $box->id))->toBeTrue();
});

test('appliance cannot subscribe with a foreign identifier', function (): void {
    $box = OnesiBox::factory()->create(['serial_number' => 'OB-ABC-003']);

    expect(invokeApplianceChannelCallback($box, 'OB-XYZ-999'))->toBeFalse();
});

test('appliance cannot subscribe with another box id', function (): void {
    $box = OnesiBox::factory()->create();
    $otherBox = OnesiBox::factory()->create();

    expect(invokeApplianceChannelCallback($box, (string) $otherBox->id))->toBeFalse();
});

test('appliance cannot subscribe with another box serial', function (): void {
    $box = OnesiBox::factory()->create(['serial_number' => 'OB-ABC-004']);
    $otherBox = OnesiBox::factory()->create(['serial_number' => 'OB-XYZ-005']);

    expect(invokeApplianceChannelCallback($box, $otherBox->serial_number))->toBeFalse();
});
