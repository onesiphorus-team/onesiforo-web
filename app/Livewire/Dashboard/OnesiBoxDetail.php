<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Concerns\ChecksOnesiBoxPermission;
use App\Enums\CommandStatus;
use App\Enums\OnesiBoxPermission;
use App\Enums\Roles;
use App\Models\OnesiBox;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dettaglio OnesiBox')]
class OnesiBoxDetail extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;

    public OnesiBox $onesiBox;

    public bool $showTechnicalDetails = false;

    public function mount(OnesiBox $onesiBox): void
    {
        $this->authorize('view', $onesiBox);
        $this->onesiBox = $onesiBox->load('recipient');
    }

    /**
     * Get the recipient associated with the OnesiBox.
     */
    #[Computed]
    public function recipient(): ?Recipient
    {
        return $this->onesiBox->recipient;
    }

    /**
     * Get the permission level of the current user.
     */
    #[Computed]
    public function permission(): ?OnesiBoxPermission
    {
        /** @var User $user */
        $user = auth()->user();

        $pivot = $this->onesiBox->caregivers()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot;

        if ($pivot === null) {
            return null;
        }

        /** @var OnesiBoxPermission|null $permission */
        $permission = $pivot->getAttribute('permission');

        return $permission;
    }

    /**
     * Check if the OnesiBox is online.
     */
    #[Computed]
    public function isOnline(): bool
    {
        return $this->onesiBox->isOnline();
    }

    /**
     * Check if the current user is an admin.
     */
    #[Computed]
    public function isAdmin(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin);
    }

    /**
     * Whether "back to my boxes" leads anywhere: a family member with a single box is sent
     * straight back here by the list.
     */
    #[Computed]
    public function canGoBackToList(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $this->isAdmin() || $user->onesiBoxes()->limit(2)->count() > 1;
    }

    /**
     * Commands sent to the box that it has not picked up yet (shown next to "Altro").
     */
    #[Computed]
    public function pendingCommandCount(): int
    {
        return $this->onesiBox->commands()->where('status', CommandStatus::Pending)->count();
    }

    /**
     * Show or hide the technical details (command queue, system, network, logs).
     *
     * They are only rendered while open, so their components do not poll in the background.
     */
    public function toggleTechnicalDetails(): void
    {
        $this->showTechnicalDetails = ! $this->showTechnicalDetails;
    }

    /**
     * Handle real-time status update from Echo.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo-private:onesibox.{onesiBox.id},StatusUpdated')]
    public function refreshStatus(array $payload): void
    {
        $this->onesiBox->refresh();
    }

    /**
     * Refresh model data from the database (called by wire:poll).
     */
    public function refreshFromDatabase(): void
    {
        $this->onesiBox->refresh();
    }

    /**
     * Navigate back to the OnesiBox list.
     */
    public function goBack(): void
    {
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.dashboard.onesi-box-detail');
    }
}
