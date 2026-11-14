<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Livewire\Settings\Telegram;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives the bot updates for "Collega Telegram": a user who opened their personal link
 * sends "/start <token>", and their chat id is saved on their account.
 *
 * Telegram signs each call with the secret given to setWebhook (header
 * X-Telegram-Bot-Api-Secret-Token); anything else is refused.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.telegram-bot-api.webhook_secret');

        abort_unless(Telegram::isAvailable() && $secret !== '', 404);
        abort_unless(hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $chatId = $request->input('message.chat.id');
        $isPrivateChat = $request->input('message.chat.type') === 'private';
        $text = (string) $request->input('message.text', '');

        if ($chatId === null || ! $isPrivateChat || preg_match('/^\/start\s+(\S+)/', $text, $matches) !== 1) {
            return response()->json(['ok' => true]);
        }

        $userId = Cache::pull(Telegram::cacheKey($matches[1]));
        $user = is_int($userId) ? User::query()->whereKey($userId)->first() : null;

        if ($user === null) {
            $this->reply($chatId, 'Questo link è scaduto o è già stato usato. Torna in Onesiforo, Impostazioni › Telegram, e tocca di nuovo «Collega Telegram».');

            return response()->json(['ok' => true]);
        }

        $user->update(['telegram_chat_id' => (string) $chatId]);

        $this->reply($chatId, "✅ Fatto, {$user->name}: da ora riceverai qui gli avvisi di Onesiforo.");

        return response()->json(['ok' => true]);
    }

    private function reply(int|string $chatId, string $text): void
    {
        try {
            Http::timeout(10)->post('https://api.telegram.org/bot'.config('services.telegram-bot-api.token').'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Telegram webhook reply not sent', ['error' => $exception->getMessage()]);
        }
    }
}
