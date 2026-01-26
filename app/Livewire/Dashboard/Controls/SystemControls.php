<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\HandlesOnesiBoxErrors;
use App\Enums\Roles;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SystemControls extends Component
{
    use AuthorizesRequests;
    use HandlesOnesiBoxErrors;

    public OnesiBox $onesiBox;

    public bool $showRebootConfirm = false;

    public bool $showRestartServiceConfirm = false;

    /**
     * Check if the current user is an admin.
     */
    #[Computed]
    public function isAdmin(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin);
    }

    public function confirmReboot(): void
    {
        $this->showRebootConfirm = true;
    }

    public function cancelReboot(): void
    {
        $this->showRebootConfirm = false;
    }

    public function reboot(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        if (! $this->isAdmin()) {
            Flux::toast('Non autorizzato', variant: 'danger');

            return;
        }

        $success = $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendRebootCommand($this->onesiBox),
            successMessage: 'Comando di riavvio inviato'
        );

        if ($success) {
            $this->showRebootConfirm = false;
        }
    }

    public function confirmRestartService(): void
    {
        $this->showRestartServiceConfirm = true;
    }

    public function cancelRestartService(): void
    {
        $this->showRestartServiceConfirm = false;
    }

    public function restartService(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        if (! $this->isAdmin()) {
            Flux::toast('Non autorizzato', variant: 'danger');

            return;
        }

        $success = $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendRestartServiceCommand($this->onesiBox),
            successMessage: 'Comando di riavvio servizio inviato'
        );

        if ($success) {
            $this->showRestartServiceConfirm = false;
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.system-controls');
    }
}
