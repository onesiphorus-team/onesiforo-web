<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\HandlesOnesiBoxErrors;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

class HeroCard extends Component
{
    use AuthorizesRequests;
    use HandlesOnesiBoxErrors;

    #[Locked]
    public OnesiBox $onesiBox;

    /** @var 'offline'|'call'|'media'|'idle' */
    #[Locked]
    public string $state = 'idle';

    #[Locked]
    public bool $isPaused = false;

    public function pause(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendPauseCommand($this->onesiBox),
            successMessage: 'Comando pausa inviato',
        );
    }

    public function resume(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendResumeCommand($this->onesiBox),
            successMessage: 'Comando ripresa inviato',
        );
    }

    public function stop(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStopCommand($this->onesiBox),
            successMessage: 'Riproduzione interrotta',
        );
    }

    public function leaveZoom(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendLeaveZoomCommand($this->onesiBox),
            successMessage: 'Chiamata terminata',
        );
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.hero-card');
    }
}
