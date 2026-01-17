<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Roles: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Admin'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Admin'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SuperAdmin => 'heroicon-o-shield-check',
            self::Admin => 'heroicon-o-cog',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'warning',
        };
    }
}
