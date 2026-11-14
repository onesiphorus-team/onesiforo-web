<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Livewire\Settings\Telegram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Points the bot to this application, for "Collega Telegram".
 *
 * A bot has a single webhook: setting it replaces any other webhook and stops getUpdates
 * polling done elsewhere with the same bot. Sending messages (the alerts) is not affected.
 */
#[\Illuminate\Console\Attributes\Description('Register the Telegram webhook used by "Collega Telegram"')]
#[\Illuminate\Console\Attributes\Signature('telegram:set-webhook {--force : Do not ask for confirmation}')]
class SetTelegramWebhookCommand extends Command
{
    public function handle(): int
    {
        if (! Telegram::isAvailable() || blank(config('services.telegram-bot-api.webhook_secret'))) {
            $this->error('Set TELEGRAM_LINKING_ENABLED=true, TELEGRAM_BOT_USERNAME and TELEGRAM_WEBHOOK_SECRET first.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This replaces any webhook (or getUpdates consumer) the bot already has. Continue?')) {
            return self::FAILURE;
        }

        $response = Http::post('https://api.telegram.org/bot'.config('services.telegram-bot-api.token').'/setWebhook', [
            'url' => route('telegram.webhook'),
            'secret_token' => config('services.telegram-bot-api.webhook_secret'),
            'allowed_updates' => ['message'],
        ]);

        if (! $response->successful() || $response->json('ok') !== true) {
            $this->error('Telegram refused the webhook: '.$response->json('description', $response->body()));

            return self::FAILURE;
        }

        $this->info('Webhook set to '.route('telegram.webhook'));

        return self::SUCCESS;
    }
}
