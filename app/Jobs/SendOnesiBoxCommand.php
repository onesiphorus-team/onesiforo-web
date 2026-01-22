<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\NewCommandAvailable;
use App\Models\Command;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendOnesiBoxCommand implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public Command $command
    ) {}

    public function handle(): void
    {
        // Broadcast to appliance via WebSocket (Reverb)
        // The appliance can listen on this channel for real-time notifications
        // and/or poll the API endpoint for pending commands
        broadcast(new NewCommandAvailable($this->command))->toOthers();

        logger()->info('OnesiBox command queued', [
            'command_uuid' => $this->command->uuid,
            'onesibox_id' => $this->command->onesi_box_id,
            'type' => $this->command->type->value,
            'payload' => $this->command->payload,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        // Mark command as failed if job fails after all retries
        $this->command->markAsFailed(
            errorCode: 'JOB_FAILED',
            errorMessage: $exception->getMessage()
        );

        logger()->error('OnesiBox command job failed', [
            'command_uuid' => $this->command->uuid,
            'onesibox_id' => $this->command->onesi_box_id,
            'type' => $this->command->type->value,
            'error' => $exception->getMessage(),
        ]);
    }
}
