<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PlaybackEventType: string implements HasColor, HasIcon, HasLabel
{
    case Started = 'started';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Stopped = 'stopped';
    case Completed = 'completed';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Started => __('Avviato'),
            self::Paused => __('In pausa'),
            self::Resumed => __('Ripreso'),
            self::Stopped => __('Fermato'),
            self::Completed => __('Completato'),
            self::Error => __('Errore'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Started => 'heroicon-o-play',
            self::Paused => 'heroicon-o-pause',
            self::Resumed => 'heroicon-o-play-circle',
            self::Stopped => 'heroicon-o-stop',
            self::Completed => 'heroicon-o-check-circle',
            self::Error => 'heroicon-o-exclamation-triangle',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Started => 'success',
            self::Paused => 'warning',
            self::Resumed => 'success',
            self::Stopped => 'gray',
            self::Completed => 'info',
            self::Error => 'danger',
        };
    }
}
