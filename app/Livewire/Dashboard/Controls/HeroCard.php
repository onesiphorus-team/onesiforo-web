<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Actions\Sessions\StopPlaybackSessionAction;
use App\Concerns\ChecksOnesiBoxPermission;
use App\Concerns\HandlesOnesiBoxErrors;
use App\Enums\CommandStatus;
use App\Enums\OnesiBoxStatus;
use App\Enums\PlaybackEventType;
use App\Models\ApplianceScreenshot;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\PlaylistItem;
use App\Services\JwOrgCatalog;
use App\Services\OnesiBoxCommandServiceInterface;
use App\Support\JwMediaKey;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "What is happening on the box right now", at a glance: what is playing (with title,
 * thumbnail and time left), what comes next in a playlist, an ongoing call, the latest
 * picture of the box screen and the next congregation meeting.
 *
 * The card refreshes itself every few seconds, independently of the page around it.
 *
 * @phpstan-import-type JwMediaItem from JwOrgCatalog
 *
 * @property-read 'offline'|'call'|'media'|'idle' $state
 * @property-read bool $isPaused
 * @property-read PlaybackSession|null $activeSession
 * @property-read array<string, JwMediaItem> $jwMediaByKey
 * @property-read JwMediaItem|null $currentJwMedia
 * @property-read int|null $estimatedPosition
 * @property-read list<array{title: string, thumbnail_url: string|null, duration_seconds: int|null}> $upcomingItems
 * @property-read int $upcomingCount
 * @property-read int|null $sessionSecondsLeft
 * @property-read ApplianceScreenshot|null $latestScreenshot
 * @property-read array{label: string, scheduled_at: CarbonInterface}|null $nextMeeting
 * @property-read int $pendingCommandCount
 * @property-read bool $canControl
 */
class HeroCard extends Component
{
    use AuthorizesRequests;
    use ChecksOnesiBoxPermission;
    use HandlesOnesiBoxErrors;

    /**
     * How many of the next playlist items are previewed.
     */
    public const int UPCOMING_ITEMS = 3;

    #[Locked]
    public OnesiBox $onesiBox;

    /**
     * Playlist items after the current one, memoized for the current request.
     *
     * @var Collection<int, PlaylistItem>|null
     */
    private ?Collection $upcomingPlaylistItemsCache = null;

    /**
     * Reload the box state (called by the card's own poll and by real-time events).
     */
    #[On('echo-private:onesibox.{onesiBox.id},StatusUpdated')]
    public function refreshBox(): void
    {
        $this->onesiBox->refresh();
    }

    /**
     * @return 'offline'|'call'|'media'|'idle'
     */
    #[Computed]
    public function state(): string
    {
        if (! $this->onesiBox->isOnline()) {
            return 'offline';
        }

        if ($this->onesiBox->status === OnesiBoxStatus::Calling && $this->onesiBox->current_meeting_id !== null) {
            return 'call';
        }

        return $this->onesiBox->current_media_url !== null ? 'media' : 'idle';
    }

    #[Computed]
    public function isPaused(): bool
    {
        $latestEvent = $this->onesiBox->playbackEvents()->latest('id')->first();

        return $latestEvent?->event === PlaybackEventType::Paused
            && $latestEvent->media_url === $this->onesiBox->current_media_url;
    }

    /**
     * Commands sent to the box that it has not picked up yet.
     */
    #[Computed]
    public function pendingCommandCount(): int
    {
        return $this->onesiBox->commands()->where('status', CommandStatus::Pending)->count();
    }

    #[Computed]
    public function activeSession(): ?PlaybackSession
    {
        return $this->onesiBox->activeSession();
    }

    /**
     * Titles and thumbnails of the JW.org media shown on the card, fetched in one go.
     *
     * @return array<string, JwMediaItem>
     */
    #[Computed]
    public function jwMediaByKey(): array
    {
        $mediaUrls = [
            ...($this->state === 'media' ? [(string) $this->onesiBox->current_media_url] : []),
            ...$this->upcomingPlaylistItems()->take(self::UPCOMING_ITEMS)->pluck('media_url')->all(),
        ];

        $mediaKeys = array_values(array_filter(array_map(JwMediaKey::fromUrl(...), $mediaUrls)));

        return $mediaKeys === [] ? [] : resolve(JwOrgCatalog::class)->mediaItems($mediaKeys);
    }

    /**
     * @return JwMediaItem|null Null when nothing is playing, the URL is not a JW.org item or JW.org is unreachable
     */
    #[Computed]
    public function currentJwMedia(): ?array
    {
        return $this->state === 'media' ? $this->jwMediaFor((string) $this->onesiBox->current_media_url) : null;
    }

