<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The bot token is part of every Telegram API URL (https://api.telegram.org/bot<token>/...),
 * and HTTP errors quote that URL: it must be taken out before anything is logged.
 */
class TelegramToken
{
    public static function redact(string $text): string
    {
        $token = (string) config('services.telegram-bot-api.token');

        if ($token !== '') {
            $text = str_replace($token, '***', $text);
        }

        return (string) preg_replace('#/bot[^/\s]+/#', '/bot***/', $text);
    }
}
