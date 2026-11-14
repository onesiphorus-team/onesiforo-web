<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

/**
 * A Telegram message about what a box is doing (turned on/off, meetings, playback),
 * sent on demand to a chat (support group or caregiver chat).
 */
class BoxActivityNotification extends Notification
{
    /**
     * @param  string  $html  Message in Telegram HTML (values already escaped)
     * @param  int|null  $topicId  Topic of a group with topics, e.g. "support"
     */
    public function __construct(
        public string $html,
        public ?int $topicId = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->content($this->html)
            ->parseMode('HTML')
            ->linkPreviewOptions(['is_disabled' => true]);

        return $this->topicId !== null ? $message->messageThreadId($this->topicId) : $message;
    }
}
