<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\VolumeControl;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Livewire;

test('volume control shows 5 preset levels', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSee('60%')
        ->assertSee('70%')
        ->assertSee('80%')
        ->assertSee('90%')
        ->assertSee('100%')
        ->assertStatus(200);
});

test('volume control highlights current volume level', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => 60]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('currentVolume', 60)
        ->assertStatus(200);
});

test('volume control creates command when level selected', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => 80]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setVolume', 70);

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::SetVolume)
        ->where('status', CommandStatus::Pending)
        ->first();

    expect($command)->not->toBeNull();
    expect($command->payload)->toBe(['level' => 70]);
});

test('volume control is disabled for readonly users', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('canControl', false)
        ->assertStatus(200);
});

test('volume control is disabled when onesibox is offline', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->offline()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('isOnline', false)
        ->assertStatus(200);
});

test('volume control validates level must be in preset values', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setVolume', 50)
        ->assertHasErrors(['level']);
});

test('readonly user cannot send volume command', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setVolume', 70);

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::SetVolume)
        ->first();

    expect($command)->toBeNull();
});

test('volume control dispatches notification on successful command', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setVolume', 60)
        ->assertDispatched('notify');
});

test('volume control rounds to nearest preset when volume is between presets', function (int $actualVolume, int $expectedPreset): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => $actualVolume]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('currentVolume', $expectedPreset);
})->with([
    '55% rounds to 60%' => [55, 60],
    '74% rounds to 70%' => [74, 70],
    '96% rounds to 100%' => [96, 100],
    '50% rounds to 60%' => [50, 60],
    '65% rounds to 60% (equidistant favors lower)' => [65, 60],
    '75% rounds to 70% (equidistant favors lower)' => [75, 70],
]);

test('volume control shows exact preset when volume matches', function (int $preset): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => $preset]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('currentVolume', $preset);
})->with([60, 70, 80, 90, 100]);
