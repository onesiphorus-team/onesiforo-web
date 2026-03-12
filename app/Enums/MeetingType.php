<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MeetingType: string implements HasLabel
{
    case Midweek = 'midweek';
    case Weekend = 'weekend';
    case Adhoc = 'adhoc';

    public function getLabel(): string
    {
        return match ($this) {
            self::Midweek => 'Infrasettimanale',
            self::Weekend => 'Fine settimana',
            self::Adhoc => 'Ad-hoc',
        };
    }
}
