<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Roles;
use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use App\Models\User;

class ApplianceScreenshotPolicy
{
    public function view(User $user, ApplianceScreenshot $screenshot): bool
    {
        if ($user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin)) {
            return true;
        }

        /** @var OnesiBox|null $box */
        $box = $screenshot->onesiBox;

        return $box !== null && $box->userCanView($user);
    }

    /**
     * Diagnostic screenshots have no UI surface for bulk-delete; rows are
     * pruned exclusively by the onesibox:prune-screenshots command.
     * deleteAny / forceDeleteAny are implemented only to satisfy the
     * architecture rule (tests/Architecture/ArchTest.php).
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
