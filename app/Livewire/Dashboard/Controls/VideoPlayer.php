<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Exceptions\OnesiBoxOfflineException;
use App\Models\OnesiBox;
use App\Rules\JwOrgUrl;
use App\Services\OnesiBoxCommandServiceInterface;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class VideoPlayer extends Component
{
    use AuthorizesRequests;

    public OnesiBox $onesiBox;

    public string $videoUrl = '';

    /**
     * Get the validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'videoUrl' => ['required', 'url', 'max:2048', new JwOrgUrl],
        ];
    }

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
