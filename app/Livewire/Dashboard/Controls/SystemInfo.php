<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire component for displaying OnesiBox system information.
 *
 * Shows CPU, memory, disk usage, temperature, and uptime.
 * Allows caregivers with full permission to request fresh data.
 */
class SystemInfo extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public OnesiBox $onesiBox;

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
     * Check if the OnesiBox is online.
     */
    #[Computed]
    public function isOnline(): bool
    {
        return $this->onesiBox->isOnline();
    }

    /**
     * Check if system info data is available.
     */
    #[Computed]
    public function hasSystemInfo(): bool
    {
        return $this->onesiBox->last_system_info_at !== null
            && ($this->onesiBox->cpu_usage !== null
                || $this->onesiBox->memory_usage !== null
                || $this->onesiBox->disk_usage !== null
                || $this->onesiBox->temperature !== null
                || $this->onesiBox->uptime !== null);
    }

    /**
     * Get formatted uptime string.
     */
    #[Computed]
    public function formattedUptime(): ?string
    {
        if ($this->onesiBox->uptime === null) {
            return null;
        }

        $seconds = $this->onesiBox->uptime;
        $days = (int) floor($seconds / 86400);
        $hours = (int) floor(($seconds % 86400) / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days.' '.($days === 1 ? 'giorno' : 'giorni');
        }
        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'ora' : 'ore');
        }
        if ($minutes > 0 && $days === 0) {
            $parts[] = $minutes.' '.($minutes === 1 ? 'minuto' : 'minuti');
        }

        return $parts === [] ? '< 1 minuto' : implode(', ', $parts);
    }

    /**
     * Request a system info refresh from the OnesiBox.
     */
    public function requestRefresh(): void
    {
        if (! $this->canControl() || ! $this->isOnline()) {
            return;
        }

        Command::query()->create([
            'onesi_box_id' => $this->onesiBox->id,
            'type' => CommandType::GetSystemInfo,
            'status' => CommandStatus::Pending,
            'priority' => 2,
        ]);

        $this->dispatch('notify', [
            'message' => __('Richiesta informazioni di sistema inviata'),
            'type' => 'success',
        ]);
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.system-info');
    }
}
