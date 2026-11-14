<?php

declare(strict_types=1);

namespace App\Services\BoxAlerts;

use App\Jobs\SendBoxActivityAlert;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Decides whether and where a box activity alert goes on Telegram.
 *
 * - Boxes with activity alerts turned off get none.
 * - Destination: the support chat (and topic) from config, otherwise the Telegram chats of the
 *   caregivers of the box, each chat once (several caregivers may share the same group).
 * - Alerts of the same kind can be held back for a while, so a flapping box or a playlist with
 *   many failing items does not flood the chat.
 */
class BoxActivityAlerts
{
    /**
     * @param  string  $text  Plain text (escaped when sent); {titolo} becomes the title of $mediaUrl
     * @param  string|null  $kind  Alerts of the same kind for the same box are sent at most once every $quietSeconds
     */
    public function send(OnesiBox $onesiBox, string $text, ?string $mediaUrl = null, ?string $kind = null, int $quietSeconds = 0): void
    {
        if (! $onesiBox->activity_notifications_enabled) {
            return;
        }

        $chatIds = $this->chatIdsFor($onesiBox);

        if ($chatIds === []) {
            return;
        }

        if ($kind !== null && $quietSeconds > 0 && ! Cache::add("box-alert:{$onesiBox->id}:{$kind}", true, $quietSeconds)) {
            return;
        }

        dispatch(new SendBoxActivityAlert($onesiBox->id, $text, $chatIds, $this->topicId(), $mediaUrl))->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    private function chatIdsFor(OnesiBox $onesiBox): array
    {
        $supportChatId = config('services.telegram-bot-api.support_chat_id');

        if (filled($supportChatId)) {
            return [(string) $supportChatId];
        }

        return $onesiBox->caregivers()
            ->whereNotNull('telegram_chat_id')
            ->get()
            ->map(fn (User $caregiver): ?string => $caregiver->telegram_chat_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function topicId(): ?int
    {
        $topicId = config('services.telegram-bot-api.support_topic_id');

        return filled($topicId) && filled(config('services.telegram-bot-api.support_chat_id')) ? (int) $topicId : null;
    }
}
