<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\HandlesOnesiBoxErrors;
use App\Enums\CommandType;
use App\Enums\PlaybackEventType;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Rules\JwStreamUrl;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Livewire component per l'invio di comandi play_stream_item a un OnesiBox.
 *
 * Modello "self-limiting": la UI non conosce il numero di item della playlist —
 * reagisce all'evento error code E112 (ORDINAL_OUT_OF_RANGE) per disabilitare "Successivo".
 * Stato ricostruito al mount dalla tabella commands (ultimi 6 ore).
 */
class StreamPlayer extends Component
{
    use AuthorizesRequests;
    use HandlesOnesiBoxErrors;

    public OnesiBox $onesiBox;

    public string $url = '';

    public ?int $lastOrdinalSent = null;

    public ?string $errorCode = null;

    public bool $reachedEnd = false;

    public function mount(OnesiBox $onesiBox): void
    {
        $this->onesiBox = $onesiBox;

        $lastCommand = Command::query()
            ->where('onesi_box_id', $onesiBox->id)
            ->where('type', CommandType::PlayStreamItem)
            ->where('created_at', '>=', now()->subHours(6))
            ->latest()
            ->first();

        if ($lastCommand === null) {
            return;
        }

        $this->url = $lastCommand->payload['url'] ?? '';
        $this->lastOrdinalSent = $lastCommand->payload['ordinal'] ?? null;

        $lastEvent = PlaybackEvent::query()
            ->where('onesi_box_id', $onesiBox->id)
            ->where('media_url', $this->url)
            ->where('created_at', '>=', $lastCommand->created_at)
            ->orderByDesc('created_at')
            ->first();

        if ($lastEvent !== null && $lastEvent->event === PlaybackEventType::Error) {
            $this->errorCode = $lastEvent->error_code;
            if ($this->errorCode === 'E112') {
                $this->reachedEnd = true;
            }
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.stream-player');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'url' => ['required', new JwStreamUrl()],
        ];
    }
}
