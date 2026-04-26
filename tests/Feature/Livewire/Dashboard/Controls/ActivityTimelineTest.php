<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\PlaybackEventType;
use App\Livewire\Dashboard\Controls\ActivityTimeline;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(fn () => freezeTestTime('2026-04-26 12:00:00'));
afterEach(fn () => releaseTestTime());

it('renders a friendly empty state when there is no activity today', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->withCaregiver($user)->create();

    Livewire::actingAs($user)
        ->test(ActivityTimeline::class, ['onesiBox' => $box])
        ->assertSee('Nessuna attività registrata oggi');
});

it('renders entries for the caregiver of the box', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->withCaregiver($user)->create();

    PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => PlaybackEventType::Started,
        'media_url' => 'https://www.jw.org/audio.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subMinutes(30),
    ]);
    PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => PlaybackEventType::Completed,
        'media_url' => 'https://www.jw.org/audio.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subMinutes(5),
    ]);

    Livewire::actingAs($user)
        ->test(ActivityTimeline::class, ['onesiBox' => $box])
        ->assertSee('www.jw.org')
        ->assertDontSee('Nessuna attività');
});

it('renders times in Europe/Rome (not UTC)', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->withCaregiver($user)->create();

    // 09:00:00 UTC == 11:00:00 Europe/Rome (CEST in late April).
    PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => PlaybackEventType::Started,
        'media_url' => 'https://www.jw.org/audio.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::parse('2026-04-26 09:00:00', 'UTC'),
    ]);
    PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => PlaybackEventType::Completed,
        'media_url' => 'https://www.jw.org/audio.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::parse('2026-04-26 09:30:00', 'UTC'),
    ]);

    Livewire::actingAs($user)
        ->test(ActivityTimeline::class, ['onesiBox' => $box])
        ->assertSee('11:00')
        ->assertSee('11:30')
        ->assertDontSee('09:00');
});

it('returns a forbidden response on mount for a user who is not a caregiver of the box', function (): void {
    $stranger = User::factory()->create();
    $box = OnesiBox::factory()->create();

    Livewire::actingAs($stranger)
        ->test(ActivityTimeline::class, ['onesiBox' => $box])
        ->assertForbidden();
});

it('renders read-only caregivers the same as full-permission caregivers', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->withCaregiver($user, OnesiBoxPermission::ReadOnly)->create();

    Livewire::actingAs($user)
        ->test(ActivityTimeline::class, ['onesiBox' => $box])
        ->assertSee('Nessuna attività registrata oggi');
});
