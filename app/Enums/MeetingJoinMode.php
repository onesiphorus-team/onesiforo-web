<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MeetingJoinMode: string implements HasLabel
{
    case Auto = 'auto';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Auto => 'Automatico',
            self::Manual => 'Manuale',
        };
    }
}
