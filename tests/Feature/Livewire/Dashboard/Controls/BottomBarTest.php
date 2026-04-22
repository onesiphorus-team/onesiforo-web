<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Livewire\Dashboard\Controls\BottomBar;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('renders the 4 slots when the user can control and the box is online', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->assertSeeHtml('data-slot="stop"')
        ->assertSeeHtml('data-slot="volume"')
        ->assertSeeHtml('data-slot="new"')
        ->assertSeeHtml('data-slot="call"');
});

it('renders nothing when the user has no caregiver relationship', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    // No caregivers attach — user has no permission

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->assertDontSeeHtml('data-slot="stop"')
        ->assertDontSeeHtml('data-slot="volume"');
});

it('stopAll dispatches Stop when media is playing, and also LeaveZoom if in a call', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_media_url' => 'https://x/y.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendStopCommand')->once();
        $mock->shouldReceive('sendLeaveZoomCommand')->once();
    });

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->call('stopAll');
});