    /**
     * Playback position now, estimated from the last position the box reported.
     */
    #[Computed]
    public function estimatedPosition(): ?int
    {
        $position = $this->onesiBox->current_media_position;
        $duration = $this->onesiBox->current_media_duration;

        if ($position === null) {
            return null;
        }

        if (! $this->isPaused && $this->onesiBox->last_seen_at !== null) {
            $position += (int) max(0, $this->onesiBox->last_seen_at->diffInSeconds(now()));
        }

        return $duration !== null && $duration > 0 ? min($position, $duration) : $position;
    }

    /**
     * @return list<array{title: string, thumbnail_url: string|null, duration_seconds: int|null}>
     */
    #[Computed]
    public function upcomingItems(): array
    {
        return array_values($this->upcomingPlaylistItems()
            ->take(self::UPCOMING_ITEMS)
            ->map(function (PlaylistItem $item): array {
                $jwMedia = $this->jwMediaFor($item->media_url);

                return [
                    'title' => $item->title ?? $jwMedia['title'] ?? 'Contenuto successivo',
                    'thumbnail_url' => $jwMedia['thumbnail_url'] ?? null,
                    'duration_seconds' => $item->duration_seconds ?? $jwMedia['duration_seconds'] ?? null,
                ];
            })
            ->all());
    }

    #[Computed]
    public function upcomingCount(): int
    {
        return $this->upcomingPlaylistItems()->count();
    }

    /**
     * Time left in the playlist: the rest of the current item plus the following ones,
     * never beyond the session time limit.
     */
    #[Computed]
    public function sessionSecondsLeft(): ?int
    {
        $session = $this->activeSession;

        if ($session === null) {
            return null;
        }

        $duration = $this->onesiBox->current_media_duration;
        $currentLeft = $this->state === 'media' && $duration !== null
            ? max(0, $duration - (int) $this->estimatedPosition)
            : 0;

        $followingSeconds = (int) $this->upcomingPlaylistItems()->sum('duration_seconds');

        return min($currentLeft + $followingSeconds, $session->timeRemainingSeconds());
    }

    #[Computed]
    public function latestScreenshot(): ?ApplianceScreenshot
    {
        return $this->onesiBox->screenshots()->latest('captured_at')->first();
    }

    /**
     * @return array{label: string, scheduled_at: CarbonInterface}|null
     */
    #[Computed]
    public function nextMeeting(): ?array
    {
        $congregation = $this->onesiBox->recipient?->congregation;

        if ($congregation === null) {
            return null;
        }

        $meeting = $congregation->nextMeeting();

        return [
            'label' => 'Adunanza '.mb_strtolower($meeting['type']->getLabel()),
            'scheduled_at' => $meeting['scheduled_at'],
        ];
    }

    public function pause(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendPauseCommand($this->onesiBox),
            successMessage: 'Messo in pausa',
        );
    }

    public function resume(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendResumeCommand($this->onesiBox),
            successMessage: 'Riproduzione ripresa',
        );
    }

    /**
     * Stop what is playing; a running playlist is ended too, so it does not start the next item.
     */
    public function stop(OnesiBoxCommandServiceInterface $commandService, StopPlaybackSessionAction $stopSession): void
    {
        $this->authorize('control', $this->onesiBox);

        $session = $this->onesiBox->activeSession();

        $this->executeWithErrorHandling(
            callback: fn () => $session instanceof PlaybackSession
                ? $stopSession->execute($session)
                : $commandService->sendStopCommand($this->onesiBox),
            successMessage: $session instanceof PlaybackSession ? 'Playlist interrotta' : 'Riproduzione interrotta',
        );

        unset($this->activeSession);
    }

    public function leaveZoom(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendLeaveZoomCommand($this->onesiBox),
            successMessage: 'Chiamata terminata',
        );
    }

    /**
     * Format a duration for caregivers, e.g. "45 s", "12 min", "1 h 05 min".
     */
    public function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} s";
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes < 60
            ? "{$minutes} min"
            : sprintf('%d h %02d min', intdiv($minutes, 60), $minutes % 60);
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.hero-card', [
            'displayTimezone' => config('app.display_timezone', 'Europe/Rome'),
        ]);
    }

    /**
     * Playlist items after the one being played (memoized for the current request).
     *
     * @return Collection<int, PlaylistItem>
     */
    private function upcomingPlaylistItems(): Collection
    {
        $session = $this->activeSession;

        if ($session === null) {
            return collect();
        }

        return $this->upcomingPlaylistItemsCache ??= $session->playlist->items()
            ->where('position', '>', $session->current_position)
            ->orderBy('position')
            ->get()
            ->toBase();
    }

    /**
     * @return JwMediaItem|null
     */
    private function jwMediaFor(string $mediaUrl): ?array
    {
        $mediaKey = JwMediaKey::fromUrl($mediaUrl);

        return $mediaKey === null ? null : ($this->jwMediaByKey[$mediaKey] ?? null);
    }
}
