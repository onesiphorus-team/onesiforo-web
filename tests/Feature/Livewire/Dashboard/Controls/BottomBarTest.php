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

it('renders the 4 slots when the user can control and the box is online', function (): void {
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

it('renders nothing when the user has no caregiver relationship', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    // No caregivers attach — user has no permission

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->assertDontSeeHtml('data-slot="stop"')
        ->assertDontSeeHtml('data-slot="volume"');
});

it('stopAll dispatches Stop when media is playing, and also LeaveZoom if in a call', function (): void {
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

it('renders a modal trigger that mounts the VolumeControl component', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->assertSet('showVolume', false)
        ->call('openVolume')
        ->assertSet('showVolume', true);
});

it('openNew() dispatches open-quick-play without a preselected tab', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->call('openNew')
        ->assertDispatched('open-quick-play');
});

it('callAction() dispatches open-quick-play with tab=zoom when no active call', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->call('callAction')
        ->assertDispatched('open-quick-play', tab: 'zoom');
});

it('callAction() ends the current call when in a call', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '999',
    ]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendLeaveZoomCommand')->once();
    });

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->call('callAction');
});

it('stopAll() is forbidden for a user without Full permission', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Playing]);
    // No caregivers attached

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->call('stopAll')
        ->assertForbidden();
});

it('openNew() is forbidden for a user without Full permission', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    // No caregivers attached

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->call('openNew')
        ->assertForbidden();
});

it('callAction() is forbidden for a user without Full permission', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '999',
    ]);
    // No caregivers attached

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->call('callAction')
        ->assertForbidden();
});

it('displays the current volume percentage on the volume button', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['volume' => 65]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->assertSeeHtml('Volume 65%')
        ->assertSeeHtml('>65%<');
});

it('displays "Muto" when volume is zero', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['volume' => 0]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->assertSeeHtml('Muto');
});

it('renders the call slot labelled "Zoom" when not in a call', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($user)
        ->test(BottomBar::class, ['onesiBox' => $box])
        ->assertSeeHtml('>Zoom<')
        ->assertSeeHtml('Avvia chiamata Zoom')
        ->assertDontSeeHtml('>Chiama<');
});
