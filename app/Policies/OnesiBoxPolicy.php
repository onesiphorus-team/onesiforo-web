<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Roles;
use App\Models\OnesiBox;
use App\Models\User;

class OnesiBoxPolicy
{
    /**
     * Determina se l'utente può visualizzare qualsiasi OnesiBox.
     * (Solo le sue assegnate, gestito via query scope)
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determina se l'utente può visualizzare questa OnesiBox.
     */
    public function view(User $user, OnesiBox $onesiBox): bool
    {
        return $onesiBox->userCanView($user);
    }

    /**
     * Determina se l'utente può inviare comandi a questa OnesiBox.
     * Richiede permesso "Full".
     */
    public function control(User $user, OnesiBox $onesiBox): bool
    {
        return $onesiBox->userHasFullPermission($user);
    }

    /**
     * Determina se l'utente può creare nuove OnesiBox.
     * Admin e super-admin possono creare OnesiBox via Filament.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin);
    }

    /**
     * Determina se l'utente può modificare questa OnesiBox.
     * Admin e super-admin possono modificare OnesiBox via Filament.
     */
    public function update(User $user, OnesiBox $onesiBox): bool
    {
        return $user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin);
    }

    /**
     * Determina se l'utente può eliminare questa OnesiBox.
     * Solo super-admin può eliminare OnesiBox.
     */
    public function delete(User $user, OnesiBox $onesiBox): bool
    {
        return $user->hasRole(Roles::SuperAdmin);
    }

    /**
     * Determina se l'utente può eliminare in bulk le OnesiBox.
     * Solo super-admin può eliminare in bulk.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasRole(Roles::SuperAdmin);
    }

    /**
     * Determina se l'utente può forzare l'eliminazione in bulk delle OnesiBox.
     * Solo super-admin può forzare l'eliminazione.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole(Roles::SuperAdmin);
    }
}
