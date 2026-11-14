<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Actions\Playlists\CreatePlaylistAction;
use App\Enums\PlaylistSourceType;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Services\JwOrgCatalog;
use InvalidArgumentException;

/**
 * Plays, in order, the JW.org media a caregiver queued up in the library browser.
 *
 * The session ends when the last item is over; its time limit is the total duration of
 * the queue plus a margin, so loading times and pauses do not cut the last items short.
 *
 * @phpstan-import-type JwMediaItem from JwOrgCatalog
 */
class StartQueuedPlaybackSessionAction
{
    public const int MAX_ITEMS = 100;

    public const int DURATION_MARGIN_MINUTES = 60;

    public function __construct(
        private readonly CreatePlaylistAction $createPlaylistAction,
        private readonly StartPlaybackSessionAction $startPlaybackSessionAction,
    ) {}

    /**
     * @param  'video'|'audio'  $mediaType
     * @param  list<JwMediaItem>  $items
     */
    public function execute(OnesiBox $onesiBox, string $mediaType, array $items): PlaybackSession
    {
        throw_if($items === [], InvalidArgumentException::class, 'The queue is empty');
        throw_if(count($items) > self::MAX_ITEMS, InvalidArgumentException::class, 'The queue has more than '.self::MAX_ITEMS.' items');

        $playlist = $this->createPlaylistAction->execute(
            $onesiBox,
            array_map(fn (array $item): array => [
                'url' => $item['url'],
                'title' => $item['title'],
                'duration_seconds' => $item['duration_seconds'],
            ], $items),
            PlaylistSourceType::Manual,
            name: $mediaType === 'audio' ? 'Playlist di audio' : 'Playlist di video',
        );

        $totalSeconds = array_sum(array_column($items, 'duration_seconds'));
        $durationMinutes = (int) ceil($totalSeconds / 60) + self::DURATION_MARGIN_MINUTES;

        return $this->startPlaybackSessionAction->execute($onesiBox, $playlist, $durationMinutes, $mediaType);
    }
}
