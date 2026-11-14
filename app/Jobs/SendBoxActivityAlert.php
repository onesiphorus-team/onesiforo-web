<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OnesiBox;
use App\Notifications\BoxActivityNotification;
use App\Services\JwOrgCatalog;
use App\Support\JwMediaKey;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Sends a box activity alert to Telegram, from the queue so neither the box nor the
 * caregiver ever wait for Telegram (or for JW.org, when a title has to be looked up).
 *
 * The text may contain {titolo}: it is replaced by the title of $mediaUrl.
 */
class SendBoxActivityAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<int, string>  $chatIds
     */
    public function __construct(
        public int $onesiBoxId,
        public string $text,
        public array $chatIds,
        public ?int $topicId = null,
        public ?string $mediaUrl = null,
    ) {}

    public function handle(JwOrgCatalog $catalog): void
    {
        $onesiBox = OnesiBox::query()->with('recipient')->find($this->onesiBoxId);

        if ($onesiBox === null) {
            return;
        }

        $html = $this->header($onesiBox)."\n".str_replace('{titolo}', $this->mediaTitle($catalog), e($this->text));

        foreach ($this->chatIds as $chatId) {
            try {
                Notification::route('telegram', $chatId)->notifyNow(new BoxActivityNotification($html, $this->topicId));
            } catch (Throwable $exception) {
                // One unreachable chat must not stop the others.
                Log::warning('Telegram box alert not delivered', ['chat_id' => $chatId, 'error' => $exception->getMessage()]);
            }
        }
    }

    private function header(OnesiBox $onesiBox): string
    {
        $person = $onesiBox->recipient?->full_name;

        return $person !== null
            ? '<b>'.e($person).'</b> · '.e($onesiBox->name)
            : '<b>'.e($onesiBox->name).'</b>';
    }

    private function mediaTitle(JwOrgCatalog $catalog): string
    {
        if ($this->mediaUrl === null) {
            return '';
        }

        $mediaKey = JwMediaKey::fromUrl($this->mediaUrl);

        try {
            $title = $mediaKey !== null ? ($catalog->mediaItem($mediaKey)['title'] ?? null) : null;
        } catch (RuntimeException|ConnectionException) {
            $title = null;
        }

        return $title !== null
            ? '«'.e($title).'»'
            : e((string) (parse_url($this->mediaUrl, PHP_URL_HOST) ?: 'un contenuto'));
    }
}
