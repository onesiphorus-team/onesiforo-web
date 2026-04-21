<?php

declare(strict_types=1);

namespace App\Filament\Resources\Congregations\Schemas;

use App\Rules\ZoomUrl;
use App\Support\Days;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CongregationForm
{
    public static function configure(Schema $schema): Schema
    {
        $days = Days::labels();

        return $schema
            ->components([
                Section::make(__('Informazioni Congregazione'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nome'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('zoom_url')
                            ->label(__('URL Zoom'))
                            ->url()
                            ->required()
                            ->rules([new ZoomUrl])
                            ->placeholder('https://us05web.zoom.us/j/1234567890?pwd=abc123')
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Adunanza Infrasettimanale'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('midweek_day')
                                    ->label(__('Giorno'))
                                    ->options($days)
                                    ->required(),

                                TimePicker::make('midweek_time')
                                    ->label(__('Orario'))
                                    ->required()
                                    ->seconds(false),
                            ]),
                    ]),

                Section::make(__('Adunanza del Fine Settimana'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('weekend_day')
                                    ->label(__('Giorno'))
                                    ->options($days)
                                    ->required(),

                                TimePicker::make('weekend_time')
                                    ->label(__('Orario'))
                                    ->required()
                                    ->seconds(false),
                            ]),
                    ]),

                Section::make(__('Impostazioni'))
                    ->schema([
                        Select::make('timezone')
                            ->label(__('Fuso Orario'))
                            ->options(fn () => collect(timezone_identifiers_list())->mapWithKeys(fn ($tz): array => [$tz => $tz])->all())
                            ->searchable()
                            ->required()
                            ->default('Europe/Rome'),

                        Toggle::make('is_active')
                            ->label(__('Attiva'))
                            ->default(true),
                    ]),
            ]);
    }
}
