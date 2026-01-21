<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OnesiBoxStatus: string implements HasColor, HasIcon, HasLabel
{
    case Idle = 'idle';
    case Playing = 'playing';
    case Calling = 'calling';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Idle => __('Inattivo'),
            self::Playing => __('In riproduzione'),
            self::Calling => __('In chiamata'),
            self::Error => __('Errore'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Idle => 'heroicon-o-pause-circle',
            self::Playing => 'heroicon-o-play-circle',
            self::Calling => 'heroicon-o-phone',
            self::Error => 'heroicon-o-exclamation-triangle',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Idle => 'gray',
            self::Playing => 'success',
            self::Calling => 'info',
            self::Error => 'danger',
        };
    }
}
