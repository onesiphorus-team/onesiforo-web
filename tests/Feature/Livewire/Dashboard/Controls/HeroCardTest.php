<?php

declare(strict_types=1);

use App\Enums\OnesiBoxStatus;
use App\Livewire\Dashboard\Controls\HeroCard;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
