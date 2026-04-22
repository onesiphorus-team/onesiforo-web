<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Livewire\Dashboard\OnesiBoxDetail;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('heroState returns offline when the box is offline', function () {
    $box = OnesiBox::factory()->offline()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'offline');
});

it('heroState returns call when the box is in a Zoom call', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '1234567890',
    ]);
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'call');
});

it('heroState returns media when a media is playing and no call is active', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'media');
});

it('heroState returns idle when the box is online and nothing is playing', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Idle,
    ]);
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'idle');
});
