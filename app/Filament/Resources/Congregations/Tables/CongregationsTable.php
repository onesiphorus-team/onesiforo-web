<?php

declare(strict_types=1);

namespace App\Filament\Resources\Congregations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CongregationsTable
{
    private const array DAY_NAMES = [
        0 => 'Domenica',
        1 => 'Lunedì',
        2 => 'Martedì',
        3 => 'Mercoledì',
        4 => 'Giovedì',
        5 => 'Venerdì',
        6 => 'Sabato',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nome'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('zoom_url')
                    ->label(__('URL Zoom'))
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('midweek_day')
                    ->label(__('Giorno Infrasettimanale'))
                    ->formatStateUsing(fn (int $state): string => __(self::DAY_NAMES[$state] ?? (string) $state))
                    ->sortable(),

                TextColumn::make('midweek_time')
                    ->label(__('Ora Infrasettimanale'))
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('weekend_day')
                    ->label(__('Giorno Fine Settimana'))
                    ->formatStateUsing(fn (int $state): string => __(self::DAY_NAMES[$state] ?? (string) $state))
                    ->sortable(),

                TextColumn::make('weekend_time')
                    ->label(__('Ora Fine Settimana'))
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('recipients_count')
                    ->label(__('Beneficiari'))
                    ->counts('recipients')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('Attiva'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Creata il'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('Aggiornata il'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
