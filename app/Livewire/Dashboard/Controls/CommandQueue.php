<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Commands\CancelCommandAction;
use App\Enums\OnesiBoxPermission;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire component for viewing and managing the command queue.
 *
 * Allows caregivers to view pending commands and cancel them.
 *
 * @property-read Collection<int, Command> $pendingCommands
 * @property-read bool $canControl
 */
class CommandQueue extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public OnesiBox $onesiBox;

    public bool $showCancelAllConfirmation = false;

    /**
     * Get pending commands for this OnesiBox.
     *
     * @return Collection<int, Command>
     */
    #[Computed]
    public function pendingCommands(): Collection
    {
        return $this->onesiBox->pendingCommands()
            ->orderByPriority()
            ->get();
    }

    /**
     * Check if the current user can control this OnesiBox.
     */
    #[Computed]
    public function canControl(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        $pivot = $this->onesiBox->caregivers()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot;

        if ($pivot === null) {
            return false;
        }

        /** @var OnesiBoxPermission|null $permission */
        $permission = $pivot->getAttribute('permission');

        return $permission === OnesiBoxPermission::Full;
    }

    /**
     * Cancel a single command.
     */
    public function cancelCommand(string $uuid): void
    {
        if (! $this->canControl()) {
            return;
        }

        $command = Command::query()->where('uuid', $uuid)
            ->where('onesi_box_id', $this->onesiBox->id)
            ->first();

        if ($command === null) {
            return;
        }

        $action = new CancelCommandAction;
        $result = $action->execute($command);

        if ($result) {
            $this->dispatch('notify', [
                'message' => __('Comando annullato'),
                'type' => 'success',
            ]);
        }

        // Clear the computed property cache
        unset($this->pendingCommands);
    }

    /**
     * Show confirmation dialog for cancelling all commands.
     */
    public function confirmCancelAll(): void
    {
        $this->showCancelAllConfirmation = true;
    }

    /**
     * Hide confirmation dialog.
     */
    public function cancelCancelAll(): void
    {
        $this->showCancelAllConfirmation = false;
    }

    /**
     * Cancel all pending commands for this OnesiBox.
     */
    public function cancelAll(): void
    {
        if (! $this->canControl()) {
            return;
        }

        $action = new CancelCommandAction;
        $cancelledCount = 0;

        foreach ($this->pendingCommands as $command) {
            if ($action->execute($command)) {
                $cancelledCount++;
            }
        }

        if ($cancelledCount > 0) {
            $this->dispatch('notify', [
                'message' => __(':count comandi annullati', ['count' => $cancelledCount]),
                'type' => 'success',
            ]);
        }

        $this->showCancelAllConfirmation = false;

        // Clear the computed property cache
        unset($this->pendingCommands);
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.command-queue');
    }
}
