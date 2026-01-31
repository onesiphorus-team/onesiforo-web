<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\HandlesOnesiBoxErrors;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ZoomCall extends Component
{
    use AuthorizesRequests;
    use HandlesOnesiBoxErrors;

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

        $success = $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendZoomUrlCommand(
                $this->onesiBox,
                $this->zoomUrl,
                $this->getParticipantName()
            ),
            successMessage: 'Connessione alla riunione Zoom in corso...'
        );

        if ($success) {
            $this->reset('zoomUrl');
        }
    }

    public function getParticipantName(): string
    {
        $recipient = $this->onesiBox->recipient;

        return $recipient !== null ? $recipient->full_name : $this->onesiBox->name;
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.zoom-call');
    }
}
