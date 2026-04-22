<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BottomBar extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    #[Locked]
    public OnesiBox $onesiBox;

    public bool $showVolume = false;

    #[Computed]
    public function isOnline(): bool
    {
        return $this->onesiBox->isOnline();
    }

    public function openVolume(): void
    {
        $this->showVolume = true;
    }

    public function stopAll(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: function () use ($commandService): void {
                $commandService->sendStopCommand($this->onesiBox);
                if ($this->onesiBox->status === OnesiBoxStatus::Calling) {
                    $commandService->sendLeaveZoomCommand($this->onesiBox);
                }
            },
            successMessage: 'Tutte le riproduzioni sono state interrotte.',
        );
    }

    public function openNew(): void
    {
        $this->dispatch('open-quick-play');
    }

    public function callAction(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        if ($this->onesiBox->status === OnesiBoxStatus::Calling) {
            $this->executeWithErrorHandling(
                callback: fn () => $commandService->sendLeaveZoomCommand($this->onesiBox),
                successMessage: 'Chiamata terminata',
            );

            return;
        }

        $this->dispatch('open-quick-play', tab: 'zoom');
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.bottom-bar');
    }
}
