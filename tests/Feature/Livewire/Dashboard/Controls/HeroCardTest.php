<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Livewire\Dashboard\Controls\HeroCard;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('renders the idle variant with "in attesa" copy when state is idle', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'idle'])
        ->assertSee('In attesa')
        ->assertSeeHtml('data-hero-state="idle"');
});

it('renders the media variant with title, type label and progress bar', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://www.youtube.com/watch?v=abc',
        'current_media_type' => 'audio',
        'current_media_title' => 'Ave Maria',
        'current_media_position' => 60,
        'current_media_duration' => 180,
    ]);

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'media'])
        ->assertSee('AUDIO')
        ->assertSee('Ave Maria')
        ->assertSeeHtml('data-hero-state="media"')
        ->assertSeeHtml('role="progressbar"');
});

it('does not render progress bar when position/duration are null', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/x.mp3',
        'current_media_type' => 'audio',
    ]);

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'media'])
        ->assertDontSeeHtml('role="progressbar"');
});

it('renders the call variant with meeting id', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '123456789',
        'current_meeting_joined_at' => now()->subMinutes(12),
    ]);

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'call'])
        ->assertSee('Chiamata in corso')
        ->assertSee('123456789')
        ->assertSeeHtml('data-hero-state="call"');
});

it('renders the offline variant with warning styling and last seen', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->offline()->create(['last_seen_at' => now()->subHours(2)]);

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'offline'])
        ->assertSee('Dispositivo offline')
        ->assertSeeHtml('data-hero-state="offline"');
});

it('pause() dispatches a Pause command when media is playing and not paused', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/x.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock) use ($box): void {
        $mock->shouldReceive('sendPauseCommand')
            ->once()
            ->withArgs(fn ($b): bool => $b->is($box));
    });

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'media', 'isPaused' => false])
        ->call('pause');
});

it('resume() dispatches a Resume command when media is paused', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/x.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendResumeCommand')->once();
    });

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'media', 'isPaused' => true])
        ->call('resume');
});

it('stop() dispatches a Stop command on the current media', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/x.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendStopCommand')->once();
    });

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'media'])
        ->call('stop');
});

it('leaveZoom() dispatches a LeaveZoom command while on a call', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '123',
    ]);
    $box->caregivers()->attach($user, ['permission' => OnesiBoxPermission::Full->value]);

    $this->mock(OnesiBoxCommandServiceInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('sendLeaveZoomCommand')->once();
    });

    Livewire::actingAs($user)
        ->test(HeroCard::class, ['onesiBox' => $box, 'state' => 'call'])
        ->call('leaveZoom');
});
