<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Roles;
use App\Models\ApplianceScreenshot;
use App\Models\User;

class ApplianceScreenshotPolicy
{
    public function view(User $user, ApplianceScreenshot $screenshot): bool
    {
        if ($user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin)) {
            return true;
        }

        $box = $screenshot->onesiBox;

        return $box !== null && $box->userCanView($user);
    }
}
