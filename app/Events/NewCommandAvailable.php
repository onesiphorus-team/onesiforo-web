<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Command;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewCommandAvailable implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Command $command
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("appliance.{$this->command->onesi_box_id}"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->command->uuid,
            'type' => $this->command->type->value,
            'priority' => $this->command->priority,
            'expires_at' => $this->command->expires_at->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NewCommand';
    }
}
