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
                        ->label(__('First Name'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('last_name')
                        ->label(__('Last Name'))
                        ->required()
                        ->maxLength(255),
                ]),

            TextInput::make('phone')
                ->label(__('Phone'))
                ->tel()
                ->maxLength(50)
                ->regex('/^(\+39\s?)?(\d{2,4}\s?)?\d{6,10}$/')
                ->validationMessages([
                    'regex' => __('Please enter a valid Italian phone number.'),
                ])
                ->placeholder(__('e.g. +39 02 1234567')),

            Fieldset::make(__('Address'))
                ->schema([
                    TextInput::make('street')
                        ->label(__('Street'))
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Grid::make(3)
                        ->schema([
                            TextInput::make('city')
                                ->label(__('City'))
                                ->maxLength(100),

                            TextInput::make('postal_code')
                                ->label(__('Postal Code'))
                                ->maxLength(10)
                                ->placeholder(__('e.g. 20100')),

                            TextInput::make('province')
                                ->label(__('Province'))
                                ->maxLength(2)
                                ->placeholder(__('e.g. MI')),
                        ]),
                ])
                ->columns(1),
        ];
    }
}
