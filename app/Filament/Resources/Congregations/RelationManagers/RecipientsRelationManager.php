<?php

declare(strict_types=1);

namespace App\Filament\Resources\Congregations\RelationManagers;

use App\Filament\Resources\Recipients\RecipientResource;
use App\Models\Recipient;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Beneficiari';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('Nome'))
                    ->state(fn (Recipient $record): string => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),

                TextColumn::make('phone')
                    ->label(__('Telefono'))
                    ->searchable(),

                TextColumn::make('onesiBox.name')
                    ->label(__('OnesiBox'))
                    ->placeholder(__('Non assegnato'))
                    ->searchable(),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label(__('Associa beneficiario'))
                    ->multiple()
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                DissociateAction::make(),
            ])
            ->toolbarActions([
                DissociateBulkAction::make(),
            ])
            ->recordUrl(fn (Recipient $record): string => RecipientResource::getUrl('edit', ['record' => $record]));
    }
}
