<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Tables;

use App\Models\OnesiBox;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OnesiBoxesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nome'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('serial_number')
                    ->label(__('Numero Seriale'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('recipient.full_name')
                    ->label(__('Beneficiario'))
                    ->state(fn (OnesiBox $record): ?string => $record->recipient?->full_name)
                    ->searchable(['recipient.first_name', 'recipient.last_name'])
                    ->placeholder(__('Non assegnato')),

                IconColumn::make('is_online')
                    ->label(__('Stato'))
                    ->state(fn (OnesiBox $record): string => self::getOnlineStatus($record))
                    ->icon(fn (string $state): string => match ($state) {
                        'online' => 'heroicon-o-signal',
                        'offline' => 'heroicon-o-signal-slash',
                        'never' => 'heroicon-o-question-mark-circle',
                        'disabled' => 'heroicon-o-no-symbol',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        'never' => 'warning',
                        'disabled' => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'online' => __('Online'),
                        'offline' => __('Offline'),
                        'never' => __('Mai connesso'),
                        'disabled' => __('Disabilitato'),
                        default => __('Sconosciuto'),
                    }),

                TextColumn::make('last_seen_at')
                    ->label(__('Ultimo Contatto'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder(__('Mai'))
                    ->sortable(),

                TextColumn::make('firmware_version')
                    ->label(__('Firmware'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('caregivers_count')
                    ->label(__('Caregiver'))
                    ->counts('caregivers')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),

                IconColumn::make('is_active')
                    ->label(__('Attivo'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('Stato Attivazione')),

                SelectFilter::make('online_status')
                    ->label(__('Stato Connessione'))
                    ->options([
                        'online' => __('Online'),
                        'offline' => __('Offline'),
                        'never' => __('Mai connesso'),
                    ])
                    ->query(fn ($query, array $data) => match ($data['value']) {
                        'online' => $query->where('last_seen_at', '>=', now()->subMinutes(5)),
                        'offline' => $query->where('last_seen_at', '<', now()->subMinutes(5)),
                        'never' => $query->whereNull('last_seen_at'),
                        default => $query,
                    }),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
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

    private static function getOnlineStatus(OnesiBox $record): string
    {
        if (! $record->is_active) {
            return 'disabled';
        }

        if ($record->last_seen_at === null) {
            return 'never';
        }

        return $record->isOnline() ? 'online' : 'offline';
    }
}
