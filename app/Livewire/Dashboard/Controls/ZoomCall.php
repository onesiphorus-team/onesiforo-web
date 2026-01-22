<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Exceptions\OnesiBoxOfflineException;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ZoomCall extends Component
{
    use AuthorizesRequests;

    public OnesiBox $onesiBox;

    #[Validate('required|regex:/^\d{9,11}$/')]
    public string $meetingId = '';

    #[Validate('nullable|max:10')]
    public string $password = '';

    public function startCall(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate();

        try {
            $commandService->sendZoomCommand(
                $this->onesiBox,
                $this->meetingId,
                $this->password ?: null
            );
            Flux::toast('Chiamata Zoom avviata');
            $this->reset(['meetingId', 'password']);
        } catch (OnesiBoxOfflineException) {
            Flux::toast('OnesiBox non raggiungibile', variant: 'danger');
        }
    }

    public function endCall(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        try {
            $commandService->sendStopCommand($this->onesiBox);
            Flux::toast('Comando di stop inviato');
        } catch (OnesiBoxOfflineException) {
            Flux::toast('OnesiBox non raggiungibile', variant: 'danger');
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.zoom-call');
    }
}
