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
            'current_media' => $this->onesiBox->current_media_url !== null ? [
                'url' => $this->onesiBox->current_media_url,
                'type' => $this->onesiBox->current_media_type,
                'title' => $this->onesiBox->current_media_title,
                'position' => $this->onesiBox->current_media_position,
                'duration' => $this->onesiBox->current_media_duration,
            ] : null,
            'current_meeting' => $this->onesiBox->current_meeting_id !== null ? [
                'meeting_id' => $this->onesiBox->current_meeting_id,
                'meeting_url' => $this->onesiBox->current_meeting_url,
                'joined_at' => $this->onesiBox->current_meeting_joined_at?->toISOString(),
            ] : null,
            'volume' => $this->onesiBox->volume,
        ];
    }

    public function broadcastAs(): string
    {
        return 'StatusUpdated';
    }
}
