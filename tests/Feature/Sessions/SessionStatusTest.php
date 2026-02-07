<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Livewire\Dashboard\Controls\SessionStatus;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->onesiBox = OnesiBox::factory()->online()->create();
    $this->user = User::factory()->create();
    $this->onesiBox->caregivers()->attach($this->user, ['permission' => OnesiBoxPermission::Full]);
    $this->actingAs($this->user);
});

test('shows active session details with video title and progress', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->withTitle('Video 1')->create();
    PlaylistItem::factory()->for($playlist)->atPosition(1)->withTitle('Video 2')->create();
    PlaylistItem::factory()->for($playlist)->atPosition(2)->withTitle('Video 3')->create();

    PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->withDuration(60)
        ->create(['current_position' => 0, 'items_played' => 0]);

    Livewire::test(SessionStatus::class, ['onesiBox' => $this->onesiBox])
        ->assertSee('Sessione in corso')
        ->assertSee('Video 1')
        ->assertSee('Video 1 di 3');
});

test('shows no content when no active session', function (): void {
    Livewire::test(SessionStatus::class, ['onesiBox' => $this->onesiBox])
        ->assertDontSee('Sessione in corso');
});

test('shows progress when session has advanced', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->withTitle('Video 1')->create();
    PlaylistItem::factory()->for($playlist)->atPosition(1)->withTitle('Video 2')->create();

    PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->withDuration(60)
        ->create(['current_position' => 1, 'items_played' => 1]);

    Livewire::test(SessionStatus::class, ['onesiBox' => $this->onesiBox])
        ->assertSee('Video 2 di 2')
        ->assertSee('Video 2');
});

test('read-only caregiver can see session status', function (): void {
    $readOnlyUser = User::factory()->create();
    $this->onesiBox->caregivers()->attach($readOnlyUser, ['permission' => OnesiBoxPermission::ReadOnly]);

    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->withTitle('Test Video')->create();

    PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->create();

    $this->actingAs($readOnlyUser);

    Livewire::test(SessionStatus::class, ['onesiBox' => $this->onesiBox])
        ->assertSee('Sessione in corso')
        ->assertSee('Test Video');
});

test('shows items played and skipped counts', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(1)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(2)->create();

    PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->create(['items_played' => 2, 'items_skipped' => 1, 'current_position' => 2]);

    $component = Livewire::test(SessionStatus::class, ['onesiBox' => $this->onesiBox]);
    $component->assertSee('2');  // items_played
    $component->assertSee('1');  // items_skipped
});
