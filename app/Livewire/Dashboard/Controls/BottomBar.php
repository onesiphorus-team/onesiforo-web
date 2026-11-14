<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Sessions\StopPlaybackSessionAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Enums\OnesiBoxStatus;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
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

    #[Computed]
    public function isInCall(): bool
    {
        return $this->onesiBox->status === OnesiBoxStatus::Calling;
    }

    /**
     * Whether something is running on the box (a media, a playlist or a call): only then
     * the stop button is shown in red.
     */
    #[Computed]
    public function hasSomethingToStop(): bool
    {
        if (! $this->onesiBox->isOnline()) {
            return false;
        }

        return $this->onesiBox->current_media_url !== null
            || $this->onesiBox->status === OnesiBoxStatus::Calling
            || $this->onesiBox->activeSession() instanceof PlaybackSession;
    }

    #[Computed]
    public function currentVolume(): int
    {
        return (int) ($this->onesiBox->volume ?? 80);
    }

    /**
     * Show the bar again when the box went online or offline, a call or a media started or
     * ended, or the volume changed (see AnnouncesOnesiBoxState: the status card and the page poll the box and announce it).
     *
     * Nothing to reload here: Livewire fetches the box again on every request.
     */
    #[On('box-state-changed')]
    public function refreshBox(): void {}

    public function openVolume(): void
    {
        $this->showVolume = true;
    }

    /**
     * Stop everything: the media being played (ending a running playlist, so it does not
     * start the next item) and an ongoing call.
     */
    public function stopAll(OnesiBoxCommandServiceInterface $commandService, StopPlaybackSessionAction $stopSession): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: function () use ($commandService, $stopSession): void {
                $session = $this->onesiBox->activeSession();

                if ($session instanceof PlaybackSession) {
                    $stopSession->execute($session);
                } else {
                    $commandService->sendStopCommand($this->onesiBox);
                }

                if ($this->onesiBox->status === OnesiBoxStatus::Calling) {
                    $commandService->sendLeaveZoomCommand($this->onesiBox);
                }
            },
            successMessage: 'Tutte le riproduzioni sono state interrotte.',
        );
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
