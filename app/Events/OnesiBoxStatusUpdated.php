<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OnesiBox;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OnesiBoxStatusUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public OnesiBox $onesiBox
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("onesibox.{$this->onesiBox->id}"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->onesiBox->id,
            'status' => $this->onesiBox->status->value ?? null,
            'is_online' => $this->onesiBox->isOnline(),
            'last_seen_at' => $this->onesiBox->last_seen_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StatusUpdated';
    }
}
