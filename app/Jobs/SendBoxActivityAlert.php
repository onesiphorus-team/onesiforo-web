<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OnesiBox;
use App\Notifications\BoxActivityNotification;
use App\Services\JwOrgCatalog;
use App\Support\JwMediaKey;
use App\Support\TelegramToken;
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
 *
 * One job per chat: when Telegram cannot be reached (timeout, 429) the job is tried again
 * later, and a retry never writes a second time to a chat that already got the alert.
 */
class SendBoxActivityAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Seconds to wait before the second and the third attempt.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(
        public int $onesiBoxId,
        public string $text,
        public string $chatId,
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

        try {
            Notification::route('telegram', $this->chatId)->notifyNow(new BoxActivityNotification($html, $this->topicId));
        } catch (Throwable $exception) {
            $this->retryLater($exception);
        }
    }

    /**
     * The exception is not thrown again: its message may quote the Telegram URL, bot token
     * included, and would end up in the logs and in the failed jobs.
     */
    private function retryLater(Throwable $exception): void
    {
        $attempt = $this->attempts();
        $context = [
            'onesi_box_id' => $this->onesiBoxId,
            'chat_id' => $this->chatId,
            'attempt' => $attempt,
            'exception' => $exception::class,
            'error' => TelegramToken::redact($exception->getMessage()),
        ];

        if ($attempt >= $this->tries) {
            Log::error('Telegram box alert not delivered, giving up', $context);

            return;
        }

        Log::warning('Telegram box alert not delivered, trying again later', $context);

        $this->release($this->backoff[$attempt - 1] ?? 120);
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
