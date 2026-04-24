<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ApplianceScreenshot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplianceScreenshotReceived implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly ApplianceScreenshot $screenshot)
    {
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("appliance.{$this->screenshot->onesi_box_id}");
    }

    public function broadcastAs(): string
    {
        return 'ApplianceScreenshotReceived';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->screenshot->id,
            'captured_at' => $this->screenshot->captured_at->toIso8601String(),
        ];
    }
}
