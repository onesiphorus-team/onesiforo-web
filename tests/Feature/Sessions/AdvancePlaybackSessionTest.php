<?php

declare(strict_types=1);

use App\Actions\Sessions\AdvancePlaybackSessionAction;
use App\Enums\CommandType;
use App\Enums\PlaybackEventType;
use App\Enums\PlaybackSessionStatus;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;

beforeEach(function (): void {
    $this->onesiBox = OnesiBox::factory()->online()->create();
    $this->advanceAction = resolve(AdvancePlaybackSessionAction::class);
});

test('completed event advances to next video and creates play_media command', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create(['media_url' => 'https://www.jw.org/video/1.mp4']);
    PlaylistItem::factory()->for($playlist)->atPosition(1)->create(['media_url' => 'https://www.jw.org/video/2.mp4']);

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->withDuration(60)
        ->create(['current_position' => 0, 'items_played' => 0]);

    $this->advanceAction->execute($this->onesiBox, PlaybackEventType::Completed);

    $session->refresh();

    expect($session->current_position)->toBe(1);
    expect($session->items_played)->toBe(1);
    expect($session->status)->toBe(PlaybackSessionStatus::Active);

    $command = $this->onesiBox->commands()
        ->where('type', CommandType::PlayMedia)
        ->latest()
        ->first();

    expect($command)
        ->not->toBeNull()
        ->payload->toHaveKey('url', 'https://www.jw.org/video/2.mp4')
        ->payload->toHaveKey('session_id', $session->uuid);
});

test('completed event on last video ends session as completed', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->withDuration(60)
        ->create(['current_position' => 0, 'items_played' => 0]);

    $this->advanceAction->execute($this->onesiBox, PlaybackEventType::Completed);

    $session->refresh();

    expect($session->status)->toBe(PlaybackSessionStatus::Completed);
    expect($session->ended_at)->not->toBeNull();
    expect($session->items_played)->toBe(1);
    expect($session->current_position)->toBe(1);
});

test('completed event when time expired ends session', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(1)->create();

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->withDuration(1)
        ->create([
            'current_position' => 0,
            'items_played' => 0,
            'started_at' => now()->subMinutes(2),
        ]);

    $this->advanceAction->execute($this->onesiBox, PlaybackEventType::Completed);

    $session->refresh();

    expect($session->status)->toBe(PlaybackSessionStatus::Completed);
    expect($session->ended_at)->not->toBeNull();
});

test('error event skips to next video and increments items_skipped', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(1)->create(['media_url' => 'https://www.jw.org/video/2.mp4']);

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->withDuration(60)
        ->create(['current_position' => 0, 'items_skipped' => 0]);

    $this->advanceAction->execute($this->onesiBox, PlaybackEventType::Error);

    $session->refresh();

    expect($session->current_position)->toBe(1);
    expect($session->items_skipped)->toBe(1);
    expect($session->status)->toBe(PlaybackSessionStatus::Active);
});

test('no active session does nothing', function (): void {
    // No active session exists — existing behavior preserved
    $commandCountBefore = $this->onesiBox->commands()->count();

    $this->advanceAction->execute($this->onesiBox, PlaybackEventType::Completed);

    expect($this->onesiBox->commands()->count())->toBe($commandCountBefore);
});

test('completed event for non-session playback does nothing', function (): void {
    // Simulate a playback event without an active session
    $commandCountBefore = $this->onesiBox->commands()->count();

    $this->advanceAction->execute($this->onesiBox, PlaybackEventType::Completed);

    expect($this->onesiBox->commands()->count())->toBe($commandCountBefore);
});

test('advance preserves existing playback api behavior', function (): void {
    $onesiBox = OnesiBox::factory()->create();
    $token = $onesiBox->createToken('onesibox-api-token');

    // No active session — existing behavior must work
    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'completed',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$token->plainTextToken}"]
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.logged', true);
});
