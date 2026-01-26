<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\ChecksOnesiBoxPermission;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Command;
use App\Models\OnesiBox;
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
    use ChecksOnesiBoxPermission;

    #[Locked]
    public OnesiBox $onesiBox;

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
     * Check if detailed memory info is available.
     */
    #[Computed]
    public function hasDetailedMemory(): bool
    {
        return $this->onesiBox->memory_total !== null
            && $this->onesiBox->memory_used !== null;
    }

    /**
     * Format bytes to human-readable string.
     */
    public function formatBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return number_format($value, $i > 1 ? 1 : 0).' '.$units[$i];
    }

    /**
     * Get memory breakdown for the progress bar.
     *
     * @return array{used: float, buffers: float, cached: float, free: float}
     */
    #[Computed]
    public function memoryBreakdown(): array
    {
        $total = $this->onesiBox->memory_total ?? 1;
        $used = $this->onesiBox->memory_used ?? 0;
        $buffers = $this->onesiBox->memory_buffers ?? 0;
        $cached = $this->onesiBox->memory_cached ?? 0;

        // Calculate percentages
        // "Used" from OnesiBox includes buffers and cached, so we need to subtract
        $actualUsed = max(0, $used - $buffers - $cached);

        return [
            'used' => ($actualUsed / $total) * 100,
            'buffers' => ($buffers / $total) * 100,
            'cached' => ($cached / $total) * 100,
            'free' => 100 - (($actualUsed + $buffers + $cached) / $total) * 100,
        ];
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
