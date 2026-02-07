<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\ChecksOnesiBoxPermission;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\PlaylistItem;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Livewire component for displaying real-time session status.
 *
 * Visible to all caregivers (including read-only) for monitoring active sessions.
 *
 * @property-read PlaybackSession|null $activeSession
 * @property-read bool $hasActiveSession
 * @property-read int $timeRemainingSeconds
 * @property-read PlaylistItem|null $currentVideo
 * @property-read int $totalItems
 * @property-read int $progressPercent
 */
class SessionStatus extends Component
{
    use ChecksOnesiBoxPermission;

    public OnesiBox $onesiBox;

    /**
     * Get the current active session, if any.
     */
    #[Computed]
    public function activeSession(): ?PlaybackSession
    {
        return $this->onesiBox->activeSession();
    }

    /**
     * Check if there is an active session.
     */
    #[Computed]
    public function hasActiveSession(): bool
    {
        return $this->activeSession !== null;
    }

    /**
     * Get the time remaining in seconds.
     */
    #[Computed]
    public function timeRemainingSeconds(): int
    {
        return $this->activeSession?->timeRemainingSeconds() ?? 0;
    }

    /**
     * Get the current playlist item being played.
     */
    #[Computed]
    public function currentVideo(): ?PlaylistItem
    {
        return $this->activeSession?->currentItem();
    }

    /**
     * Get the total number of items in the playlist.
     */
    #[Computed]
    public function totalItems(): int
    {
        return $this->activeSession?->playlist->items()->count() ?? 0;
    }

    /**
     * Format seconds into a human-readable string.
     */
    public function formatTimeRemaining(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'Scaduta';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        return sprintf('%dm %02ds', $minutes, $secs);
    }

    /**
     * Calculate progress percentage.
     */
    #[Computed]
    public function progressPercent(): int
    {
        $total = $this->totalItems;
        if ($total === 0) {
            return 0;
        }

        $session = $this->activeSession;
        if ($session === null) {
            return 0;
        }

        return (int) round(($session->items_played / $total) * 100);
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.session-status');
    }
}
