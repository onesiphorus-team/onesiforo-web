<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MeetingType;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\User;
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
     * @param  User  $notifiable
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
     * @param  User  $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var MeetingType $type */
        $type = $this->meetingInstance->type;

        return [
            'meeting_instance_id' => $this->meetingInstance->id,
            'onesi_box_id' => $this->onesiBox->id,
            'message' => "Adunanza {$type->getLabel()} tra 30 minuti — {$this->onesiBox->name}",
            'type' => 'meeting_upcoming',
        ];
    }

    /**
     * @param  User  $notifiable
     */
    public function toTelegram(object $notifiable): TelegramMessage
    {
        /** @var MeetingType $type */
        $type = $this->meetingInstance->type;

        /** @var User $notifiable */
        return TelegramMessage::create()
            ->to($notifiable->telegram_chat_id)
            ->content("📅 Adunanza {$type->getLabel()} tra 30 minuti\n🖥️ {$this->onesiBox->name}\n\nAccedi alla dashboard per confermare o saltare.");
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        /** @var User $notifiable */
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
