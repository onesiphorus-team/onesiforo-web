<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Roles;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make(__('Informazioni Personali'))
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nome'))
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
                    ]),

                Section::make(__('Stato Account'))
                    ->icon('heroicon-o-information-circle')
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->schema([
                        IconEntry::make('email_verified_at')
                            ->label(__('Email Verificata'))
                            ->boolean()
                            ->trueIcon('heroicon-o-check-badge')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        TextEntry::make('last_login_at')
                            ->label(__('Ultimo Accesso'))
                            ->dateTime('d/m/Y H:i')
                            ->placeholder(__('Mai')),

                        TextEntry::make('created_at')
                            ->label(__('Registrato il'))
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make(__('Ruoli'))
                    ->icon('heroicon-o-shield-check')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('roles')
                            ->hiddenLabel()
                            ->options(fn (): array => self::getAvailableRoleOptions())
                            ->descriptions(fn (): array => self::getAvailableRoleDescriptions())
                            ->disabled(fn (?User $record): bool => self::shouldDisableRoles($record))
                            ->in(fn (): array => array_keys(self::getAvailableRoleOptions()))
                            ->columns(3),
                    ]),
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
        if ($currentUser->hasRole(Roles::SuperAdmin)) {
            return [
                Roles::SuperAdmin->value => Roles::SuperAdmin->getLabel(),
                Roles::Admin->value => Roles::Admin->getLabel(),
                Roles::Caregiver->value => Roles::Caregiver->getLabel(),
            ];
        }

        // Admin can only assign caregiver role
        return [
            Roles::Caregiver->value => Roles::Caregiver->getLabel(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function getAvailableRoleDescriptions(): array
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if ($currentUser->hasRole(Roles::SuperAdmin)) {
            return [
                Roles::SuperAdmin->value => Roles::SuperAdmin->getDescription(),
                Roles::Admin->value => Roles::Admin->getDescription(),
                Roles::Caregiver->value => Roles::Caregiver->getDescription(),
            ];
        }

        return [
            Roles::Caregiver->value => Roles::Caregiver->getDescription(),
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
        if ($currentUser->hasRole(Roles::SuperAdmin)) {
            return false;
        }

        // Admin cannot edit roles of admin or super-admin users
        return $record->hasAnyRoles(Roles::Admin, Roles::SuperAdmin);
    }
}
