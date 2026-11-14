<?php

declare(strict_types=1);

namespace App\Actions\Sessions;

use App\Actions\Playlists\CreatePlaylistAction;
use App\Enums\PlaylistSourceType;
use App\Exceptions\NoRandomMediaAvailableException;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Services\JwOrgCatalog;
use Illuminate\Support\Collection;

/**
 * Builds a playlist of JW.org media picked at random from the chosen categories and
 * starts a timed session with it.
 *
 * Media never shown on the OnesiBox come first (in random order), followed by the ones
 * shown least recently, so the beneficiary does not see the same content again too soon.
 *
 * @phpstan-import-type JwMediaItem from JwOrgCatalog
 */
class StartRandomPlaybackSessionAction
{
    /**
     * Without opting in, items longer than this are skipped (e.g. one-hour monthly programs).
     */
    public const int LONG_ITEM_THRESHOLD_SECONDS = 30 * 60;

    public const int MAX_ITEMS = 100;

    /**
     * Categories left out of a random session unless the caregiver selects them.
     *
     * @var list<string>
     */
    public const array DEFAULT_EXCLUDED_CATEGORIES = ['VODAudioDescriptions'];

    public function __construct(
        private readonly JwOrgCatalog $catalog,
        private readonly CreatePlaylistAction $createPlaylistAction,
        private readonly StartPlaybackSessionAction $startPlaybackSessionAction,
    ) {}

    /**
     * @param  'video'|'audio'  $mediaType
     * @param  list<string>  $categoryKeys
     *
     * @throws NoRandomMediaAvailableException When no media matches the criteria
     */
    public function execute(
        OnesiBox $onesiBox,
        string $mediaType,
        array $categoryKeys,
        int $durationMinutes,
        bool $includeLongItems = false,
    ): PlaybackSession {
        $selectedItems = $this->selectItems($onesiBox, $categoryKeys, $durationMinutes * 60, $includeLongItems);

        throw_if($selectedItems === [], NoRandomMediaAvailableException::class, 'No media available for the selected categories');

        $playlist = $this->createPlaylistAction->execute(
            $onesiBox,
            array_map(fn (array $item): array => [
                'url' => $item['url'],
                'title' => $item['title'],
                'duration_seconds' => $item['duration_seconds'],
            ], $selectedItems),
            PlaylistSourceType::JworgRandom,
            name: $mediaType === 'audio' ? 'Audio casuali' : 'Video casuali',
        );

        return $this->startPlaybackSessionAction->execute($onesiBox, $playlist, $durationMinutes, $mediaType);
    }

    /**
     * @param  list<string>  $categoryKeys
     * @return list<JwMediaItem>
     */
    private function selectItems(OnesiBox $onesiBox, array $categoryKeys, int $budgetSeconds, bool $includeLongItems): array
    {
        $maxItemSeconds = $includeLongItems ? $budgetSeconds : min($budgetSeconds, self::LONG_ITEM_THRESHOLD_SECONDS);
        $lastShownAt = $onesiBox->lastShownAtByMediaKey();

        /** @var Collection<int, JwMediaItem> $candidates */
        $candidates = collect($this->catalog->mediaInCategories($categoryKeys))
            ->filter(fn (array $item): bool => $item['duration_seconds'] > 0 && $item['duration_seconds'] <= $maxItemSeconds);

        [$alreadyShown, $neverShown] = $candidates->partition(fn (array $item): bool => isset($lastShownAt[$item['key']]));

        $orderedCandidates = $neverShown->shuffle()->concat(
            $alreadyShown->sortBy(fn (array $item): int => $lastShownAt[$item['key']]->getTimestamp())
        );

        $selectedItems = [];
        $totalSeconds = 0;

        foreach ($orderedCandidates as $item) {
            if ($totalSeconds >= $budgetSeconds || count($selectedItems) >= self::MAX_ITEMS) {
                break;
            }

            $selectedItems[] = $item;
            $totalSeconds += $item['duration_seconds'];
        }

        return $selectedItems;
    }
}
