<?php

declare(strict_types=1);

use App\Actions\Sessions\StopPlaybackSessionAction;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\PlaybackSessionStatus;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;

beforeEach(function (): void {
    $this->onesiBox = OnesiBox::factory()->online()->create();
    $this->stopAction = resolve(StopPlaybackSessionAction::class);
});

test('stopping active session sets status to stopped and ended_at', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->create();

    $result = $this->stopAction->execute($session);

    expect($result->status)->toBe(PlaybackSessionStatus::Stopped);
    expect($result->ended_at)->not->toBeNull();
});

test('stopping session sends stop_media command', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->create();

    $this->stopAction->execute($session);

    $stopCommand = $this->onesiBox->commands()
        ->where('type', CommandType::StopMedia)
        ->latest()
        ->first();

    expect($stopCommand)->not->toBeNull();
});

test('stopping session cancels pending play_media commands for the session', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->active()
        ->create();

    // Create a pending play_media command with session_id
    $pendingCommand = Command::factory()
        ->for($this->onesiBox, 'onesiBox')
        ->ofType(CommandType::PlayMedia)
        ->withPayload([
            'url' => 'https://www.jw.org/video/test.mp4',
            'media_type' => 'video',
            'session_id' => $session->uuid,
        ])
        ->pending()
        ->create();

    $this->stopAction->execute($session);

    $pendingCommand->refresh();
    expect($pendingCommand->status)->toBe(CommandStatus::Cancelled);
});

test('stopping already completed session does nothing', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->onesiBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();

    $session = PlaybackSession::factory()
        ->forOnesiBox($this->onesiBox)
        ->forPlaylist($playlist)
        ->completed()
        ->create();

    $originalEndedAt = $session->ended_at;

    $result = $this->stopAction->execute($session);

    expect($result->status)->toBe(PlaybackSessionStatus::Completed);
    expect($result->ended_at->toDateTimeString())->toBe($originalEndedAt->toDateTimeString());
});

test('stopping session on offline onesibox still marks session as stopped', function (): void {
    $offlineBox = OnesiBox::factory()->offline()->create();
    $playlist = Playlist::factory()->forOnesiBox($offlineBox)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();

    $session = PlaybackSession::factory()
        ->forOnesiBox($offlineBox)
        ->forPlaylist($playlist)
        ->active()
        ->create();

    $result = $this->stopAction->execute($session);

    expect($result->status)->toBe(PlaybackSessionStatus::Stopped);
    expect($result->ended_at)->not->toBeNull();
});
