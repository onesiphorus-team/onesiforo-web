<?php

namespace App\Notifications;

use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class MeetingUpcomingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MeetingInstance $meetingInstance,
        public OnesiBox $onesiBox,
    ) {}

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($notifiable->telegram_chat_id) {
            $channels[] = 'telegram';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $type = $this->meetingInstance->type->getLabel();

        return [
            'meeting_instance_id' => $this->meetingInstance->id,
            'onesi_box_id' => $this->onesiBox->id,
            'message' => "Adunanza {$type} tra 30 minuti — {$this->onesiBox->name}",
            'type' => 'meeting_upcoming',
        ];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $type = $this->meetingInstance->type->getLabel();

        return TelegramMessage::create()
            ->to($notifiable->telegram_chat_id)
            ->content("📅 Adunanza {$type} tra 30 minuti\n🖥️ {$this->onesiBox->name}\n\nAccedi alla dashboard per confermare o saltare.");
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
