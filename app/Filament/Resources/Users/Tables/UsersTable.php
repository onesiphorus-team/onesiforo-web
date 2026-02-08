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
use Filament\Tables\Enums\FiltersLayout;
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
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                IconColumn::make('email_verified_at')
                    ->label(__('Verified'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(function (User $record): string {
                        if (! $record->hasVerifiedEmail()) {
                            return __('Email not verified');
                        }

                        /** @var \Illuminate\Support\Carbon $verifiedAt */
                        $verifiedAt = $record->email_verified_at;

                        return __('Email verified on :date', ['date' => $verifiedAt->format('d/m/Y H:i')]);
                    }),

                TextColumn::make('roles.name')
                    ->label(__('Roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Roles::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state): string => Roles::tryFrom($state)?->getColor() ?? 'gray')
                    ->icon(fn (string $state): ?string => Roles::tryFrom($state)?->getIcon()),

                TextColumn::make('last_login_at')
                    ->label(__('Last Login'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('Never'))
                    ->sortable(),

                IconColumn::make('online_status')
                    ->label(__('Status'))
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
                        'online' => __('Online (active in last 5 minutes)'),
                        'offline' => __('Offline'),
                        'never' => __('Never connected'),
                        default => __('Unknown'),
                    }),

                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('Role'))
                    ->relationship('roles', 'name')
                    ->options(
                        collect(Roles::cases())
                            ->mapWithKeys(fn (Roles $role): array => [$role->value => $role->getLabel()])
                            ->all()
                    )
                    ->multiple()
                    ->preload(),

                TernaryFilter::make('email_verified_at')
                    ->label(__('Email Verification'))
                    ->placeholder(__('All users'))
                    ->trueLabel(__('Verified'))
                    ->falseLabel(__('Not verified'))
                    ->nullable(),

                SelectFilter::make('online_status')
                    ->label(__('Connection Status'))
                    ->options([
                        'online' => __('Online'),
                        'offline' => __('Offline'),
                        'never' => __('Never connected'),
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
            ], layout: FiltersLayout::AboveContent)
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
            ->label(__('Send Email Verification'))
            ->icon('heroicon-o-envelope')
            ->color('warning')
            ->visible(fn (User $record): bool => ! $record->hasVerifiedEmail())
            ->requiresConfirmation()
            ->modalHeading(__('Send Verification Email'))
            ->modalDescription(__('Are you sure you want to send the verification email to this user?'))
            ->action(function (User $record): void {
                $record->sendEmailVerificationNotification();

                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->log('Verification email sent');

                Notification::make()
                    ->title(__('Verification email sent'))
                    ->body(__('The verification email has been sent to :email', ['email' => $record->email]))
                    ->success()
                    ->send();
            });
    }

    private static function sendPasswordResetAction(): Action
    {
        return Action::make('send_password_reset')
            ->label(__('Send Password Reset'))
            ->icon('heroicon-o-key')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('Send Password Reset'))
            ->modalDescription(__('Are you sure you want to send the password reset link to this user?'))
            ->action(function (User $record): void {
                Password::sendResetLink(['email' => $record->email]);

                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->log('Password reset sent');

                Notification::make()
                    ->title(__('Password reset email sent'))
                    ->body(__('The password reset link has been sent to :email', ['email' => $record->email]))
                    ->success()
                    ->send();
            });
    }
}
