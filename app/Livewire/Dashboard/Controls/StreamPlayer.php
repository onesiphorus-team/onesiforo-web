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

    public function playFromStart(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate();

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStreamItemCommand(
                $this->onesiBox,
                $this->url,
                1
            ),
            successMessage: 'Playlist avviata'
        );

        $this->lastOrdinalSent = 1;
        $this->reachedEnd = false;
        $this->errorCode = null;
    }

    public function next(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        if ($this->reachedEnd || $this->lastOrdinalSent === null) {
            return;
        }

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStreamItemCommand(
                $this->onesiBox,
                $this->url,
                $this->lastOrdinalSent + 1
            ),
            successMessage: 'Prossimo video inviato'
        );

        $this->lastOrdinalSent++;
        $this->errorCode = null;
    }

    public function previous(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        if ($this->lastOrdinalSent === null || $this->lastOrdinalSent <= 1) {
            return;
        }

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStreamItemCommand(
                $this->onesiBox,
                $this->url,
                $this->lastOrdinalSent - 1
            ),
            successMessage: 'Video precedente inviato'
        );

        $this->lastOrdinalSent--;
        $this->reachedEnd = false;
        $this->errorCode = null;
    }

    public function stop(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStopCommand($this->onesiBox),
            successMessage: 'Riproduzione interrotta'
        );
    }

    /**
     * Echo listener per eventi di playback broadcast dal PlaybackController.
     *
     * @param  array{event: string, media_url: string, media_type: string, error_code?: string|null, error_message?: string|null, occurred_at?: string}  $payload
     */
    #[On('echo-private:appliance.{onesiBox.serial_number},.playback.event-received')]
    public function handlePlaybackEvent(array $payload): void
    {
        if ($payload['media_url'] !== $this->url) {
            return;
        }

        if ($payload['event'] !== 'error') {
            return;
        }

        $code = $payload['error_code'] ?? null;
        $this->errorCode = $code;

        if ($code === 'E112') {
            $this->reachedEnd = true;
        }
    }

    public function dismissError(): void
    {
        $this->errorCode = null;
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
            'url' => ['required', new JwStreamUrl],
        ];
    }
}
