<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;

class RecipientFieldset
{
    /**
     * Get the schema components for creating a recipient inline.
     *
     * @return array<\Filament\Schemas\Components\Component>
     */
    public static function getSchema(): array
    {
        return [
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
        ];
    }
}
