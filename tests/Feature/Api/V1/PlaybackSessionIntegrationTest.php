<?php

declare(strict_types=1);

use App\Actions\Playlists\CreatePlaylistAction;
use App\Actions\Sessions\StartPlaybackSessionAction;
use App\Actions\Sessions\StopPlaybackSessionAction;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\PlaybackSessionStatus;
use App\Models\OnesiBox;

beforeEach(function (): void {
    $this->onesiBox = OnesiBox::factory()->online()->create();
    $this->token = $this->onesiBox->createToken('onesibox-api-token');
    $this->createPlaylistAction = resolve(CreatePlaylistAction::class);
    $this->startAction = resolve(StartPlaybackSessionAction::class);
});

test('full session flow: start, play through all videos, session completes', function (): void {
    $playlist = $this->createPlaylistAction->execute($this->onesiBox, [
        ['url' => 'https://www.jw.org/video/1.mp4', 'title' => 'Video 1'],
        ['url' => 'https://www.jw.org/video/2.mp4', 'title' => 'Video 2'],
        ['url' => 'https://www.jw.org/video/3.mp4', 'title' => 'Video 3'],
    ]);

    $session = $this->startAction->execute($this->onesiBox, $playlist, 60);

    // Verify first play_media command was created
    $firstCommand = $this->onesiBox->commands()
        ->where('type', CommandType::PlayMedia)
        ->latest('id')
        ->first();
    expect($firstCommand->payload['url'])->toBe('https://www.jw.org/video/1.mp4');
    expect($firstCommand->payload['session_id'])->toBe($session->uuid);

    // Simulate completed event for video 1 via API
    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'completed',
            'media_url' => 'https://www.jw.org/video/1.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$this->token->plainTextToken}"]
    );
    $response->assertOk();

    $session->refresh();
    expect($session->current_position)->toBe(1);
    expect($session->items_played)->toBe(1);
    expect($session->status)->toBe(PlaybackSessionStatus::Active);

    // Verify second play_media command was created
    $secondCommand = $this->onesiBox->commands()
        ->where('type', CommandType::PlayMedia)
        ->latest('id')
        ->first();
    expect($secondCommand->payload['url'])->toBe('https://www.jw.org/video/2.mp4');

    // Simulate completed event for video 2
    $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'completed',
            'media_url' => 'https://www.jw.org/video/2.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$this->token->plainTextToken}"]
    )->assertOk();

    $session->refresh();
    expect($session->current_position)->toBe(2);
    expect($session->items_played)->toBe(2);

    // Simulate completed event for video 3 (last video)
    $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'completed',
            'media_url' => 'https://www.jw.org/video/3.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$this->token->plainTextToken}"]
    )->assertOk();

    $session->refresh();
    expect($session->status)->toBe(PlaybackSessionStatus::Completed);
    expect($session->ended_at)->not->toBeNull();
    expect($session->items_played)->toBe(3);
});

test('error events skip to next video during session', function (): void {
    $playlist = $this->createPlaylistAction->execute($this->onesiBox, [
        ['url' => 'https://www.jw.org/video/1.mp4'],
        ['url' => 'https://www.jw.org/video/2.mp4'],
    ]);

    $session = $this->startAction->execute($this->onesiBox, $playlist, 60);

    // Simulate error event for video 1
    $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'error',
            'media_url' => 'https://www.jw.org/video/1.mp4',
            'media_type' => 'video',
            'error_message' => 'Codec non supportato',
        ],
        ['Authorization' => "Bearer {$this->token->plainTextToken}"]
    )->assertOk();

    $session->refresh();
    expect($session->current_position)->toBe(1);
    expect($session->items_skipped)->toBe(1);
    expect($session->items_played)->toBe(0);
    expect($session->status)->toBe(PlaybackSessionStatus::Active);

    // Next play_media command should be for video 2
    $command = $this->onesiBox->commands()
        ->where('type', CommandType::PlayMedia)
        ->latest('id')
        ->first();
    expect($command->payload['url'])->toBe('https://www.jw.org/video/2.mp4');
});

test('stop session cancels pending commands', function (): void {
    $playlist = $this->createPlaylistAction->execute($this->onesiBox, [
        ['url' => 'https://www.jw.org/video/1.mp4'],
        ['url' => 'https://www.jw.org/video/2.mp4'],
    ]);

    $session = $this->startAction->execute($this->onesiBox, $playlist, 60);

    // Verify a pending play_media command exists
    $pendingCommands = $this->onesiBox->commands()
        ->where('type', CommandType::PlayMedia)
        ->where('status', CommandStatus::Pending)
        ->count();
    expect($pendingCommands)->toBeGreaterThanOrEqual(1);

    // Stop the session
    $stopAction = resolve(StopPlaybackSessionAction::class);
    $stopAction->execute($session);

    $session->refresh();
    expect($session->status)->toBe(PlaybackSessionStatus::Stopped);
    expect($session->ended_at)->not->toBeNull();

    // Verify pending play_media commands with session_id were cancelled
    $remainingPending = $this->onesiBox->commands()
        ->where('type', CommandType::PlayMedia)
        ->where('status', CommandStatus::Pending)
        ->whereJsonContains('payload->session_id', $session->uuid)
        ->count();
    expect($remainingPending)->toBe(0);
});

test('session expires after time limit during playback', function (): void {
    $playlist = $this->createPlaylistAction->execute($this->onesiBox, [
        ['url' => 'https://www.jw.org/video/1.mp4'],
        ['url' => 'https://www.jw.org/video/2.mp4'],
        ['url' => 'https://www.jw.org/video/3.mp4'],
    ]);

    // Start session with 1 minute duration, but started 2 minutes ago (already expired)
    $session = $this->startAction->execute($this->onesiBox, $playlist, 1);
    $session->update(['started_at' => now()->subMinutes(2)]);

    // Simulate completed event — session should detect expiry and end
    $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'completed',
            'media_url' => 'https://www.jw.org/video/1.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$this->token->plainTextToken}"]
    )->assertOk();

    $session->refresh();
    expect($session->status)->toBe(PlaybackSessionStatus::Completed);
    expect($session->ended_at)->not->toBeNull();
});

test('non-session playback events still work correctly', function (): void {
    // No active session — regular playback events should work as before
    $response = $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'started',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
            'duration' => 3600,
        ],
        ['Authorization' => "Bearer {$this->token->plainTextToken}"]
    );

    $response->assertOk()->assertJsonPath('data.logged', true);

    // Completed event without session should not create new commands
    $commandCountBefore = $this->onesiBox->commands()->count();

    $this->postJson(
        route('api.v1.appliances.playback'),
        [
            'event' => 'completed',
            'media_url' => 'https://www.jw.org/media/video/example.mp4',
            'media_type' => 'video',
        ],
        ['Authorization' => "Bearer {$this->token->plainTextToken}"]
    )->assertOk();

    expect($this->onesiBox->commands()->count())->toBe($commandCountBefore);
});
