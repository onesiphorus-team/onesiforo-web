<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recipients\Tables;

use App\Models\Recipient;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecipientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['onesiBox', 'congregation']))
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('Nome'))
                    ->state(fn (Recipient $record): string => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),

                TextColumn::make('phone')
                    ->label(__('Telefono'))
                    ->searchable(),

                TextColumn::make('full_address')
                    ->label(__('Indirizzo'))
                    ->state(fn (Recipient $record): ?string => $record->full_address)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('onesiBox.name')
                    ->label(__('OnesiBox'))
                    ->placeholder(__('Non assegnato'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('congregation.name')
                    ->label(__('Congregazione'))
                    ->placeholder(__('Non assegnata'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('Creato il'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('Aggiornato il'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->label(__('Eliminato il'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('first_name')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
