<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum OnesiBoxPermission: string implements HasColor, HasDescription, HasLabel
{
    case Full = 'full';
    case ReadOnly = 'read-only';

    public function getLabel(): string
    {
        return match ($this) {
            self::Full => __('Completo'),
            self::ReadOnly => __('Sola lettura'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Full => __('Può inviare comandi e visualizzare lo stato'),
            self::ReadOnly => __('Può solo visualizzare lo stato'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Full => 'success',
            self::ReadOnly => 'gray',
        };
    }
}
