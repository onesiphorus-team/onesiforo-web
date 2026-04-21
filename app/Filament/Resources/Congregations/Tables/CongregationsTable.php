<?php

declare(strict_types=1);

namespace App\Filament\Resources\Congregations\Tables;

use App\Support\Days;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CongregationsTable
{
    public static function configure(Table $table): Table
    {
        $dayLabels = Days::labels();

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
                    ->formatStateUsing(fn (int $state): string => (string) __($dayLabels[$state] ?? (string) $state))
                    ->sortable(),

                TextColumn::make('midweek_time')
                    ->label(__('Ora Infrasettimanale'))
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('weekend_day')
                    ->label(__('Giorno Fine Settimana'))
                    ->formatStateUsing(fn (int $state): string => (string) __($dayLabels[$state] ?? (string) $state))
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
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('Stato attivazione')),
            ])
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
