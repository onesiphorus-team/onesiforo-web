<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PlaybackEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast quando un OnesiBox riporta un evento di playback.
 *
 * Permette a Livewire components (es. StreamPlayer) di reagire in tempo reale
 * a eventi started/paused/stopped/completed/error senza polling.
 */
class PlaybackEventReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public PlaybackEvent $playbackEvent) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("appliance.{$this->playbackEvent->onesiBox->serial_number}");
    }

    public function broadcastAs(): string
    {
        return 'playback.event-received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->playbackEvent->event->value,
            'media_url' => $this->playbackEvent->media_url,
            'media_type' => $this->playbackEvent->media_type,
            'error_code' => $this->playbackEvent->error_code,
            'error_message' => $this->playbackEvent->error_message,
            'occurred_at' => $this->playbackEvent->created_at->toISOString(),
        ];
    }
}
