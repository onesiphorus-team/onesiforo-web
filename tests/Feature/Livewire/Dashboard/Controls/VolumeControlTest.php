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

test('volume control shows 6 preset levels (without mute)', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSee('50%')
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

test('volume control creates command when preset level selected', function (): void {
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

test('volume control validates level must be a multiple of 5 between 0 and 100', function (int $invalidLevel): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setVolume', $invalidLevel)
        ->assertHasErrors(['level']);
})->with([
    'not multiple of 5: 33' => 33,
    'not multiple of 5: 42' => 42,
    'over max: 105' => 105,
    'negative: -10' => -10,
]);

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

test('mute sends volume 0 command', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => 75]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setSliderVolume', 0);

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::SetVolume)
        ->where('status', CommandStatus::Pending)
        ->first();

    expect($command)->not->toBeNull();
    expect($command->payload)->toBe(['level' => 0]);
});

test('volume control rounds to nearest multiple of 5 when volume is between steps', function (int $actualVolume, int $expectedVolume): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => $actualVolume]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('currentVolume', $expectedVolume);
})->with([
    '0 stays 0' => [0, 0],
    '1 rounds to 0' => [1, 0],
    '2 rounds to 0' => [2, 0],
    '3 rounds to 5' => [3, 5],
    '7 rounds to 5' => [7, 5],
    '8 rounds to 10' => [8, 10],
    '22 rounds to 20' => [22, 20],
    '23 rounds to 25' => [23, 25],
    '47 rounds to 45' => [47, 45],
    '48 rounds to 50' => [48, 50],
    '73 rounds to 75' => [73, 75],
    '96 rounds to 95' => [96, 95],
    '98 rounds to 100' => [98, 100],
    '100 stays 100' => [100, 100],
]);

test('volume control shows exact value when volume is already a multiple of 5', function (int $volume): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => $volume]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('currentVolume', $volume);
})->with([0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100]);

test('nearest preset highlights closest preset button for non-preset volumes', function (int $volume, int $expectedPreset): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => $volume]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('nearestPreset', $expectedPreset);
})->with([
    '0 nearest to 50' => [0, 50],
    '5 nearest to 50' => [5, 50],
    '25 nearest to 50' => [25, 50],
    '30 nearest to 50' => [30, 50],
    '35 nearest to 50' => [35, 50],
    '45 nearest to 50' => [45, 50],
    '55 nearest to 50' => [55, 50],
    '65 nearest to 60' => [65, 60],
    '75 nearest to 70' => [75, 70],
    '85 nearest to 80' => [85, 80],
    '95 nearest to 90' => [95, 90],
]);

test('nearest preset matches exact preset when volume is a preset', function (int $preset): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => $preset]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('nearestPreset', $preset);
})->with([50, 60, 70, 80, 90, 100]);

test('slider volume creates command with valid level', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => 80]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setSliderVolume', 35);

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::SetVolume)
        ->where('status', CommandStatus::Pending)
        ->first();

    expect($command)->not->toBeNull();
    expect($command->payload)->toBe(['level' => 35]);
});

test('slider volume rejects level not multiple of 5', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setSliderVolume', 33)
        ->assertHasErrors(['level']);
});

test('slider volume rejects level out of range', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setSliderVolume', 105)
        ->assertHasErrors(['level']);
});

test('readonly user cannot use slider volume', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->call('setSliderVolume', 35);

    $command = Command::query()->where('onesi_box_id', $onesiBox->id)
        ->where('type', CommandType::SetVolume)
        ->first();

    expect($command)->toBeNull();
});

test('slider volume syncs with preset button press', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => 80]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('sliderVolume', 80)
        ->call('setVolume', 60)
        ->assertSet('sliderVolume', 60);
});

test('slider volume is initialized from onesibox volume on mount', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['volume' => 45]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('sliderVolume', 45);
});

test('volume presets do not include 0 (mute handled by toggle)', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full]);

    Livewire::actingAs($user)
        ->test(VolumeControl::class, ['onesiBox' => $onesiBox])
        ->assertSet('volumeLevels', [50, 60, 70, 80, 90, 100]);
});
