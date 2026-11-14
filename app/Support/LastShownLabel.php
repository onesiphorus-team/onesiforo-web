<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Etichetta leggibile che indica quando un contenuto è stato mostrato l'ultima volta.
 *
 * La granularità decresce con il tempo trascorso: minuti → ora del giorno → giorni → data.
 */
class LastShownLabel
{
    /**
     * Sotto questa soglia un contenuto è considerato "visto di recente".
     */
    public const int RECENT_DAYS = 7;

    public static function for(?CarbonInterface $lastShownAt): string
    {
        if (! $lastShownAt instanceof CarbonInterface) {
            return 'Mai mostrato';
        }

        $timezone = (string) config('app.display_timezone', 'Europe/Rome');
        $now = Date::now()->setTimezone($timezone);
        $lastShownAt = $lastShownAt->copy()->setTimezone($timezone);
        $minutesAgo = (int) $lastShownAt->diffInMinutes($now, absolute: true);

        if ($minutesAgo < 1) {
            return 'Proprio ora';
        }

        if ($minutesAgo < 60) {
            return $minutesAgo === 1 ? '1 minuto fa' : "{$minutesAgo} minuti fa";
        }

        if ($lastShownAt->isSameDay($now)) {
            return 'Oggi alle '.$lastShownAt->format('H:i');
        }

        if ($lastShownAt->isSameDay($now->copy()->subDay())) {
            return 'Ieri alle '.$lastShownAt->format('H:i');
        }

        $daysAgo = (int) $lastShownAt->copy()->startOfDay()->diffInDays($now->copy()->startOfDay(), absolute: true);

        if ($daysAgo < self::RECENT_DAYS) {
            return "{$daysAgo} giorni fa";
        }

        $format = $lastShownAt->year === $now->year ? 'j F' : 'j F Y';

        return 'Il '.$lastShownAt->locale('it')->translatedFormat($format);
    }

    public static function isRecent(?CarbonInterface $lastShownAt): bool
    {
        return $lastShownAt instanceof CarbonInterface
            && $lastShownAt->greaterThan(Date::now()->subDays(self::RECENT_DAYS));
    }
}
