<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Events\OnesiBoxCommandSent;
use App\Exceptions\OnesiBoxOfflineException;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\Command;
use App\Models\CustomCommand;
use App\Models\OnesiBox;
use App\Models\User;

class OnesiBoxCommandService implements OnesiBoxCommandServiceInterface
{
    /**
     * Default priority for commands (1=highest, 5=lowest).
     */
    private const int DEFAULT_PRIORITY = 3;

    /**
     * Commands that prepare the box for content (e.g. switching the TV input) go out first.
     */
    private const int BEFORE_CONTENT_PRIORITY = 2;

    /**
     * An automatic command already sent this recently is not sent again, e.g. when the
     * caregiver taps several videos in a row.
     */
    private const int BEFORE_CONTENT_COOLDOWN_SECONDS = 60;

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
        $this->sendContentCommand($onesiBox, CommandType::PlayMedia, [
            'url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);
    }

    public function sendZoomCommand(OnesiBox $onesiBox, string $meetingId, ?string $password = null): void
    {
        $this->sendContentCommand($onesiBox, CommandType::JoinZoom, [
            'meeting_id' => $meetingId,
            'password' => $password,
        ]);
    }

    public function sendStopCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::StopMedia);
    }

    public function sendSessionStopCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::StopMedia, priority: 1);
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
        $this->sendContentCommand($onesiBox, CommandType::JoinZoom, [
            'meeting_url' => $zoomUrl,
            'participant_name' => $participantName,
        ]);
    }

    public function sendRestartServiceCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::RestartService);
    }

    public function sendSessionMediaCommand(OnesiBox $onesiBox, string $mediaUrl, string $mediaType, string $sessionId, bool $startsSession = false): void
    {
        // Only the first item of a session prepares the box: moving on to the next item must not,
        // e.g., take the TV back to the box after someone switched it to normal TV on purpose.
        $send = $startsSession ? $this->sendContentCommand(...) : $this->sendCommand(...);

        $send($onesiBox, CommandType::PlayMedia, [
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
        $this->sendContentCommand($onesiBox, CommandType::PlayStreamItem, [
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

    public function sendCustomScriptCommand(OnesiBox $onesiBox, CustomCommand $customCommand): void
    {
        $this->sendCommand($onesiBox, CommandType::CustomScript, [
            'custom_command_id' => $customCommand->id,
            'script_name' => $customCommand->script_name,
            'static_args' => $customCommand->static_args ?? [],
        ]);
    }

    /**
     * Send a command that makes the box show something (media or a meeting), preceded by the
     * custom commands marked to run before content, e.g. switching the TV to the box input.
     *
     * Those commands are flagged to run in the background, so the box does not wait for them
     * before starting the content. A box without such commands gets only the content command.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws OnesiBoxOfflineException
     */
    private function sendContentCommand(OnesiBox $onesiBox, CommandType $type, array $payload = [], int $priority = self::DEFAULT_PRIORITY): void
    {
        $this->ensureOnline($onesiBox);

        $this->sendCommandsBeforeContent($onesiBox);

        $this->sendCommand($onesiBox, $type, $payload, $priority);
    }

    private function sendCommandsBeforeContent(OnesiBox $onesiBox): void
    {
        $customCommands = $onesiBox->customCommands()->runningBeforeContent()->ordered()->get();

        foreach ($customCommands as $customCommand) {
            if ($this->wasSentRecently($onesiBox, $customCommand)) {
                continue;
            }

            $command = $this->createCommand($onesiBox, CommandType::CustomScript, [
                'custom_command_id' => $customCommand->id,
                'script_name' => $customCommand->script_name,
                'static_args' => $customCommand->static_args ?? [],
                'run_in_background' => true,
                'automatic' => true,
            ], self::BEFORE_CONTENT_PRIORITY);

            dispatch(new SendOnesiBoxCommand($command))->afterCommit();
        }
    }

    private function wasSentRecently(OnesiBox $onesiBox, CustomCommand $customCommand): bool
    {
        return $onesiBox->commands()
            ->where('type', CommandType::CustomScript)
            ->where('payload->custom_command_id', $customCommand->id)
            ->where('created_at', '>=', now()->subSeconds(self::BEFORE_CONTENT_COOLDOWN_SECONDS))
            ->exists();
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

        dispatch(new SendOnesiBoxCommand($command))->afterCommit();

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
