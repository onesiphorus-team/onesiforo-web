<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Oltrematica\RoleLite\Models\Role;

class DeleteUserAction
{
    public function __invoke(User $record, bool $force = false): bool
    {
        return DB::transaction(function () use ($record, $force): bool {
            Role::query()->where('name', Roles::SuperAdmin->value)->lockForUpdate()->first();

            $actor = User::query()->find(auth()->id());
            $record = User::withTrashed()->whereKey($record->getKey())->first();

            if (! $actor || ! $record || Gate::forUser($actor)->denies($force ? 'forceDelete' : 'delete', $record)) {
                return false;
            }

            return (bool) ($force ? $record->forceDelete() : $record->delete());
        });
    }
}
