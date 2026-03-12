<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MeetingAttendanceStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Joined = 'joined';
    case Completed = 'completed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'In attesa',
            self::Confirmed => 'Confermata',
            self::Joined => 'Collegato',
            self::Completed => 'Completata',
            self::Skipped => 'Saltata',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Confirmed => 'warning',
            self::Joined => 'info',
            self::Completed => 'success',
            self::Skipped => 'danger',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Joined;
    }
}
