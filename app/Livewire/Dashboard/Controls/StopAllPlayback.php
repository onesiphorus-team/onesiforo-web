<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Enums\OnesiBoxStatus;
use App\Exceptions\OnesiBoxOfflineException;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StopAllPlayback extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public OnesiBox $onesiBox;

    public bool $showConfirmation = false;

    public function confirmStop(): void
    {
        $this->showConfirmation = true;
    }

    public function cancelStop(): void
    {
        $this->showConfirmation = false;
    }

    public function stopAll(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        try {
            // Send stop media command (works for both audio and video)
            $commandService->sendStopCommand($this->onesiBox);

            // If in a Zoom call, also send leave command
            if ($this->onesiBox->status === OnesiBoxStatus::Calling) {
                $commandService->sendLeaveZoomCommand($this->onesiBox);
            }

            Flux::toast('Tutte le riproduzioni sono state interrotte.');
        } catch (OnesiBoxOfflineException) {
            Flux::toast('OnesiBox non raggiungibile', variant: 'danger');
        }

        $this->showConfirmation = false;
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.stop-all-playback');
    }
}
