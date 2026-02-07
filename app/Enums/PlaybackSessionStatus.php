<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PlaybackSessionStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Completed = 'completed';
    case Stopped = 'stopped';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => __('Attiva'),
            self::Completed => __('Completata'),
            self::Stopped => __('Interrotta'),
            self::Error => __('Errore'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-play-circle',
            self::Completed => 'heroicon-o-check-circle',
            self::Stopped => 'heroicon-o-stop-circle',
            self::Error => 'heroicon-o-exclamation-triangle',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Completed => 'info',
            self::Stopped => 'warning',
            self::Error => 'danger',
        };
    }

    /**
     * Check if the session is still running.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if the session has ended (by any means).
     */
    public function isEnded(): bool
    {
        return in_array($this, [self::Completed, self::Stopped, self::Error], true);
    }
}
