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

    protected static ?string $title = 'Authentication Tokens';

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
                    ->label(__('Token Name'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_used_at')
                    ->label(__('Last Used'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('Never')),
                TextColumn::make('expires_at')
                    ->label(__('Expires'))
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
            ->label(__('Generate Token'))
            ->icon('heroicon-o-key')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('Generate New API Token'))
            ->modalDescription(__('A new API token will be generated for this OnesiBox. Make sure to copy the token after generation - it will not be shown again.'))
            ->modalSubmitActionLabel(__('Generate'))
            ->action(function (): void {
                /** @var OnesiBox $onesiBox */
                $onesiBox = $this->getOwnerRecord();

                $generateToken = new GenerateOnesiBoxToken;
                $newToken = $generateToken($onesiBox);

                $this->generatedToken = $newToken->plainTextToken;

                Notification::make()
                    ->title(__('Token Generated Successfully'))
                    ->body(__('Copy this token now. It will not be shown again: ')."\n\n".$this->generatedToken)
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    protected function getRevokeTokenAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label(__('Revoke'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Revoke Token'))
            ->modalDescription(__('Are you sure you want to revoke this token? This action cannot be undone and any devices using this token will lose access.'))
            ->modalSubmitActionLabel(__('Revoke Token'))
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
            ->successNotificationTitle(__('Token Revoked'));
    }
}
