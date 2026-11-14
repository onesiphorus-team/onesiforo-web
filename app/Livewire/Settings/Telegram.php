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
        $this->linkToken = Str::random(32);

        Cache::put(self::cacheKey($this->linkToken), $this->user()->id, now()->addMinutes(self::LINK_TTL_MINUTES));
    }

    public function disconnect(): void
    {
        $this->user()->update(['telegram_chat_id' => null]);
        $this->linkToken = null;

        unset($this->isConnected);
    }

    public function render(): View
    {
        return view('livewire.settings.telegram');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
