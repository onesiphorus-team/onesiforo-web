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
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, CommandType::PlayMedia, [
            'url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);

        dispatch(new SendOnesiBoxCommand($command));

        $this->dispatchCommandSentEvent($onesiBox, $command);
    }

    public function sendZoomCommand(OnesiBox $onesiBox, string $meetingId, ?string $password = null): void
    {
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, CommandType::JoinZoom, [
            'meeting_id' => $meetingId,
            'password' => $password,
        ]);

        dispatch(new SendOnesiBoxCommand($command));

        $this->dispatchCommandSentEvent($onesiBox, $command);
    }

    public function sendStopCommand(OnesiBox $onesiBox): void
    {
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, CommandType::StopMedia, []);

        dispatch(new SendOnesiBoxCommand($command));

        $this->dispatchCommandSentEvent($onesiBox, $command);
    }

    public function sendRebootCommand(OnesiBox $onesiBox): void
    {
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, CommandType::Reboot, []);

        dispatch(new SendOnesiBoxCommand($command));

        $this->dispatchCommandSentEvent($onesiBox, $command);
    }

    public function sendLeaveZoomCommand(OnesiBox $onesiBox): void
    {
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, CommandType::LeaveZoom, []);

        dispatch(new SendOnesiBoxCommand($command));

        $this->dispatchCommandSentEvent($onesiBox, $command);
    }

    public function sendZoomUrlCommand(OnesiBox $onesiBox, string $zoomUrl, string $participantName = 'Rosa Iannascoli'): void
    {
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, CommandType::JoinZoom, [
            'meeting_url' => $zoomUrl,
            'participant_name' => $participantName,
        ]);

        dispatch(new SendOnesiBoxCommand($command));

        $this->dispatchCommandSentEvent($onesiBox, $command);
    }

    public function sendRestartServiceCommand(OnesiBox $onesiBox): void
    {
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, CommandType::RestartService, []);

        dispatch(new SendOnesiBoxCommand($command));

        $this->dispatchCommandSentEvent($onesiBox, $command);
    }

    /**
     * Create a command in the database.
     *
     * @param  array<string, mixed>  $payload
     */
    private function createCommand(OnesiBox $onesiBox, CommandType $type, array $payload): Command
    {
        return Command::query()->create([
            'onesi_box_id' => $onesiBox->id,
            'type' => $type,
            'payload' => $payload,
            'priority' => self::DEFAULT_PRIORITY,
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
        /** @var User|null $user */
        $user = auth()->user();

        if ($user !== null) {
            event(new OnesiBoxCommandSent($onesiBox, $user, $command));
        }
    }
}
