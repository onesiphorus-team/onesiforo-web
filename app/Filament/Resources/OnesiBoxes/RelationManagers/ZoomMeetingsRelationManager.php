<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\RelationManagers;

use App\Rules\ZoomUrl;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Zoom meetings the box joins besides its congregation meeting, offered to caregivers
 * as one-tap choices in the Zoom sheet.
 */
class ZoomMeetingsRelationManager extends RelationManager
{
    protected static string $relationship = 'zoomMeetings';

    protected static ?string $title = 'Riunioni Zoom';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('Nome'))
                ->placeholder('Adunanza Loreto')
                ->required()
                ->maxLength(100),
            TextInput::make('zoom_url')
                ->label(__('Link Zoom'))
                ->placeholder('https://us05web.zoom.us/j/1234567890?pwd=...')
                ->url()
                ->required()
                ->maxLength(2048)
                ->rule(new ZoomUrl),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nome'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('zoom_url')
                    ->label(__('Link Zoom'))
                    ->fontFamily('mono')
                    ->limit(50)
                    ->copyable(),
                TextColumn::make('last_used_at')
                    ->label(__('Ultimo utilizzo'))
                    ->since()
                    ->placeholder(__('Mai'))
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Nuova riunione'))
                    ->modalHeading(__('Nuova riunione Zoom')),
            ])
            ->recordActions([
                EditAction::make()->label(__('Modifica')),
                DeleteAction::make()->label(__('Elimina')),
            ]);
    }
}
