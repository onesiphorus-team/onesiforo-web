<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\Schemas;

use App\Models\Recipient;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OnesiBoxForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Informazioni Dispositivo'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nome'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder(__('es. OnesiBox-001')),

                        TextInput::make('serial_number')
                            ->label(__('Numero Seriale'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder(__('es. OB-12345678')),

                        TextInput::make('firmware_version')
                            ->label(__('Versione Firmware'))
                            ->maxLength(50)
                            ->placeholder(__('es. 1.0.0')),

                        Toggle::make('is_active')
                            ->label(__('Attivo'))
                            ->default(true)
                            ->helperText(__('Se disabilitato, il dispositivo non potrà comunicare con il server.')),
                    ]),

                Section::make(__('Assegnazione Beneficiario'))
                    ->schema([
                        Select::make('recipient_id')
                            ->label(__('Beneficiario'))
                            ->relationship('recipient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Recipient $record): string => $record->full_name)
                            ->searchable(['first_name', 'last_name'])
                            ->preload()
                            ->nullable()
                            ->createOptionForm(RecipientFieldset::getSchema())
                            ->createOptionUsing(fn (array $data): int => Recipient::query()->create($data)->id)
                            ->helperText(__('Seleziona un beneficiario esistente o creane uno nuovo.')),
                    ]),

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
