<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlaylistSourceType: string implements HasLabel
{
    case Manual = 'manual';
    case JworgSection = 'jworg_section';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => __('Manuale'),
            self::JworgSection => __('Sezione JW.org'),
        };
    }
}
