<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Roles;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nome'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                IconColumn::make('email_verified_at')
                    ->label(__('Verificato'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(function (User $record): string {
                        if (! $record->hasVerifiedEmail()) {
                            return __('Email non verificata');
                        }

                        /** @var \Illuminate\Support\Carbon $verifiedAt */
                        $verifiedAt = $record->email_verified_at;

                        return __('Email verificata il :date', ['date' => $verifiedAt->format('d/m/Y H:i')]);
                    }),

                TextColumn::make('roles.name')
                    ->label(__('Ruoli'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Roles::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state): string => Roles::tryFrom($state)?->getColor() ?? 'gray')
                    ->icon(fn (string $state): ?string => Roles::tryFrom($state)?->getIcon()),

                TextColumn::make('last_login_at')
                    ->label(__('Ultimo Accesso'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('Mai'))
                    ->sortable(),

                IconColumn::make('online_status')
                    ->label(__('Stato'))
                    ->state(fn (User $record): string => self::getOnlineStatus($record))
                    ->icon(fn (string $state): string => match ($state) {
                        'online' => 'heroicon-o-check-circle',
                        'offline' => 'heroicon-o-minus-circle',
                        'never' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'gray',
                        'never' => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'online' => __('Online (attivo negli ultimi 5 minuti)'),
                        'offline' => __('Offline'),
                        'never' => __('Mai connesso'),
                        default => __('Sconosciuto'),
                    }),

                TextColumn::make('created_at')
                    ->label(__('Creato il'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('Ruolo'))
                    ->relationship('roles', 'name')
                    ->options(
                        collect(Roles::cases())
                            ->mapWithKeys(fn (Roles $role): array => [$role->value => $role->getLabel()])
                            ->all()
                    )
                    ->multiple()
                    ->preload(),

                TernaryFilter::make('email_verified_at')
                    ->label(__('Verifica Email'))
                    ->placeholder(__('Tutti gli utenti'))
                    ->trueLabel(__('Verificati'))
                    ->falseLabel(__('Non verificati'))
                    ->nullable(),

                SelectFilter::make('online_status')
                    ->label(__('Stato Connessione'))
                    ->options([
                        'online' => __('Online'),
                        'offline' => __('Offline'),
                        'never' => __('Mai connesso'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === null) {
                            return $query;
                        }

                        return match ($value) {
                            'online' => $query->where('last_login_at', '>=', now()->subMinutes(5)),
                            'offline' => $query->whereNotNull('last_login_at')
                                ->where('last_login_at', '<', now()->subMinutes(5)),
                            'never' => $query->whereNull('last_login_at'),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::resendVerificationAction(),
                    self::sendPasswordResetAction(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    private static function getOnlineStatus(User $record): string
    {
        if (! $record->last_login_at) {
            return 'never';
        }

        if ($record->last_login_at->diffInMinutes(now()) < 5) {
            return 'online';
        }

        return 'offline';
    }

    private static function resendVerificationAction(): Action
    {
        return Action::make('resend_verification')
            ->label(__('Invia Verifica Email'))
            ->icon('heroicon-o-envelope')
            ->color('warning')
            ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail())
            ->requiresConfirmation()
            ->modalHeading(__('Invia Email di Verifica'))
            ->modalDescription(__('Sei sicuro di voler inviare l\'email di verifica a questo utente?'))
            ->action(function (User $record): void {
                $record->sendEmailVerificationNotification();

                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->log('Verification email sent');

                Notification::make()
                    ->title(__('Email di verifica inviata'))
                    ->body(__('L\'email di verifica è stata inviata a :email', ['email' => $record->email]))
                    ->success()
                    ->send();
            });
    }

    private static function sendPasswordResetAction(): Action
    {
        return Action::make('send_password_reset')
            ->label(__('Invia Reset Password'))
            ->icon('heroicon-o-key')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('Invia Reset Password'))
            ->modalDescription(__('Sei sicuro di voler inviare il link di reset password a questo utente?'))
            ->action(function (User $record): void {
                Password::sendResetLink(['email' => $record->email]);

                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->log('Password reset sent');

                Notification::make()
                    ->title(__('Email di reset password inviata'))
                    ->body(__('Il link di reset password è stato inviato a :email', ['email' => $record->email]))
                    ->success()
                    ->send();
            });
    }
}
