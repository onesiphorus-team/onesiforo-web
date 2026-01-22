<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OnesiBox;
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

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public OnesiBox $onesiBox,
        public string $commandType,
        public array $payload
    ) {}

    public function handle(): void
    {
        // TODO: Implementare comunicazione con appliance
        // Possibili approcci:
        // 1. HTTP API verso appliance
        // 2. MQTT publish
        // 3. WebSocket push via Reverb

        logger()->info('OnesiBox command sent', [
            'onesibox_id' => $this->onesiBox->id,
            'command' => $this->commandType,
            'payload' => $this->payload,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('OnesiBox command failed', [
            'onesibox_id' => $this->onesiBox->id,
            'command' => $this->commandType,
            'error' => $exception->getMessage(),
        ]);
    }
}
