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
    case Caregiver = 'caregiver';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Admin'),
            self::Caregiver => __('Caregiver'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Full system access with ability to manage all users and settings'),
            self::Admin => __('Can manage caregivers and access administrative functions'),
            self::Caregiver => __('Can manage assigned OnesiBox devices and patients'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SuperAdmin => 'heroicon-o-shield-check',
            self::Admin => 'heroicon-o-cog-6-tooth',
            self::Caregiver => 'heroicon-o-heart',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::Admin => 'warning',
            self::Caregiver => 'success',
        };
    }
}
