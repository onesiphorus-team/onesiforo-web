<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recipients\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RecipientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Informazioni Personali'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label(__('Nome'))
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('last_name')
                                    ->label(__('Cognome'))
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        TextInput::make('phone')
                            ->label(__('Telefono'))
                            ->tel()
                            ->maxLength(50)
                            ->regex('/^(\+39\s?)?(\d{2,4}\s?)?\d{6,10}$/')
                            ->validationMessages([
                                'regex' => __('Inserisci un numero di telefono italiano valido.'),
                            ])
                            ->placeholder(__('es. +39 02 1234567')),

                        Fieldset::make(__('Indirizzo'))
                            ->schema([
                                TextInput::make('street')
                                    ->label(__('Via'))
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('city')
                                            ->label(__('Città'))
                                            ->maxLength(100),

                                        TextInput::make('postal_code')
                                            ->label(__('CAP'))
                                            ->maxLength(10)
                                            ->placeholder(__('es. 20100')),

                                        TextInput::make('province')
                                            ->label(__('Provincia'))
                                            ->maxLength(2)
                                            ->placeholder(__('es. MI')),
                                    ]),
                            ])
                            ->columns(1),
                    ]),

                Section::make(__('Contatti di Emergenza'))
                    ->schema([
                        Repeater::make('emergency_contacts')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('Nome'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('phone')
                                            ->label(__('Telefono'))
                                            ->tel()
                                            ->required()
                                            ->maxLength(50),

                                        TextInput::make('relationship')
                                            ->label(__('Parentela'))
                                            ->maxLength(100)
                                            ->placeholder(__('es. Figlio, Nipote')),
                                    ]),
                            ])
                            ->addActionLabel(__('Aggiungi contatto'))
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0),
                    ])
                    ->collapsible(),

                Section::make(__('Note'))
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->placeholder(__('Note aggiuntive...'))
                            ->rows(3),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }
}
