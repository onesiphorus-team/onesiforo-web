<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\Roles;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Livewire component for viewing OnesiBox logs.
 *
 * Allows administrators to request logs from the device and view them.
 */
class LogViewer extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public OnesiBox $onesiBox;

    #[Validate('required|integer|min:10|max:500')]
    public int $lines = 100;

    public ?string $logs = null;

    public bool $isLoading = false;

    public ?int $pendingCommandId = null;

    /**
     * Check if the current user is an admin.
     */
    #[Computed]
    public function isAdmin(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->hasAnyRoles(Roles::SuperAdmin, Roles::Admin);
    }

    /**
     * Check if the OnesiBox is online.
     */
    #[Computed]
    public function isOnline(): bool
    {
        return $this->onesiBox->isOnline();
    }

    /**
     * Request logs from the OnesiBox.
     */
    public function requestLogs(): void
    {
        if (! $this->isAdmin() || ! $this->isOnline()) {
            Flux::toast('Non autorizzato o dispositivo offline', variant: 'danger');

            return;
        }

        $this->authorize('control', $this->onesiBox);

        $this->validate();

        $this->isLoading = true;
        $this->logs = null;

        $command = Command::query()->create([
            'onesi_box_id' => $this->onesiBox->id,
            'type' => CommandType::GetLogs,
            'payload' => ['lines' => $this->lines],
            'status' => CommandStatus::Pending,
            'priority' => 2,
        ]);

        $this->pendingCommandId = $command->id;

        Flux::toast('Richiesta log inviata', variant: 'success');
    }

    /**
     * Handle real-time status update from Echo to check if logs are ready.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo-private:onesibox.{onesiBox.id},CommandAcknowledged')]
    public function handleCommandAcknowledged(array $payload): void
    {
        if ($this->pendingCommandId === null) {
            return;
        }

        // Check if this is our command
        if (($payload['command_id'] ?? null) !== $this->pendingCommandId) {
            return;
        }

        $command = Command::query()->find($this->pendingCommandId);
        if ($command === null) {
            $this->isLoading = false;
            $this->pendingCommandId = null;

            return;
        }

        $this->isLoading = false;

        if ($command->status === CommandStatus::Completed) {
            // Extract logs from the result
            $result = $command->result;
            $this->logs = $result['logs'] ?? 'Nessun log disponibile';
        } else {
            $this->logs = 'Errore nel recupero dei log: '.($command->error_message ?? 'Errore sconosciuto');
            Flux::toast('Errore nel recupero dei log', variant: 'danger');
        }

        $this->pendingCommandId = null;
    }

    /**
     * Clear the logs display.
     */
    public function clearLogs(): void
    {
        $this->logs = null;
        $this->pendingCommandId = null;
        $this->isLoading = false;
    }

    /**
     * Refresh command status manually (polling fallback).
     */
    public function checkCommandStatus(): void
    {
        if ($this->pendingCommandId === null) {
            return;
        }

        $command = Command::query()->find($this->pendingCommandId);
        if ($command === null) {
            $this->isLoading = false;
            $this->pendingCommandId = null;

            return;
        }

        if ($command->status !== CommandStatus::Pending) {
            $this->isLoading = false;

            if ($command->status === CommandStatus::Completed) {
                $result = $command->result;
                $this->logs = $result['logs'] ?? 'Nessun log disponibile';
            } else {
                $this->logs = 'Errore nel recupero dei log: '.($command->error_message ?? 'Errore sconosciuto');
            }

            $this->pendingCommandId = null;
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.log-viewer');
    }
}
