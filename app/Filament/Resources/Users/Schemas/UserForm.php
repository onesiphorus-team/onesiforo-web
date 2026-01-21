<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: User::class,
                        ignorable: fn (?User $record): ?User => $record,
                        modifyRuleUsing: fn (Unique $rule) => $rule->withoutTrashed(),
                    ),

                CheckboxList::make('roles')
                    ->label(__('Roles'))
                    ->options(fn (): array => self::getAvailableRoleOptions())
                    ->disabled(fn (?User $record): bool => self::shouldDisableRoles($record))
                    ->in(fn (): array => array_keys(self::getAvailableRoleOptions())),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function getAvailableRoleOptions(): array
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        // Super-admin can assign all roles
        if ($currentUser->hasRole('super-admin')) {
            return [
                'super-admin' => __('Super Admin'),
                'admin' => __('Admin'),
                'caregiver' => __('Caregiver'),
            ];
        }

        // Admin can only assign caregiver role
        return [
            'caregiver' => __('Caregiver'),
        ];
    }

    private static function shouldDisableRoles(?User $record): bool
    {
        if (! $record instanceof User) {
            return false;
        }

        /** @var User $currentUser */
        $currentUser = Auth::user();

        // Super-admin can always edit roles
        if ($currentUser->hasRole('super-admin')) {
            return false;
        }

        // Admin cannot edit roles of admin or super-admin users
        return $record->hasAnyRoles('admin', 'super-admin');
    }
}
