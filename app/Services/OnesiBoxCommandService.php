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
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, CommandType::PlayMedia, [
            'url' => $audioUrl,
            'media_type' => 'audio',
        ]);

        dispatch(new SendOnesiBoxCommand($command));

        $this->dispatchCommandSentEvent($onesiBox, $command);
    }

    public function sendVideoCommand(OnesiBox $onesiBox, string $videoUrl): void
    {
        $this->ensureOnline($onesiBox);

        $command = $this->createCommand($onesiBox, CommandType::PlayMedia, [
            'url' => $videoUrl,
            'media_type' => 'video',
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

    /**
     * Create a command in the database.
     *
     * @param  array<string, mixed>  $payload
     */
    private function createCommand(OnesiBox $onesiBox, CommandType $type, array $payload): Command
    {
        return Command::create([
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
