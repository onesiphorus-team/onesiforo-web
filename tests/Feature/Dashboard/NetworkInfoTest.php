<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\NetworkInfo;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;

it('shows no network info message when network data is not available', function (): void {
    $user = User::factory()->create();

    $onesiBox = OnesiBox::factory()->create([
        'network_type' => null,
        'ip_address' => null,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(NetworkInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('Nessuna informazione di rete disponibile');
});

it('shows network info when ethernet is connected', function (): void {
    $user = User::factory()->create();

    $onesiBox = OnesiBox::factory()->create([
        'network_type' => 'ethernet',
        'network_interface' => 'eth0',
        'ip_address' => '192.168.1.100',
        'gateway' => '192.168.1.1',
        'mac_address' => 'aa:bb:cc:dd:ee:ff',
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(NetworkInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('Ethernet')
        ->assertSee('192.168.1.100')
        ->assertSee('eth0')
        ->assertSee('192.168.1.1')
        ->assertSee('aa:bb:cc:dd:ee:ff');
});

it('shows wifi details when wifi is connected', function (): void {
    $user = User::factory()->create();

    $onesiBox = OnesiBox::factory()->create([
        'network_type' => 'wifi',
        'network_interface' => 'wlan0',
        'ip_address' => '192.168.1.100',
        'wifi_ssid' => 'MyNetwork',
        'wifi_signal_percent' => 70,
        'wifi_signal_dbm' => -65,
        'wifi_channel' => 6,
        'wifi_frequency' => 2437,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(NetworkInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('WiFi')
        ->assertSee('MyNetwork')
        ->assertSee('70%')
        ->assertSee('-65 dBm');
});

it('shows dns servers when available', function (): void {
    $user = User::factory()->create();

    $onesiBox = OnesiBox::factory()->create([
        'network_type' => 'ethernet',
        'ip_address' => '192.168.1.100',
        'dns_servers' => ['8.8.8.8', '8.8.4.4'],
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(NetworkInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('8.8.8.8, 8.8.4.4');
});

it('computes isWifi correctly', function (): void {
    $user = User::factory()->create();

    $wifiBox = OnesiBox::factory()->create(['network_type' => 'wifi', 'ip_address' => '192.168.1.1']);
    $ethernetBox = OnesiBox::factory()->create(['network_type' => 'ethernet', 'ip_address' => '192.168.1.2']);

    Livewire::actingAs($user)
        ->test(NetworkInfo::class, ['onesiBox' => $wifiBox])
        ->assertSet('isWifi', true);

    Livewire::actingAs($user)
        ->test(NetworkInfo::class, ['onesiBox' => $ethernetBox])
        ->assertSet('isWifi', false);
});

it('formats wifi frequency correctly', function (): void {
    $user = User::factory()->create();

    $onesiBox = OnesiBox::factory()->create([
        'network_type' => 'wifi',
        'ip_address' => '192.168.1.100',
        'wifi_frequency' => 5180,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(NetworkInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('5.2 GHz');
});

it('shows correct signal label for various signal strengths', function (int $percent, string $expectedLabel): void {
    $user = User::factory()->create();

    $onesiBox = OnesiBox::factory()->create([
        'network_type' => 'wifi',
        'ip_address' => '192.168.1.100',
        'wifi_signal_percent' => $percent,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(NetworkInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee($expectedLabel);
})->with([
    [90, 'Eccellente'],
    [70, 'Buono'],
    [50, 'Discreto'],
    [30, 'Debole'],
    [10, 'Molto debole'],
]);
