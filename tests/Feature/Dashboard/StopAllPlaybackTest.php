<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Livewire\Dashboard\Controls\StopAllPlayback;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Livewire\Livewire;
use Mockery\MockInterface;

it('shows confirmation dialog when stop is requested', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(StopAllPlayback::class, ['onesiBox' => $onesiBox])
        ->assertSet('showConfirmation', false)
        ->call('confirmStop')
        ->assertSet('showConfirmation', true);
});

it('hides confirmation dialog when cancelled', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(StopAllPlayback::class, ['onesiBox' => $onesiBox])
        ->call('confirmStop')
        ->assertSet('showConfirmation', true)
        ->call('cancelStop')
        ->assertSet('showConfirmation', false);
});

it('sends stop command when confirmed', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Playing]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendStopCommand')
            ->once()
            ->withArgs(fn ($box): bool => $box instanceof OnesiBox);
    });

    Livewire::actingAs($user)
        ->test(StopAllPlayback::class, ['onesiBox' => $onesiBox])
        ->call('confirmStop')
        ->call('stopAll')
        ->assertSet('showConfirmation', false)
        ->assertHasNoErrors();
});

it('sends both stop and leave zoom commands when in a call', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Calling]);
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendStopCommand')
            ->once()
            ->withArgs(fn ($box): bool => $box instanceof OnesiBox);
        $mock->shouldReceive('sendLeaveZoomCommand')
            ->once()
            ->withArgs(fn ($box): bool => $box instanceof OnesiBox);
    });

    Livewire::actingAs($user)
        ->test(StopAllPlayback::class, ['onesiBox' => $onesiBox])
        ->call('confirmStop')
        ->call('stopAll')
        ->assertHasNoErrors();
});

it('blocks stop all with readonly permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(StopAllPlayback::class, ['onesiBox' => $onesiBox])
        ->call('confirmStop')
        ->call('stopAll')
        ->assertForbidden();
});
