<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Enums\PlaybackSessionStatus;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Services\OnesiBoxCommandServiceInterface;

/**
 * Starts a timed playback session for a playlist on an OnesiBox.
 */
class StartPlaybackSessionAction
{
    public function __construct(
        private readonly OnesiBoxCommandServiceInterface $commandService,
        private readonly StopPlaybackSessionAction $stopAction,
    ) {}

    /**
     * Execute the action to start a playback session.
     *
     * @param  int  $durationMinutes  Allowed values: 30, 60, 120, 180
     */
    public function execute(OnesiBox $onesiBox, Playlist $playlist, int $durationMinutes): PlaybackSession
    {
        $existingSession = $onesiBox->activeSession();
        if ($existingSession instanceof PlaybackSession) {
            $this->stopAction->execute($existingSession);
        }

        $session = PlaybackSession::query()->create([
            'onesi_box_id' => $onesiBox->id,
            'playlist_id' => $playlist->id,
            'status' => PlaybackSessionStatus::Active,
            'duration_minutes' => $durationMinutes,
            'started_at' => now(),
            'current_position' => 0,
            'items_played' => 0,
            'items_skipped' => 0,
        ]);

        $firstItem = $session->currentItem();

        if ($firstItem !== null) {
            $this->commandService->sendSessionMediaCommand(
                $onesiBox,
                $firstItem->media_url,
                'video',
                $session->uuid,
            );
        }

        return $session;
    }
}
