<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\PlaybackSessionStatus;
use App\Models\PlaybackSession;
use App\Services\OnesiBoxCommandServiceInterface;

/**
 * Stops an active playback session and sends stop command to OnesiBox.
 */
class StopPlaybackSessionAction
{
    public function __construct(
        private readonly OnesiBoxCommandServiceInterface $commandService,
    ) {}

    /**
     * Execute the action to stop a session.
     */
    public function execute(PlaybackSession $session): PlaybackSession
    {
        if ($session->status->isEnded()) {
            return $session;
        }

        $session->update([
            'status' => PlaybackSessionStatus::Stopped,
            'ended_at' => now(),
        ]);

        // Cancel pending play_media commands for this session
        $session->onesiBox->commands()
            ->where('type', CommandType::PlayMedia)
            ->where('status', CommandStatus::Pending)
            ->whereJsonContains('payload->session_id', $session->uuid)
            ->each(function ($command): void {
                $command->update([
                    'status' => CommandStatus::Cancelled,
                    'executed_at' => now(),
                ]);
            });

        // Send stop_media command
        try {
            $this->commandService->sendStopCommand($session->onesiBox);
        } catch (\App\Exceptions\OnesiBoxOfflineException) {
            // OnesiBox is offline — session is still marked as stopped
        }

        return $session->refresh();
    }
}
