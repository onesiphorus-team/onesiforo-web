<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helper per le etichette dei giorni della settimana.
 *
 * Le chiavi corrispondono a Carbon::dayOfWeek (0 = Domenica .. 6 = Sabato).
 */
class Days
{
    /**
     * Etichette dei giorni della settimana in italiano.
     *
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return [
            0 => 'Domenica',
            1 => 'Lunedì',
            2 => 'Martedì',
            3 => 'Mercoledì',
            4 => 'Giovedì',
            5 => 'Venerdì',
            6 => 'Sabato',
        ];
    }
}
