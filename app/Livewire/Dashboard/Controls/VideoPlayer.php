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

class VideoPlayer extends Component
{
    use AuthorizesRequests;

    public OnesiBox $onesiBox;

    #[Validate('required|url|max:2048')]
    public string $videoUrl = '';

    public function playVideo(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate();

        try {
            $commandService->sendVideoCommand($this->onesiBox, $this->videoUrl);
            Flux::toast('Comando video inviato con successo');
            $this->reset('videoUrl');
        } catch (OnesiBoxOfflineException) {
            Flux::toast('OnesiBox non raggiungibile', variant: 'danger');
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.video-player');
    }
}
