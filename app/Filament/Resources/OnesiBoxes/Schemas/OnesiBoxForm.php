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
                Section::make(__('Device Information'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder(__('e.g. OnesiBox-001')),

                        TextInput::make('serial_number')
                            ->label(__('Serial Number'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder(__('e.g. OB-12345678')),

                        TextInput::make('firmware_version')
                            ->label(__('Firmware Version'))
                            ->maxLength(50)
                            ->placeholder(__('e.g. 1.0.0')),

                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->helperText(__('If disabled, the appliance cannot communicate with the server.')),
                    ]),

                Section::make(__('Recipient Assignment'))
                    ->schema([
                        Select::make('recipient_id')
                            ->label(__('Recipient'))
                            ->relationship('recipient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Recipient $record): string => $record->full_name)
                            ->searchable(['first_name', 'last_name'])
                            ->preload()
                            ->nullable()
                            ->createOptionForm(RecipientFieldset::getSchema())
                            ->createOptionUsing(fn (array $data): int => Recipient::query()->create($data)->id)
                            ->helperText(__('Select an existing recipient or create a new one.')),
                    ]),

                Section::make(__('Notes'))
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->placeholder(__('Any additional notes...'))
                            ->rows(3),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }
}
