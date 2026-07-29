<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\LogSnapshotSource;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\ApplianceScreenshot;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\OnesiBoxLogSnapshot;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Read-only diagnostics panel embedded in the OnesiBox edit screen.
 *
 * Gathers, in one place, everything an operator needs before deciding whether
 * to touch a device: the telemetry already shown on the dashboard widget, when
 * the box last talked to the server, its most recent screen captures, and the
 * periodically collected application logs.
 *
 * @property-read bool $isOnline
 * @property-read Collection<int, ApplianceScreenshot> $screenshots
 * @property-read Collection<int, OnesiBoxLogSnapshot> $logSnapshots
 * @property-read OnesiBoxLogSnapshot|null $selectedSnapshot
 * @property-read Collection<int, Command> $recentCommands
 * @property-read int $pendingCommandsCount
 * @property-read bool $hasPendingLogRequest
 * @property-read array<int, array{label: string, at: CarbonInterface|null, hint: string|null}> $contacts
 * @property-read string|null $formattedUptime
 */
class BoxDiagnosticsPanel extends Component
{
    /**
     * Number of screen captures shown in the strip.
     */
    private const int SCREENSHOT_LIMIT = 6;

    /**
     * Number of log captures listed before the operator has to open the
     * dedicated log history.
     */
    private const int SNAPSHOT_LIMIT = 8;

    private const int COMMAND_LIMIT = 5;

    #[Locked]
    public OnesiBox $record;

    #[Locked]
    public ?int $selectedSnapshotId = null;

    public function mount(OnesiBox $record): void
    {
        $this->record = $record;
    }

    #[Computed]
    public function isOnline(): bool
    {
        return $this->record->isOnline();
    }

    /**
     * @return Collection<int, ApplianceScreenshot>
     */
    #[Computed]
    public function screenshots(): Collection
    {
        return $this->record->screenshots()
            ->latest('captured_at')
            ->limit(self::SCREENSHOT_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, OnesiBoxLogSnapshot>
     */
    #[Computed]
    public function logSnapshots(): Collection
    {
        return $this->record->logSnapshots()
            ->latestFirst()
            ->limit(self::SNAPSHOT_LIMIT)
            ->get();
    }

    #[Computed]
    public function selectedSnapshot(): ?OnesiBoxLogSnapshot
    {
        if ($this->selectedSnapshotId === null) {
            return $this->logSnapshots->first();
        }

        return $this->logSnapshots->firstWhere('id', $this->selectedSnapshotId)
            ?? $this->record->logSnapshots()->whereKey($this->selectedSnapshotId)->first();
    }

    /**
     * @return Collection<int, Command>
     */
    #[Computed]
    public function recentCommands(): Collection
    {
        return $this->record->commands()
            ->latest('id')
            ->limit(self::COMMAND_LIMIT)
            ->get();
    }

    #[Computed]
    public function pendingCommandsCount(): int
    {
        return $this->record->pendingCommands()->count();
    }

    #[Computed]
    public function hasPendingLogRequest(): bool
    {
        return $this->record->commands()
            ->pending()
            ->where('type', CommandType::GetLogs)
            ->exists();
    }

    /**
     * Timeline of the last inbound signals, most useful first.
     *
     * Every entry is a moment the appliance proved it was alive in some way,
     * which is what "ultimi contatti" means for an operator triaging a box.
     *
     * @return array<int, array{label: string, at: CarbonInterface|null, hint: string|null}>
     */
    #[Computed]
    public function contacts(): array
    {
        $lastPlayback = $this->record->playbackEvents()->latest('id')->first();
        $lastExecutedCommand = $this->record->commands()
            ->whereNotNull('executed_at')
            ->latest('executed_at')
            ->first();

        return [
            $this->contact(
                __('Ultimo heartbeat'),
                $this->record->last_seen_at,
                __('Il dispositivo è considerato offline dopo 5 minuti di silenzio.'),
            ),
            $this->contact(__('Ultime info di sistema'), $this->record->last_system_info_at),
            $this->contact(__('Ultima schermata'), $this->screenshots->first()?->captured_at),
            $this->contact(__('Ultimo log ricevuto'), $this->logSnapshots->first()?->captured_at),
            $this->contact(__('Ultima richiesta log inviata'), $this->record->last_log_requested_at),
            $this->contact(
                __('Ultimo comando eseguito'),
                $lastExecutedCommand?->executed_at,
                $lastExecutedCommand?->type->getLabel(),
            ),
            $this->contact(
                __('Ultimo evento di riproduzione'),
                $lastPlayback?->created_at,
                $lastPlayback?->event->getLabel(),
            ),
        ];
    }

    /**
     * Human-readable uptime, mirroring the caregiver dashboard formatting.
     */
    #[Computed]
    public function formattedUptime(): ?string
    {
        $seconds = $this->record->uptime;

        if ($seconds === null) {
            return null;
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.' '.($days === 1 ? __('giorno') : __('giorni'));
        }
        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? __('ora') : __('ore'));
        }
        if ($minutes > 0 && $days === 0) {
            $parts[] = $minutes.' '.($minutes === 1 ? __('minuto') : __('minuti'));
        }

        return $parts === [] ? __('< 1 minuto') : implode(', ', $parts);
    }

    public function selectSnapshot(int $id): void
    {
        $this->selectedSnapshotId = $id;
    }

    /**
     * Ask the appliance for a fresh log capture right now.
     *
     * The answer arrives asynchronously through the command acknowledgement
     * endpoint, which is what persists the snapshot.
     */
    public function requestLogs(): void
    {
        Gate::authorize('update', $this->record);

        if (! $this->record->isOnline()) {
            Notification::make()
                ->title(__('Dispositivo offline'))
                ->body(__('Impossibile richiedere i log: la OnesiBox non è raggiungibile.'))
                ->danger()
                ->send();

            return;
        }

        if ($this->hasPendingLogRequest) {
            Notification::make()
                ->title(__('Richiesta già in corso'))
                ->body(__('Una richiesta log è già in attesa di risposta.'))
                ->warning()
                ->send();

            return;
        }

        $command = Command::query()->create([
            'onesi_box_id' => $this->record->id,
            'type' => CommandType::GetLogs,
            'payload' => [
                'lines' => $this->record->log_collection_lines,
                'source' => LogSnapshotSource::Manual->value,
            ],
            'status' => CommandStatus::Pending,
            'priority' => 2,
        ]);

        dispatch(new SendOnesiBoxCommand($command));

        $this->refreshDiagnostics();

        Notification::make()
            ->title(__('Richiesta log inviata'))
            ->body(__('I log compariranno qui appena la OnesiBox risponde.'))
            ->success()
            ->send();
    }

    #[On('echo-private:onesibox.{record.id},StatusUpdated')]
    #[On('echo-private:onesibox.{record.id},ApplianceScreenshotReceived')]
    public function refreshDiagnostics(): void
    {
        $this->record->refresh();

        unset(
            $this->isOnline,
            $this->screenshots,
            $this->logSnapshots,
            $this->selectedSnapshot,
            $this->recentCommands,
            $this->pendingCommandsCount,
            $this->hasPendingLogRequest,
            $this->contacts,
            $this->formattedUptime,
        );
    }

    public function render(): View
    {
        return view('livewire.filament.box-diagnostics-panel');
    }

    /**
     * @return array{label: string, at: CarbonInterface|null, hint: string|null}
     */
    private function contact(string $label, ?CarbonInterface $at, ?string $hint = null): array
    {
        return ['label' => $label, 'at' => $at, 'hint' => $hint];
    }
}
