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
        $this->command->loadMissing('onesiBox');

        // Broadcast to appliance via WebSocket (Reverb).
        // Uses ShouldBroadcastNow to avoid double-queuing (we're already in a job).
        // Wrapped in try/catch so Reverb failures don't mark the command as failed —
        // the appliance will pick it up via polling fallback.
        try {
            broadcast(new NewCommandAvailable($this->command));

            logger()->info('OnesiBox command broadcasted via WebSocket', [
                'command_uuid' => $this->command->uuid,
                'onesibox_id' => $this->command->onesi_box_id,
                'type' => $this->command->type->value,
                'payload' => $this->command->payload,
            ]);
        } catch (Throwable $e) {
            logger()->warning('WebSocket broadcast failed, appliance will use polling fallback', [
                'command_uuid' => $this->command->uuid,
                'error' => $e->getMessage(),
            ]);
        }
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
