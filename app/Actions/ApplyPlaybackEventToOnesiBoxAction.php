<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OnesiBoxStatus;
use App\Enums\PlaybackEventType;
use App\Events\OnesiBoxStatusUpdated;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;

/**
 * Reflects a playback event on the OnesiBox "now playing" state right away.
 *
 * Heartbeats report the full state only every 30 seconds: without this, a caregiver who
 * starts a video would see the box as idle (or still on the previous item) for up to half
 * a minute. The next heartbeat stays the source of truth and overwrites these values.
 */
class ApplyPlaybackEventToOnesiBoxAction
{
    public function execute(OnesiBox $onesiBox, PlaybackEvent $playbackEvent): void
    {
        $isSameMedia = $onesiBox->current_media_url === $playbackEvent->media_url;

        match ($playbackEvent->event) {
            PlaybackEventType::Started, PlaybackEventType::Resumed => $this->showAsPlaying($onesiBox, $playbackEvent, $isSameMedia),
            PlaybackEventType::Paused => $this->updatePosition($onesiBox, $playbackEvent, $isSameMedia),
            PlaybackEventType::Stopped, PlaybackEventType::Completed, PlaybackEventType::Error => $this->clearIfCurrent($onesiBox, $isSameMedia),
        };

        $onesiBox->recordHeartbeat();

        event(new OnesiBoxStatusUpdated($onesiBox));
    }

    private function showAsPlaying(OnesiBox $onesiBox, PlaybackEvent $playbackEvent, bool $isSameMedia): void
    {
        if ($onesiBox->status !== OnesiBoxStatus::Calling) {
            $onesiBox->status = OnesiBoxStatus::Playing;
        }

        if (! $isSameMedia) {
            $onesiBox->current_media_title = null;
            $onesiBox->current_media_duration = null;
        }

        $onesiBox->current_media_url = $playbackEvent->media_url;
        $onesiBox->current_media_type = $playbackEvent->media_type;
        $onesiBox->current_media_position = $playbackEvent->position
            ?? ($playbackEvent->event === PlaybackEventType::Started ? 0 : $onesiBox->current_media_position);
        $onesiBox->current_media_duration = $playbackEvent->duration ?? $onesiBox->current_media_duration;
    }

    private function updatePosition(OnesiBox $onesiBox, PlaybackEvent $playbackEvent, bool $isSameMedia): void
    {
        if ($isSameMedia && $playbackEvent->position !== null) {
            $onesiBox->current_media_position = $playbackEvent->position;
        }
    }

    private function clearIfCurrent(OnesiBox $onesiBox, bool $isSameMedia): void
    {
        if (! $isSameMedia) {
            return;
        }

        if ($onesiBox->status === OnesiBoxStatus::Playing) {
            $onesiBox->status = OnesiBoxStatus::Idle;
        }

        $onesiBox->current_media_url = null;
        $onesiBox->current_media_type = null;
        $onesiBox->current_media_title = null;
        $onesiBox->current_media_position = null;
        $onesiBox->current_media_duration = null;
    }
}
