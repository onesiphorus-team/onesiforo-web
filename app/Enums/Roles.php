<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum Roles: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';

    case CareGiver = 'care-giver';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Admin'),
            self::CareGiver => __('Care Giver'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Admin'),
            self::CareGiver => __('Care Giver'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SuperAdmin => 'heroicon-o-shield-check',
            self::Admin => 'heroicon-o-cog',
            self::CareGiver => Heroicon::OutlinedHeart->value,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'warning',
            self::CareGiver => 'success',
        };
    }
}
