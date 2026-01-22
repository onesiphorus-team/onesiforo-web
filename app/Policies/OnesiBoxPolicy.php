<?php

declare(strict_types=1);

namespace App\Policies;

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
     * (Out of scope per questa feature - solo admin via Filament)
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determina se l'utente può modificare questa OnesiBox.
     * (Out of scope per questa feature - solo admin via Filament)
     */
    public function update(User $user, OnesiBox $onesiBox): bool
    {
        return false;
    }

    /**
     * Determina se l'utente può eliminare questa OnesiBox.
     * (Out of scope per questa feature - solo admin via Filament)
     */
    public function delete(User $user, OnesiBox $onesiBox): bool
    {
        return false;
    }

    /**
     * Determina se l'utente può eliminare in bulk le OnesiBox.
     * (Out of scope per questa feature - solo admin via Filament)
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determina se l'utente può forzare l'eliminazione in bulk delle OnesiBox.
     * (Out of scope per questa feature - solo admin via Filament)
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
