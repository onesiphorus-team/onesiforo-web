<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\RelationManagers;

use App\Actions\CustomCommands\CopyCustomCommandsAction;
use App\Models\CustomCommand;
use App\Models\OnesiBox;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
            Toggle::make('runs_before_content')
                ->label(__('Esegui automaticamente quando il box inizia a mostrare qualcosa'))
                ->helperText(__('Parte da solo all\'avvio di un video, di un audio o di una riunione Zoom (anche le adunanze automatiche), senza ritardarne l\'avvio. Utile per portare la TV sull\'ingresso del box (es. to-box.sh). Non riparte se è già stato eseguito nell\'ultimo minuto.'))
                ->default(false),
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
                IconColumn::make('runs_before_content')
                    ->label(__('Automatico'))
                    ->tooltip(__('Eseguito da solo quando il box inizia a mostrare qualcosa'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Nuovo comando'))
                    ->modalHeading(__('Nuovo comando personalizzato')),
                $this->copyFromAnotherBoxAction(),
            ])
            ->recordActions([
                EditAction::make()->label(__('Modifica')),
                DeleteAction::make()->label(__('Elimina')),
            ]);
    }

    /**
     * Copy the commands of another box (e.g. one connected to the same kind of TV).
     */
    private function copyFromAnotherBoxAction(): Action
    {
        return Action::make('copy_from_box')
            ->label(__('Copia da un altro box'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->modalHeading(__('Copia i comandi da un altro box'))
            ->modalDescription(__('Vengono copiati i comandi che questo box non ha ancora. Ricorda di installare gli script anche su questo box.'))
            ->modalSubmitActionLabel(__('Copia'))
            ->schema([
                Select::make('source_box_id')
                    ->label(__('Box da cui copiare'))
                    ->options(fn (): array => OnesiBox::query()
                        ->whereKeyNot($this->getOwnerRecord()->getKey())
                        ->whereHas('customCommands')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, CopyCustomCommandsAction $copyCustomCommands): void {
                /** @var OnesiBox $target */
                $target = $this->getOwnerRecord();
                $source = OnesiBox::query()->whereKey($data['source_box_id'])->firstOrFail();

                $copied = $copyCustomCommands->execute($source, $target);

                Notification::make()
                    ->title($copied === 0
                        ? __('Nessun comando da copiare: questo box li ha già tutti')
                        : trans_choice(':count comando copiato|:count comandi copiati', $copied, ['count' => $copied]))
                    ->success()
                    ->send();
            });
    }
}
