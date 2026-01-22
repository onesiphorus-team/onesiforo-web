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

class AudioPlayer extends Component
{
    use AuthorizesRequests;

    public OnesiBox $onesiBox;

    #[Validate('required|url|max:2048')]
    public string $audioUrl = '';

    public function playAudio(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate();

        try {
            $commandService->sendAudioCommand($this->onesiBox, $this->audioUrl);
            Flux::toast('Comando audio inviato con successo');
            $this->reset('audioUrl');
        } catch (OnesiBoxOfflineException) {
            Flux::toast('OnesiBox non raggiungibile', variant: 'danger');
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.audio-player');
    }
}
