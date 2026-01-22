<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\ZoomCall;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Livewire\Livewire;
use Mockery\MockInterface;

it('sends zoom command with full permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendZoomCommand')
            ->once()
            ->withArgs(fn ($box, $id, $pass): bool => $box instanceof OnesiBox && $id === '123456789' && $pass === 'secret');
    });

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('meetingId', '123456789')
        ->set('password', 'secret')
        ->call('startCall')
        ->assertHasNoErrors();
});

it('blocks zoom command with readonly permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('meetingId', '123456789')
        ->call('startCall')
        ->assertForbidden();
});

it('validates meeting ID format', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->set('meetingId', '123')  // Too short
        ->call('startCall')
        ->assertHasErrors(['meetingId' => 'regex']);
});

it('sends end call command with full permission', function (): void {
    $user = User::factory()->create();
    $onesiBox = OnesiBox::factory()->online()->create();
    $onesiBox->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendStopCommand')
            ->once()
            ->withArgs(fn ($box): bool => $box instanceof OnesiBox);
    });

    Livewire::actingAs($user)
        ->test(ZoomCall::class, ['onesiBox' => $onesiBox])
        ->call('endCall')
        ->assertHasNoErrors();
});
