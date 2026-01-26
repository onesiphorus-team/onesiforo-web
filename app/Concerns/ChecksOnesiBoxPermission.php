<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\OnesiBox;
use App\Models\User;
use Livewire\Attributes\Computed;

/**
 * Provides OnesiBox permission checking for Livewire components.
 *
 * This trait provides computed properties to check user permissions on an OnesiBox.
 * Requires the using class to have a public `$onesiBox` property of type OnesiBox.
 *
 * @property OnesiBox $onesiBox
 */
trait ChecksOnesiBoxPermission
{
    /**
     * Check if the current user has full control permission on this OnesiBox.
     *
     * Uses the model's userHasFullPermission method for consistent permission checking.
     */
    #[Computed]
    public function canControl(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $this->onesiBox->userHasFullPermission($user);
    }

    /**
     * Check if the current user can view this OnesiBox.
     *
     * Uses the model's userCanView method for consistent permission checking.
     */
    #[Computed]
    public function canView(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $this->onesiBox->userCanView($user);
    }
}
