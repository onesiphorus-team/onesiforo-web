<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Origin of an OnesiBox log snapshot.
 *
 * Scheduled snapshots are produced by the periodic `onesibox:collect-logs`
 * command; manual ones by an operator asking for logs from the admin panel
 * or the caregiver dashboard.
 */
enum LogSnapshotSource: string implements HasColor, HasIcon, HasLabel
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => __('Periodico'),
            self::Manual => __('Manuale'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Scheduled => 'heroicon-o-clock',
            self::Manual => 'heroicon-o-hand-raised',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Manual => 'info',
        };
    }
}
