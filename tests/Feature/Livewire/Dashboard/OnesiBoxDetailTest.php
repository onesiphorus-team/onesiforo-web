<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Enums\PlaybackSessionStatus;
use App\Livewire\Dashboard\Controls\BottomBar;
use App\Livewire\Dashboard\Controls\HeroCard;
use App\Livewire\Dashboard\Controls\QuickPlaySheet;
use App\Livewire\Dashboard\OnesiBoxDetail;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Models\PlaybackSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('heroState returns offline when the box is offline', function (): void {
    $box = OnesiBox::factory()->offline()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'offline');
});

it('heroState returns call when the box is in a Zoom call', function (): void {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '1234567890',
    ]);
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'call');
});

it('heroState returns media when a media is playing and no call is active', function (): void {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'media');
});

it('heroState returns idle when the box is online and nothing is playing', function (): void {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Idle,
    ]);
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'idle');
});

// Task 5 tests

it('isInCall reflects the Calling status', function (): void {
    $boxInCall = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '1234567890',
    ]);
    $boxInCall->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $boxInCall])
        ->assertSet('isInCall', true);

    $boxIdle = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);
    $boxIdle->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $boxIdle])
        ->assertSet('isInCall', false);
});

it('isMediaPaused is true only when the last PlaybackEvent is Paused', function (): void {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    PlaybackEvent::factory()->for($box, 'onesiBox')->started()->create();
    PlaybackEvent::factory()->for($box, 'onesiBox')->paused()->create();

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('isMediaPaused', true);
});

it('isMediaPaused is false when the last PlaybackEvent is not Paused', function (): void {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    PlaybackEvent::factory()->for($box, 'onesiBox')->paused()->create();
    PlaybackEvent::factory()->for($box, 'onesiBox')->resumed()->create();

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('isMediaPaused', false);
});

it('accordionDefaults opens session when an active session exists', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    PlaybackSession::factory()->for($box, 'onesiBox')->create([
        'status' => PlaybackSessionStatus::Active,
    ]);

    $defaults = livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->get('accordionDefaults');

    expect($defaults)->toHaveKey('session', true);
});

it('accordionDefaults opens commands when pending commands exist', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);

    Command::factory()->for($box, 'onesiBox')->create([
        'status' => CommandStatus::Pending,
    ]);

    $defaults = livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->get('accordionDefaults');

    expect($defaults)->toHaveKey('commands', true);
});

it('mounts HeroCard, BottomBar and QuickPlaySheet in the detail view', function (): void {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full->value]);

    Livewire::actingAs($this->user)
        ->test(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSeeLivewire(HeroCard::class)
        ->assertSeeLivewire(BottomBar::class)
        ->assertSeeLivewire(QuickPlaySheet::class);
});
