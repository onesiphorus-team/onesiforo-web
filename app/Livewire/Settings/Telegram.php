<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Collega Telegram": the user opens the bot from a personal link, presses Start, and the bot
 * (see TelegramWebhookController) saves the chat id, so alerts reach them on Telegram.
 *
 * Available only when services.telegram-bot-api.linking_enabled is on.
 *
 * @property-read bool $isConnected
 * @property-read string|null $linkUrl
 */
class Telegram extends Component
{
    /**
     * How long a personal link stays valid.
     */
    public const int LINK_TTL_MINUTES = 30;

    #[Locked]
    public ?string $linkToken = null;

    /**
     * When the personal link stops working (Unix timestamp).
     */
    #[Locked]
    public ?int $linkExpiresAt = null;

    /**
     * The user waited too long: the page went back to the start and says why.
     */
    #[Locked]
    public bool $linkExpired = false;

    public static function isAvailable(): bool
    {
        return (bool) config('services.telegram-bot-api.linking_enabled')
            && filled(config('services.telegram-bot-api.bot_username'))
            && filled(config('services.telegram-bot-api.token'));
    }

    public static function cacheKey(string $token): string
    {
        return "telegram-link:{$token}";
    }

    /**
     * Where the token of the link a user is waiting on is remembered, so that asking for a
     * new link invalidates the previous one even after the page was reloaded.
     */
    public static function pendingTokenCacheKey(int $userId): string
    {
        return "telegram-link-user:{$userId}";
    }

    public function mount(): void
    {
        abort_unless(self::isAvailable(), 404);
    }

    #[Computed]
    public function isConnected(): bool
    {
        return filled($this->user()->fresh()?->telegram_chat_id);
    }

    #[Computed]
    public function linkUrl(): ?string
    {
        if ($this->linkToken === null) {
            return null;
        }

        return 'https://t.me/'.config('services.telegram-bot-api.bot_username').'?start='.$this->linkToken;
    }

    public function startLinking(): void
    {
        $this->forgetPendingLink();

        $userId = $this->user()->id;
        $expiresAt = now()->addMinutes(self::LINK_TTL_MINUTES);

        $this->linkToken = Str::random(32);
        $this->linkExpiresAt = $expiresAt->getTimestamp();
        $this->linkExpired = false;

        Cache::put(self::cacheKey($this->linkToken), $userId, $expiresAt);
        Cache::put(self::pendingTokenCacheKey($userId), $this->linkToken, $expiresAt);
    }

    public function disconnect(): void
    {
        $this->user()->update(['telegram_chat_id' => null]);
        $this->forgetPendingLink();

        unset($this->isConnected);
    }

    public function render(): View
    {
        // The page polls while waiting: once the link is expired, stop and say so.
        if ($this->linkToken !== null && $this->linkExpiresAt !== null && $this->linkExpiresAt <= now()->getTimestamp()) {
            $this->forgetPendingLink();
            $this->linkExpired = true;
        }

        return view('livewire.settings.telegram');
    }

    /**
     * Make the link the user was waiting on (in this page or in a previous visit) unusable.
     */
    private function forgetPendingLink(): void
    {
        $pendingToken = Cache::pull(self::pendingTokenCacheKey($this->user()->id));

        foreach (array_unique(array_filter([$pendingToken, $this->linkToken], is_string(...))) as $token) {
            Cache::forget(self::cacheKey($token));
        }

        $this->linkToken = null;
        $this->linkExpiresAt = null;
        unset($this->linkUrl);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
