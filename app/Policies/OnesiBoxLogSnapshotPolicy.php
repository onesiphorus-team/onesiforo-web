<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Roles;
use App\Models\OnesiBox;
use App\Models\OnesiBoxLogSnapshot;
use App\Models\User;

class OnesiBoxLogSnapshotPolicy
{
    /**
     * Log snapshots are always read through a specific OnesiBox, so viewing
     * them mirrors the visibility rules of the box they belong to.
     */
    public function view(User $user, OnesiBoxLogSnapshot $snapshot): bool
    {
        if ($user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin)) {
            return true;
        }

        /** @var OnesiBox|null $box */
        $box = $snapshot->onesiBox;

        return $box !== null && $box->userCanView($user);
    }

    /**
     * Snapshots have no bulk-delete UI surface; rows are pruned exclusively by
     * the onesibox:prune-log-snapshots command. deleteAny / forceDeleteAny are
     * implemented only to satisfy the architecture rule
     * (tests/Architecture/ArchTest.php).
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasRole(Roles::SuperAdmin);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole(Roles::SuperAdmin);
    }
}
