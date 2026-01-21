<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     * Admin and super-admin can view users list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRoles('super-admin', 'admin');
    }

    /**
     * Determine whether the user can view the model.
     * Admin and super-admin can view user details.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasAnyRoles('super-admin', 'admin');
    }

    /**
     * Determine whether the user can create models.
     * Admin and super-admin can invite/create users.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRoles('super-admin', 'admin');
    }

    /**
     * Determine whether the user can update the model.
     * Admin and super-admin can update users.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasAnyRoles('super-admin', 'admin');
    }

    /**
     * Determine whether the user can delete the model.
     * Only super-admin can soft-delete users.
     * Cannot delete self.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->hasRole('super-admin')) {
            return false;
        }

        return $user->id !== $model->id;
    }

    /**
     * Determine whether the user can bulk delete models.
     * Only super-admin can bulk delete users.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can restore the model.
     * Only super-admin can restore soft-deleted users.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Only super-admin can force-delete users.
     * Cannot delete self.
     * Cannot delete if this would leave no super-admin.
     */
    public function forceDelete(User $user, User $model): bool
    {
        if (! $user->hasRole('super-admin')) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        // Check if model is the last super-admin
        if ($model->hasRole('super-admin')) {
            $activeSuperAdminCount = User::query()->whereHas('roles', function ($query): void {
                $query->where('name', 'super-admin');
            })->whereNull('deleted_at')->count();

            if ($activeSuperAdminCount <= 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can bulk force delete models.
     * Only super-admin can bulk force delete users.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can assign roles to the model.
     * Super-admin can assign any role.
     * Admin can only assign caregiver role.
     */
    public function assignRole(User $user, User $model, string $role): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasRole('admin')) {
            return $role === 'caregiver';
        }

        return false;
    }

    /**
     * Determine whether the user can remove roles from the model.
     * Super-admin can remove any role (except if last super-admin).
     * Admin can only remove caregiver role.
     */
    public function removeRole(User $user, User $model, string $role): bool
    {
        if ($user->hasRole('super-admin')) {
            // Cannot remove super-admin role if last one
            if ($role === 'super-admin') {
                $activeSuperAdminCount = User::query()->whereHas('roles', function ($query): void {
                    $query->where('name', 'super-admin');
                })->whereNull('deleted_at')->count();

                return $activeSuperAdminCount > 1;
            }

            return true;
        }

        if ($user->hasRole('admin')) {
            return $role === 'caregiver';
        }

        return false;
    }
}
