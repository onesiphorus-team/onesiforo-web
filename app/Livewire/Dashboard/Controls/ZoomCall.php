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

    #[Validate([
        'zoomUrl' => [
            'required',
            'url',
            'regex:/^https:\/\/[a-z0-9]+\.zoom\.us\/j\/[0-9]+(\?pwd=.+)?$/i',
        ],
    ], message: [
        'zoomUrl.required' => 'Il link Zoom è obbligatorio.',
        'zoomUrl.url' => 'Inserisci un URL valido.',
        'zoomUrl.regex' => 'Il link deve essere un URL Zoom valido (es: https://us05web.zoom.us/j/123456789?pwd=...)',
    ])]
    public string $zoomUrl = '';

    public function startCall(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate();

        try {
            $commandService->sendZoomUrlCommand(
                $this->onesiBox,
                $this->zoomUrl,
                'Rosa Iannascoli'
            );
            Flux::toast('Connessione alla riunione Zoom in corso...');
            $this->reset('zoomUrl');
        } catch (OnesiBoxOfflineException) {
            Flux::toast('OnesiBox non raggiungibile', variant: 'danger');
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.zoom-call');
    }
}
