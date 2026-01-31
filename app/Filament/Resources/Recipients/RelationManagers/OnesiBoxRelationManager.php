<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recipients\RelationManagers;

use App\Filament\Resources\OnesiBoxes\OnesiBoxResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OnesiBoxRelationManager extends RelationManager
{
    protected static string $relationship = 'onesiBox';

    protected static ?string $title = 'OnesiBox';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nome'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('serial_number')
                    ->label(__('Numero Seriale'))
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label(__('Attivo'))
                    ->boolean(),

                TextColumn::make('firmware_version')
                    ->label(__('Firmware'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn ($record): string => OnesiBoxResource::getUrl('edit', ['record' => $record]));
    }
}
