<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MeetingInstanceStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Notified = 'notified';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Programmata',
            self::Notified => 'Notificata',
            self::InProgress => 'In corso',
            self::Completed => 'Completata',
            self::Cancelled => 'Cancellata',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Notified => 'warning',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled]);
    }
}
