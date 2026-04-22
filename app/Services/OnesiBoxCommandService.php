<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Events\OnesiBoxCommandSent;
use App\Exceptions\OnesiBoxOfflineException;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;

class OnesiBoxCommandService implements OnesiBoxCommandServiceInterface
{
    /**
     * Default priority for commands (1=highest, 5=lowest).
     */
    private const int DEFAULT_PRIORITY = 3;

    public function sendAudioCommand(OnesiBox $onesiBox, string $audioUrl): void
    {
        $this->sendMediaCommand($onesiBox, $audioUrl, 'audio');
    }

    public function sendVideoCommand(OnesiBox $onesiBox, string $videoUrl): void
    {
        $this->sendMediaCommand($onesiBox, $videoUrl, 'video');
    }

    public function sendMediaCommand(OnesiBox $onesiBox, string $mediaUrl, string $mediaType): void
    {
        $this->sendCommand($onesiBox, CommandType::PlayMedia, [
            'url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);
    }

    public function sendZoomCommand(OnesiBox $onesiBox, string $meetingId, ?string $password = null): void
    {
        $this->sendCommand($onesiBox, CommandType::JoinZoom, [
            'meeting_id' => $meetingId,
            'password' => $password,
        ]);
    }

    public function sendStopCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::StopMedia);
    }

    public function sendRebootCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::Reboot);
    }

    public function sendLeaveZoomCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::LeaveZoom);
    }

    public function sendZoomUrlCommand(OnesiBox $onesiBox, string $zoomUrl, string $participantName): void
    {
        $this->sendCommand($onesiBox, CommandType::JoinZoom, [
            'meeting_url' => $zoomUrl,
            'participant_name' => $participantName,
        ]);
    }

    public function sendRestartServiceCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::RestartService);
    }

    public function sendSessionMediaCommand(OnesiBox $onesiBox, string $mediaUrl, string $mediaType, string $sessionId): void
    {
        $this->sendCommand($onesiBox, CommandType::PlayMedia, [
            'url' => $mediaUrl,
            'media_type' => $mediaType,
            'session_id' => $sessionId,
        ], priority: 2);
    }

    public function sendVolumeCommand(OnesiBox $onesiBox, int $level): void
    {
        $this->sendCommand($onesiBox, CommandType::SetVolume, [
            'level' => $level,
        ]);
    }

    public function sendStreamItemCommand(OnesiBox $onesiBox, string $url, int $ordinal): void
    {
        $this->sendCommand($onesiBox, CommandType::PlayStreamItem, [
            'url' => $url,
            'ordinal' => $ordinal,
        ], priority: 2);
    }

    public function sendPauseCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::PauseMedia);
    }

    public function sendResumeCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::ResumeMedia);
    }

    /**
     * Send a command to an OnesiBox appliance.
     *
     * This is the central method that handles:
     * - Online status verification
     * - Command creation in database
     * - Job dispatching for WebSocket notification
     * - Event dispatching for audit logging
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws OnesiBoxOfflineException
     */
    private function sendCommand(OnesiBox $onesiBox, CommandType $type, array $payload = [], int $priority = self::DEFAULT_PRIORITY): void
    {
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, $type, $payload, $priority);

        dispatch(new SendOnesiBoxCommand($command));

        $this->dispatchCommandSentEvent($onesiBox, $command);
    }

    /**
     * Create a command in the database.
     *
     * @param  array<string, mixed>  $payload
     */
    private function createCommand(OnesiBox $onesiBox, CommandType $type, array $payload, int $priority = self::DEFAULT_PRIORITY): Command
    {
        return Command::query()->create([
            'onesi_box_id' => $onesiBox->id,
            'type' => $type,
            'payload' => $payload,
            'priority' => $priority,
            'status' => CommandStatus::Pending,
        ]);
    }

    private function ensureOnline(OnesiBox $onesiBox): void
    {
        if (! $onesiBox->isOnline()) {
            throw new OnesiBoxOfflineException("OnesiBox {$onesiBox->name} is offline");
        }
    }

    private function dispatchCommandSentEvent(OnesiBox $onesiBox, Command $command): void
    {
        $user = auth()->user();

        if ($user instanceof User) {
            event(new OnesiBoxCommandSent($onesiBox, $user, $command));
        }
    }
}
