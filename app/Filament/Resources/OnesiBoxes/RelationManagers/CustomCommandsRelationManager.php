<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\RelationManagers;

use App\Models\CustomCommand;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomCommandsRelationManager extends RelationManager
{
    protected static string $relationship = 'customCommands';

    protected static ?string $title = 'Comandi personalizzati';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('Nome'))
                ->required()
                ->maxLength(100),
            Textarea::make('description')
                ->label(__('Descrizione'))
                ->rows(2)
                ->maxLength(500)
                ->nullable(),
            TextInput::make('script_name')
                ->label(__('Nome file script'))
                ->required()
                ->maxLength(100)
                ->regex(CustomCommand::SCRIPT_NAME_REGEX)
                ->helperText(__('Solo basename del file .sh. Deve esistere sulla OnesiBox in /opt/onesibox/custom-scripts/.')),
            TagsInput::make('static_args')
                ->label(__('Argomenti'))
                ->placeholder(__('Aggiungi argomento'))
                ->helperText(__('Argomenti fissi passati allo script. Lasciare vuoto se nessuno.'))
                ->nullable(),
            TextInput::make('icon')
                ->label(__('Icona Heroicon'))
                ->placeholder('heroicon-o-bolt')
                ->maxLength(100)
                ->nullable()
                ->helperText(__('Nome icona Heroicon, es. heroicon-o-tv, heroicon-o-power.')),
            Grid::make(2)->components([
                TextInput::make('sort_order')
                    ->label(__('Ordinamento'))
                    ->numeric()
                    ->default(0),
                Toggle::make('is_enabled')
                    ->label(__('Abilitato'))
                    ->default(true),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Nome'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('script_name')
                    ->label(__('Script'))
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('Descrizione'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                IconColumn::make('is_enabled')
                    ->label(__('Abilitato'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Nuovo comando'))
                    ->modalHeading(__('Nuovo comando personalizzato')),
            ])
            ->recordActions([
                EditAction::make()->label(__('Modifica')),
                DeleteAction::make()->label(__('Elimina')),
            ]);
    }
}
