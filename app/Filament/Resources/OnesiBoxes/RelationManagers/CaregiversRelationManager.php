<?php

declare(strict_types=1);

namespace App\Filament\Resources\OnesiBoxes\RelationManagers;

use App\Enums\OnesiBoxPermission;
use App\Enums\Roles;
use App\Models\OnesiBox;
use App\Models\OnesiBoxUser;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CaregiversRelationManager extends RelationManager
{
    protected static string $relationship = 'caregivers';

    protected static ?string $title = 'Caregiver';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('permission')
                    ->label(__('Permesso'))
                    ->options(OnesiBoxPermission::class)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nome'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('permission')
                    ->label(__('Permesso'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Assegnato il'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->headerActions([
                $this->getAttachAction(),
            ])
            ->recordActions([
                $this->getEditAction(),
                $this->getDetachAction(),
            ]);
    }

    protected function getAttachAction(): AttachAction
    {
        return AttachAction::make()
            ->label(__('Assegna Caregiver'))
            ->modalHeading(__('Assegna Caregiver'))
            ->modalSubmitActionLabel(__('Assegna'))
            ->preloadRecordSelect()
            ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->whereHas('roles', function (Builder $query): void {
                $query->where('name', Roles::Caregiver->value);
            }))
            ->recordSelectSearchColumns(['name', 'email'])
            ->schema(fn (AttachAction $action): array => [
                $action->getRecordSelect()
                    ->label(__('Caregiver')),
                Select::make('permission')
                    ->label(__('Permesso'))
                    ->options(OnesiBoxPermission::class)
                    ->default(OnesiBoxPermission::Full->value)
                    ->required(),
            ])
            ->after(function (User $record): void {
                /** @var OnesiBox $onesiBox */
                $onesiBox = $this->getOwnerRecord();

                activity()
                    ->performedOn($onesiBox)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'caregiver_id' => $record->id,
                        'caregiver_name' => $record->name,
                        'caregiver_email' => $record->email,
                    ])
                    ->log('Caregiver assigned');
            });
    }

    protected function getEditAction(): EditAction
    {
        return EditAction::make()
            ->label(__('Modifica'))
            ->modalHeading(__('Modifica Permesso'))
            ->modalSubmitActionLabel(__('Salva'))
            ->after(function (User $record): void {
                /** @var OnesiBox $onesiBox */
                $onesiBox = $this->getOwnerRecord();

                /** @var OnesiBoxUser|null $pivot */
                $pivot = $onesiBox->caregivers()->where('user_id', $record->id)->first()?->pivot;

                activity()
                    ->performedOn($onesiBox)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'caregiver_id' => $record->id,
                        'caregiver_name' => $record->name,
                        'permission' => $pivot?->permission?->value,
                    ])
                    ->log('Caregiver permission updated');
            });
    }

    protected function getDetachAction(): DetachAction
    {
        return DetachAction::make()
            ->label(__('Rimuovi'))
            ->modalHeading(__('Rimuovi Caregiver'))
            ->modalDescription(__('Sei sicuro di voler rimuovere questo caregiver? Il caregiver non potrà più accedere a questa OnesiBox.'))
            ->modalSubmitActionLabel(__('Rimuovi'))
            ->before(function (User $record): void {
                /** @var OnesiBox $onesiBox */
                $onesiBox = $this->getOwnerRecord();

                activity()
                    ->performedOn($onesiBox)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'caregiver_id' => $record->id,
                        'caregiver_name' => $record->name,
                        'caregiver_email' => $record->email,
                    ])
                    ->log('Caregiver removed');
            });
    }
}
