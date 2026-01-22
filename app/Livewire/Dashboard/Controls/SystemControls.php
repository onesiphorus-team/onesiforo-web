<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Enums\Roles;
use App\Exceptions\OnesiBoxOfflineException;
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

    public OnesiBox $onesiBox;

    public bool $showRebootConfirm = false;

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

        try {
            $commandService->sendRebootCommand($this->onesiBox);
            Flux::toast('Comando di riavvio inviato');
            $this->showRebootConfirm = false;
        } catch (OnesiBoxOfflineException) {
            Flux::toast('OnesiBox non raggiungibile', variant: 'danger');
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.system-controls');
    }
}
