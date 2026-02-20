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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     *
     * @param  string|null  $mediaUrl  The media_url from the event, used to validate against the current item
     */
    public function execute(OnesiBox $onesiBox, PlaybackEventType $eventType, ?string $mediaUrl = null): void
    {
        /**
         * @var array{nextItem: PlaylistItem, sessionUuid: string}|null $result
         */
        $result = DB::transaction(function () use ($onesiBox, $eventType, $mediaUrl): ?array {
            $session = $onesiBox->playbackSessions()->active()->lockForUpdate()->first();

            if (! $session instanceof PlaybackSession) {
                return null;
            }

            if ($mediaUrl !== null) {
                $currentItem = $session->currentItem();

                if ($currentItem instanceof PlaylistItem && $currentItem->media_url !== $mediaUrl) {
                    Log::warning('Duplicate/stale playback event ignored: media_url mismatch', [
                        'session_id' => $session->uuid,
                        'expected_media_url' => $currentItem->media_url,
                        'received_media_url' => $mediaUrl,
                        'current_position' => $session->current_position,
                    ]);

                    return null;
                }
            }

            $this->updateCounters($session, $eventType);

            $session->increment('current_position');
            $session->refresh();

            if ($session->isExpired()) {
                $this->endSession($session);

                return null;
            }

            $nextItem = $session->currentItem();

            if (! $nextItem instanceof PlaylistItem) {
                $this->endSession($session);

                return null;
            }

            return [
                'nextItem' => $nextItem,
                'sessionUuid' => $session->uuid,
            ];
        });

        if ($result !== null) {
            try {
                $this->commandService->sendSessionMediaCommand(
                    $onesiBox,
                    $result['nextItem']->media_url,
                    'video',
                    $result['sessionUuid'],
                );
            } catch (OnesiBoxOfflineException) {
                $session = $onesiBox->playbackSessions()->active()->first();

                if ($session instanceof PlaybackSession) {
                    $session->update([
                        'status' => PlaybackSessionStatus::Error,
                        'ended_at' => now(),
                    ]);
                }
            }
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
