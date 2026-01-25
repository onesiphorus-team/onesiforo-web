<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\SystemInfo;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;

test('system info displays cpu usage', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'cpu_usage' => 45,
        'last_system_info_at' => now(),
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('45%')
        ->assertStatus(200);
});

test('system info displays memory usage', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'memory_usage' => 60,
        'last_system_info_at' => now(),
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('60%')
        ->assertStatus(200);
});

test('system info displays disk usage', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'disk_usage' => 75,
        'last_system_info_at' => now(),
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('75%')
        ->assertStatus(200);
});

test('system info displays temperature', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'temperature' => 52.5,
        'last_system_info_at' => now(),
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('52.5°C')
        ->assertStatus(200);
});

test('system info displays uptime formatted', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'uptime' => 172800, // 2 days in seconds
        'last_system_info_at' => now(),
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('2 giorni')
        ->assertStatus(200);
});

test('system info shows no data message when system info not available', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'cpu_usage' => null,
        'memory_usage' => null,
        'disk_usage' => null,
        'last_system_info_at' => null,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('Nessuna informazione di sistema disponibile')
        ->assertStatus(200);
});

test('full permission user can request system info refresh', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'cpu_usage' => 50,
        'last_system_info_at' => now()->subHour(),
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->call('requestRefresh')
        ->assertDispatched('notify');

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::GetSystemInfo)
        ->where('status', CommandStatus::Pending)
        ->first();

    expect($command)->not->toBeNull();
});

test('readonly user cannot request system info refresh', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create([
        'cpu_usage' => 50,
        'last_system_info_at' => now(),
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->assertSet('canControl', false)
        ->call('requestRefresh');

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::GetSystemInfo)
        ->first();

    expect($command)->toBeNull();
});

test('system info shows last updated time', function (): void {
    $user = User::factory()->create();
    $lastUpdate = now()->subMinutes(5);
    $onesiBox = OnesiBox::factory()->online()->create([
        'cpu_usage' => 50,
        'last_system_info_at' => $lastUpdate,
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->assertSee('5 minuti fa')
        ->assertStatus(200);
});

test('system info is disabled when onesibox is offline', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->offline()->create([
        'cpu_usage' => 50,
        'last_system_info_at' => now()->subHours(2),
    ]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(SystemInfo::class, ['onesiBox' => $onesiBox])
        ->assertSet('isOnline', false)
        ->call('requestRefresh');

    // Should not create command when offline
    $command = Command::query()->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::GetSystemInfo)
        ->first();

    expect($command)->toBeNull();
});
