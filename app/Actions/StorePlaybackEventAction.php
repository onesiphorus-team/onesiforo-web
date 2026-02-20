<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PlaybackEventType;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;

/**
 * Stores a playback event from an OnesiBox appliance.
 *
 * This action encapsulates the logic for creating and persisting playback events
 * reported by OnesiBox devices during media playback.
 */
class StorePlaybackEventAction
{
    /**
     * Store a playback event for the given OnesiBox.
     *
     * @param  OnesiBox  $onesiBox  The OnesiBox reporting the event
     * @param  PlaybackEventType|string  $event  The type of playback event
     * @param  string  $mediaUrl  The URL of the media being played
     * @param  string  $mediaType  The type of media ('audio' or 'video')
     * @param  int|null  $position  Current playback position in seconds
     * @param  int|null  $duration  Total media duration in seconds
     * @param  string|null  $errorMessage  Error message for error events
     * @param  string|null  $sessionId  The playback session UUID for analytics tracking
     * @return PlaybackEvent The created playback event
     */
    public function __invoke(
        OnesiBox $onesiBox,
        PlaybackEventType|string $event,
        string $mediaUrl,
        string $mediaType,
        ?int $position = null,
        ?int $duration = null,
        ?string $errorMessage = null,
        ?string $sessionId = null,
    ): PlaybackEvent {
        $eventType = $event instanceof PlaybackEventType
            ? $event
            : PlaybackEventType::from($event);

        return PlaybackEvent::query()->create([
            'onesi_box_id' => $onesiBox->id,
            'event' => $eventType,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'position' => $position,
            'duration' => $duration,
            'error_message' => $errorMessage,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Store a playback event from an array of data.
     *
     * @param  OnesiBox  $onesiBox  The OnesiBox reporting the event
     * @param  array{event: string, media_url: string, media_type: string, position?: int|null, duration?: int|null, error_message?: string|null, session_id?: string|null}  $data
     * @return PlaybackEvent The created playback event
     */
    public function fromArray(OnesiBox $onesiBox, array $data): PlaybackEvent
    {
        return ($this)(
            onesiBox: $onesiBox,
            event: $data['event'],
            mediaUrl: $data['media_url'],
            mediaType: $data['media_type'],
            position: $data['position'] ?? null,
            duration: $data['duration'] ?? null,
            errorMessage: $data['error_message'] ?? null,
            sessionId: $data['session_id'] ?? null,
        );
    }
}
