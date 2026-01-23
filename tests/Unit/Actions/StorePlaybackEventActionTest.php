<?php

declare(strict_types=1);

use App\Actions\StorePlaybackEventAction;
use App\Enums\PlaybackEventType;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new StorePlaybackEventAction;
    $this->onesiBox = OnesiBox::factory()->create();
});

it('stores a basic playback event', function (): void {
    $event = ($this->action)(
        onesiBox: $this->onesiBox,
        event: PlaybackEventType::Started,
        mediaUrl: 'https://www.jw.org/media/video/test.mp4',
        mediaType: 'video'
    );

    expect($event)->toBeInstanceOf(PlaybackEvent::class)
        ->and($event->onesi_box_id)->toBe($this->onesiBox->id)
        ->and($event->event)->toBe(PlaybackEventType::Started)
        ->and($event->media_url)->toBe('https://www.jw.org/media/video/test.mp4')
        ->and($event->media_type)->toBe('video');

    $this->assertDatabaseHas('playback_events', [
        'onesi_box_id' => $this->onesiBox->id,
        'media_url' => 'https://www.jw.org/media/video/test.mp4',
    ]);
});

it('stores playback event with position and duration', function (): void {
    $event = ($this->action)(
        onesiBox: $this->onesiBox,
        event: PlaybackEventType::Paused,
        mediaUrl: 'https://www.jw.org/media/audio/test.mp3',
        mediaType: 'audio',
        position: 120,
        duration: 3600
    );

    expect($event->position)->toBe(120)
        ->and($event->duration)->toBe(3600);
});

it('stores playback event with error message', function (): void {
    $event = ($this->action)(
        onesiBox: $this->onesiBox,
        event: PlaybackEventType::Error,
        mediaUrl: 'https://www.jw.org/media/video/broken.mp4',
        mediaType: 'video',
        errorMessage: 'Network timeout during playback'
    );

    expect($event->event)->toBe(PlaybackEventType::Error)
        ->and($event->error_message)->toBe('Network timeout during playback');
});

it('accepts string event type', function (): void {
    $event = ($this->action)(
        onesiBox: $this->onesiBox,
        event: 'completed',
        mediaUrl: 'https://www.jw.org/media/video/test.mp4',
        mediaType: 'video'
    );

    expect($event->event)->toBe(PlaybackEventType::Completed);
});

it('accepts enum event type', function (): void {
    $event = ($this->action)(
        onesiBox: $this->onesiBox,
        event: PlaybackEventType::Resumed,
        mediaUrl: 'https://www.jw.org/media/audio/test.mp3',
        mediaType: 'audio'
    );

    expect($event->event)->toBe(PlaybackEventType::Resumed);
});

it('stores event from array using fromArray method', function (): void {
    $event = $this->action->fromArray($this->onesiBox, [
        'event' => 'started',
        'media_url' => 'https://www.jw.org/media/video/test.mp4',
        'media_type' => 'video',
        'position' => 0,
        'duration' => 1800,
    ]);

    expect($event)->toBeInstanceOf(PlaybackEvent::class)
        ->and($event->event)->toBe(PlaybackEventType::Started)
        ->and($event->position)->toBe(0)
        ->and($event->duration)->toBe(1800);
});

it('stores event from array with optional fields', function (): void {
    $event = $this->action->fromArray($this->onesiBox, [
        'event' => 'stopped',
        'media_url' => 'https://www.jw.org/media/audio/test.mp3',
        'media_type' => 'audio',
    ]);

    expect($event->position)->toBeNull()
        ->and($event->duration)->toBeNull()
        ->and($event->error_message)->toBeNull();
});

it('handles all event types', function (PlaybackEventType $eventType): void {
    $event = ($this->action)(
        onesiBox: $this->onesiBox,
        event: $eventType,
        mediaUrl: 'https://www.jw.org/media/video/test.mp4',
        mediaType: 'video'
    );

    expect($event->event)->toBe($eventType);
})->with([
    'started' => PlaybackEventType::Started,
    'paused' => PlaybackEventType::Paused,
    'resumed' => PlaybackEventType::Resumed,
    'stopped' => PlaybackEventType::Stopped,
    'completed' => PlaybackEventType::Completed,
    'error' => PlaybackEventType::Error,
]);

it('associates event with correct OnesiBox', function (): void {
    $anotherOnesiBox = OnesiBox::factory()->create();

    $event = ($this->action)(
        onesiBox: $anotherOnesiBox,
        event: PlaybackEventType::Started,
        mediaUrl: 'https://www.jw.org/media/video/test.mp4',
        mediaType: 'video'
    );

    expect($event->onesiBox->id)->toBe($anotherOnesiBox->id)
        ->and($event->onesiBox->id)->not->toBe($this->onesiBox->id);
});
