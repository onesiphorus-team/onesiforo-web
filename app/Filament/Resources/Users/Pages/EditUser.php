<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Oltrematica\RoleLite\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<string>|null */
    protected ?array $roles = null;

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
        $this->roles = $data['roles'] ?? null;
        unset($data['roles']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            Role::query()->where('name', Roles::SuperAdmin->value)->lockForUpdate()->first();

            /** @var User $record */
            $record->refresh();
            auth()->user()?->unsetRelation('roles');
            Gate::authorize('update', $record);

            $currentRoles = $record->roles->pluck('name')->all();
            $newRoles = $this->roles ?? $currentRoles;

            foreach (array_diff($newRoles, $currentRoles) as $role) {
                Gate::authorize('assignRole', [$record, $role]);
            }

            foreach (array_diff($currentRoles, $newRoles) as $role) {
                Gate::authorize('removeRole', [$record, $role]);
            }

            parent::handleRecordUpdate($record, $data);

            if (array_diff($currentRoles, $newRoles) || array_diff($newRoles, $currentRoles)) {
                $record->syncRoles(...$newRoles);

                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'old_roles' => $currentRoles,
                        'new_roles' => $newRoles,
                    ])
                    ->log('Roles updated');
            }

            return $record;
        });
    }
}
