<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Playlists\CreatePlaylistAction;
use App\Actions\Sessions\StartPlaybackSessionAction;
use App\Actions\Sessions\StopPlaybackSessionAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Livewire component for managing playback sessions.
 *
 * Allows caregivers to start/stop timed playlist sessions.
 */
class SessionManager extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    public OnesiBox $onesiBox;

    public int $durationMinutes = 60;

    /** @var list<string> */
    public array $videoUrls = [];

    /**
     * Available duration options in minutes.
     *
     * @var list<int>
     */
    public array $durationOptions = [30, 60, 120, 180];

    /**
     * Receive updated video URLs from PlaylistBuilder.
     *
     * @param  list<string>  $videoUrls
     */
    #[On('playlist-updated')]
    public function updateVideoUrls(array $videoUrls): void
    {
        $this->videoUrls = $videoUrls;
    }

    /**
     * Get the current active session, if any.
     */
    #[Computed]
    public function activeSession(): ?PlaybackSession
    {
        return $this->onesiBox->activeSession();
    }

    /**
     * Start a new playback session with the provided URLs.
     */
    public function startSession(
        CreatePlaylistAction $createPlaylistAction,
        StartPlaybackSessionAction $startAction,
    ): void {
        $this->authorize('control', $this->onesiBox);

        $this->validate([
            'videoUrls' => ['required', 'array', 'min:1', 'max:100'],
            'videoUrls.*' => ['required', 'url', 'max:2048'],
            'durationMinutes' => ['required', 'in:30,60,120,180'],
        ]);

        $this->executeWithErrorHandling(
            callback: function () use ($createPlaylistAction, $startAction): void {
                $playlist = $createPlaylistAction->executeFromUrls($this->onesiBox, $this->videoUrls);
                $startAction->execute($this->onesiBox, $playlist, $this->durationMinutes);
            },
            successMessage: 'Sessione avviata con successo',
        );

        $this->videoUrls = [];
    }

    /**
     * Stop the current active session.
     */
    public function stopSession(StopPlaybackSessionAction $stopAction): void
    {
        $this->authorize('control', $this->onesiBox);

        $session = $this->onesiBox->activeSession();

        if ($session === null) {
            return;
        }

        $this->executeWithErrorHandling(
            callback: fn (): PlaybackSession => $stopAction->execute($session),
            successMessage: 'Sessione interrotta',
        );
    }

    /**
     * Format duration for display.
     */
    public function formatDuration(int $minutes): string
    {
        if ($minutes >= 60) {
            $hours = intdiv($minutes, 60);
            $remainder = $minutes % 60;

            return $remainder > 0
                ? "{$hours}h {$remainder}m"
                : "{$hours}h";
        }

        return "{$minutes} min";
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.session-manager');
    }
}
