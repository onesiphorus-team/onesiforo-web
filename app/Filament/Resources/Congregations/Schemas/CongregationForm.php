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
use Filament\Support\Icons\Heroicon;

class CongregationForm
{
    public static function configure(Schema $schema): Schema
    {
        $days = Days::labels();

        return $schema
            ->components([
                Section::make(__('Informazioni Congregazione'))
                    ->icon(Heroicon::OutlinedBuildingLibrary)
                    ->description(__('Dati identificativi e link alla riunione Zoom'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nome'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        TextInput::make('zoom_url')
                            ->label(__('URL Zoom'))
                            ->url()
                            ->required()
                            ->rules([new ZoomUrl])
                            ->placeholder('https://us05web.zoom.us/j/1234567890?pwd=abc123')
                            ->helperText(__('Formato atteso: https://*.zoom.us/j/<id>, /w/ o /s/, con pwd opzionale.'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        Section::make(__('Adunanza Infrasettimanale'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('midweek_day')
                                            ->label(__('Giorno'))
                                            ->options($days)
                                            ->native(false)
                                            ->required(),

                                        TimePicker::make('midweek_time')
                                            ->label(__('Orario'))
                                            ->required()
                                            ->seconds(false),
                                    ]),
                            ]),

                        Section::make(__('Adunanza del Fine Settimana'))
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('weekend_day')
                                            ->label(__('Giorno'))
                                            ->options($days)
                                            ->native(false)
                                            ->required(),

                                        TimePicker::make('weekend_time')
                                            ->label(__('Orario'))
                                            ->required()
                                            ->seconds(false),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make(__('Impostazioni'))
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->schema([
                        Select::make('timezone')
                            ->label(__('Fuso Orario'))
                            ->options(fn (): array => collect(timezone_identifiers_list())
                                ->mapWithKeys(fn (string $tz): array => [$tz => $tz])
                                ->all())
                            ->searchable()
                            ->required()
                            ->default('Europe/Rome')
                            ->columnSpan(1),

                        Toggle::make('is_active')
                            ->label(__('Attiva'))
                            ->helperText(__('Se disabilitata, non verrà pianificata alcuna adunanza per questa congregazione.'))
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
