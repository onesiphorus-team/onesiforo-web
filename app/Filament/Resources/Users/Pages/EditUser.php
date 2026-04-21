<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<string> */
    protected array $roles = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->record;

        // Add roles to the form data
        $data['roles'] = $record->roles->pluck('name')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract roles from data to handle separately
        $this->roles = $data['roles'] ?? [];
        unset($data['roles']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $record */
        $record = $this->record;

        // Sync roles with the user
        $currentRoles = $record->roles->pluck('name')->toArray();
        $newRoles = $this->roles;

        // Only sync if roles have changed
        if (array_diff($currentRoles, $newRoles) || array_diff($newRoles, $currentRoles)) {
            $record->syncRoles(...$newRoles);

            // Log role changes
            activity()
                ->performedOn($record)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_roles' => $currentRoles,
                    'new_roles' => $newRoles,
                ])
                ->log('Roles updated');
        }
    }
}
