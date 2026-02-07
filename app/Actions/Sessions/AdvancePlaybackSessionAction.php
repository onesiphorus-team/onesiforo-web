<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Enums\PlaybackEventType;
use App\Enums\PlaybackSessionStatus;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\PlaylistItem;
use App\Services\OnesiBoxCommandServiceInterface;

/**
 * Advances a playback session to the next video after a completed/error event.
 *
 * This action is invoked by PlaybackController when OnesiBox reports
 * a 'completed' or 'error' playback event.
 */
class AdvancePlaybackSessionAction
{
    public function __construct(
        private readonly OnesiBoxCommandServiceInterface $commandService,
    ) {}

    /**
     * Execute the action to advance the session.
     */
    public function execute(OnesiBox $onesiBox, PlaybackEventType $eventType): void
    {
        $session = $onesiBox->activeSession();

        if (! $session instanceof PlaybackSession) {
            return;
        }

        $this->updateCounters($session, $eventType);

        $session->increment('current_position');
        $session->refresh();

        if ($session->isExpired()) {
            $this->endSession($session);

            return;
        }

        $nextItem = $session->currentItem();

        if (! $nextItem instanceof PlaylistItem) {
            $this->endSession($session);

            return;
        }

        try {
            $this->commandService->sendSessionMediaCommand(
                $onesiBox,
                $nextItem->media_url,
                'video',
                $session->uuid,
            );
        } catch (OnesiBoxOfflineException) {
            $session->update([
                'status' => PlaybackSessionStatus::Error,
                'ended_at' => now(),
            ]);
        }
    }

    private function updateCounters(PlaybackSession $session, PlaybackEventType $eventType): void
    {
        if ($eventType === PlaybackEventType::Completed) {
            $session->increment('items_played');
        } elseif ($eventType === PlaybackEventType::Error) {
            $session->increment('items_skipped');
        }
    }

    private function endSession(PlaybackSession $session): void
    {
        $session->update([
            'status' => PlaybackSessionStatus::Completed,
            'ended_at' => now(),
        ]);
    }
}
