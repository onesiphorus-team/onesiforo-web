<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\RelationManagers;

use App\Actions\GenerateOnesiBoxToken;
use App\Models\OnesiBox;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class TokensRelationManager extends RelationManager
{
    public ?string $generatedToken = null;

    protected static string $relationship = 'tokens';

    protected static ?string $title = 'Token di Autenticazione';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Token form is not used - tokens are generated via action
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nome Token'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('Creato il'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_used_at')
                    ->label(__('Ultimo Utilizzo'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('Mai')),
                TextColumn::make('expires_at')
                    ->label(__('Scadenza'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                $this->getGenerateTokenAction(),
            ])
            ->recordActions([
                $this->getRevokeTokenAction(),
            ])
            ->toolbarActions([
                // Bulk actions if needed
            ]);
    }

    protected function getGenerateTokenAction(): Action
    {
        return Action::make('generate_token')
            ->label(__('Genera Token'))
            ->icon('heroicon-o-key')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('Genera Nuovo Token API'))
            ->modalDescription(__('Verrà generato un nuovo token API per questa OnesiBox. Assicurati di copiare il token dopo la generazione — non sarà più visualizzato.'))
            ->modalSubmitActionLabel(__('Genera'))
            ->action(function (): void {
                /** @var OnesiBox $onesiBox */
                $onesiBox = $this->getOwnerRecord();

                $generateToken = new GenerateOnesiBoxToken;
                $newToken = $generateToken($onesiBox);

                $this->generatedToken = $newToken->plainTextToken;

                Notification::make()
                    ->title(__('Token Generato con Successo'))
                    ->body(__('Copia questo token adesso. Non sarà più visualizzato: ')."\n\n".$this->generatedToken)
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    protected function getRevokeTokenAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label(__('Revoca'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Revoca Token'))
            ->modalDescription(__('Sei sicuro di voler revocare questo token? Questa azione non può essere annullata e qualsiasi dispositivo che utilizza questo token perderà l\'accesso.'))
            ->modalSubmitActionLabel(__('Revoca Token'))
            ->before(function (PersonalAccessToken $record): void {
                /** @var OnesiBox $onesiBox */
                $onesiBox = $this->getOwnerRecord();

                activity()
                    ->performedOn($onesiBox)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'token_id' => $record->id,
                        'token_name' => $record->name,
                    ])
                    ->log('API token revoked');
            })
            ->successNotificationTitle(__('Token Revocato'));
    }
}
