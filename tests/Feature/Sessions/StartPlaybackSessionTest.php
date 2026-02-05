<?php

declare(strict_types=1);

use App\Actions\Playlists\CreatePlaylistAction;
use App\Actions\Sessions\StartPlaybackSessionAction;
use App\Enums\CommandType;
use App\Enums\PlaybackSessionStatus;
use App\Models\OnesiBox;

beforeEach(function (): void {
    $this->onesiBox = OnesiBox::factory()->online()->create();
    $this->createPlaylistAction = resolve(CreatePlaylistAction::class);
    $this->startAction = resolve(StartPlaybackSessionAction::class);
});

test('starting a session creates playlist, session, and first play_media command', function (): void {
    $playlist = $this->createPlaylistAction->execute($this->onesiBox, [
        ['url' => 'https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODBible/pub-nwtsv_I_1_VIDEO'],
        ['url' => 'https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODBible/pub-nwtsv_I_2_VIDEO'],
    ]);

    $session = $this->startAction->execute($this->onesiBox, $playlist, 60);

    expect($session)
        ->status->toBe(PlaybackSessionStatus::Active)
        ->duration_minutes->toBe(60)
        ->current_position->toBe(0)
        ->items_played->toBe(0)
        ->items_skipped->toBe(0);

    expect($session->uuid)->not->toBeEmpty();
    expect($session->started_at)->not->toBeNull();

    // Verify first play_media command was created
    $command = $this->onesiBox->commands()->latest()->first();
    expect($command)
        ->type->toBe(CommandType::PlayMedia)
        ->payload->toHaveKey('session_id', $session->uuid)
        ->payload->toHaveKey('url', 'https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODBible/pub-nwtsv_I_1_VIDEO');
});

test('playlist items are created with correct positions', function (): void {
    $playlist = $this->createPlaylistAction->execute($this->onesiBox, [
        ['url' => 'https://www.jw.org/video/1.mp4', 'title' => 'Video 1'],
        ['url' => 'https://www.jw.org/video/2.mp4', 'title' => 'Video 2'],
        ['url' => 'https://www.jw.org/video/3.mp4', 'title' => 'Video 3'],
    ]);

    expect($playlist->items)->toHaveCount(3);

    $items = $playlist->items()->orderBy('position')->get();
    expect($items[0]->position)->toBe(0);
    expect($items[0]->title)->toBe('Video 1');
    expect($items[1]->position)->toBe(1);
    expect($items[2]->position)->toBe(2);
});

test('starting new session stops existing active session', function (): void {
    $playlist1 = $this->createPlaylistAction->execute($this->onesiBox, [
        ['url' => 'https://www.jw.org/video/1.mp4'],
    ]);
    $session1 = $this->startAction->execute($this->onesiBox, $playlist1, 60);

    $playlist2 = $this->createPlaylistAction->execute($this->onesiBox, [
        ['url' => 'https://www.jw.org/video/2.mp4'],
    ]);
    $session2 = $this->startAction->execute($this->onesiBox, $playlist2, 120);

    $session1->refresh();

    expect($session1->status)->toBe(PlaybackSessionStatus::Stopped);
    expect($session1->ended_at)->not->toBeNull();

    expect($session2->status)->toBe(PlaybackSessionStatus::Active);
});

test('cannot start session on offline onesibox', function (): void {
    $offlineBox = OnesiBox::factory()->offline()->create();
    $playlist = $this->createPlaylistAction->execute($offlineBox, [
        ['url' => 'https://www.jw.org/video/1.mp4'],
    ]);

    $this->startAction->execute($offlineBox, $playlist, 60);
})->throws(App\Exceptions\OnesiBoxOfflineException::class);

test('session is associated with correct onesibox and playlist', function (): void {
    $playlist = $this->createPlaylistAction->execute($this->onesiBox, [
        ['url' => 'https://www.jw.org/video/1.mp4'],
    ]);

    $session = $this->startAction->execute($this->onesiBox, $playlist, 30);

    expect($session->onesi_box_id)->toBe($this->onesiBox->id);
    expect($session->playlist_id)->toBe($playlist->id);
    expect($session->duration_minutes)->toBe(30);
});
