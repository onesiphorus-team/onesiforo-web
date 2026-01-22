<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\OnesiBoxCommandSent;
use App\Exceptions\OnesiBoxOfflineException;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\OnesiBox;
use App\Models\User;

class OnesiBoxCommandService implements OnesiBoxCommandServiceInterface
{
    public function sendAudioCommand(OnesiBox $onesiBox, string $audioUrl): void
    {
        $this->ensureOnline($onesiBox);

        dispatch(new SendOnesiBoxCommand($onesiBox, 'audio', ['url' => $audioUrl]));

        $this->dispatchCommandSentEvent($onesiBox, 'audio', ['url' => $audioUrl]);
    }

    public function sendVideoCommand(OnesiBox $onesiBox, string $videoUrl): void
    {
        $this->ensureOnline($onesiBox);

        dispatch(new SendOnesiBoxCommand($onesiBox, 'video', ['url' => $videoUrl]));

        $this->dispatchCommandSentEvent($onesiBox, 'video', ['url' => $videoUrl]);
    }

    public function sendZoomCommand(OnesiBox $onesiBox, string $meetingId, ?string $password = null): void
    {
        $this->ensureOnline($onesiBox);

        dispatch(new SendOnesiBoxCommand($onesiBox, 'zoom', [
            'meeting_id' => $meetingId,
            'password' => $password,
        ]));

        $this->dispatchCommandSentEvent($onesiBox, 'zoom', [
            'meeting_id' => $meetingId,
        ]);
    }

    public function sendStopCommand(OnesiBox $onesiBox): void
    {
        $this->ensureOnline($onesiBox);

        dispatch(new SendOnesiBoxCommand($onesiBox, 'stop', []));

        $this->dispatchCommandSentEvent($onesiBox, 'stop', []);
    }

    private function ensureOnline(OnesiBox $onesiBox): void
    {
        if (! $onesiBox->isOnline()) {
            throw new OnesiBoxOfflineException("OnesiBox {$onesiBox->name} is offline");
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchCommandSentEvent(OnesiBox $onesiBox, string $commandType, array $payload): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user !== null) {
            event(new OnesiBoxCommandSent($onesiBox, $user, $commandType, $payload));
        }
    }
}
