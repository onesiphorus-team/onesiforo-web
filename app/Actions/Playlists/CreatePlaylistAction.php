<?php

declare(strict_types=1);

namespace App\Actions\Playlists;

use App\Enums\PlaylistSourceType;
use App\Models\OnesiBox;
use App\Models\Playlist;

/**
 * Creates a playlist with items for an OnesiBox.
 */
class CreatePlaylistAction
{
    /**
     * Create a playlist from a simple list of URLs.
     *
     * @param  list<string>  $urls
     */
    public function executeFromUrls(
        OnesiBox $onesiBox,
        array $urls,
        PlaylistSourceType $sourceType = PlaylistSourceType::Manual,
        ?string $sourceUrl = null,
        ?string $name = null,
        bool $isSaved = false,
    ): Playlist {
        $videos = array_map(fn (string $url): array => ['url' => $url], $urls);

        return $this->execute($onesiBox, $videos, $sourceType, $sourceUrl, $name, $isSaved);
    }

    /**
     * Execute the action to create a playlist.
     *
     * @param  array<int, array{url: string, title?: string|null, duration_seconds?: int|null}>  $videos
     */
    public function execute(
        OnesiBox $onesiBox,
        array $videos,
        PlaylistSourceType $sourceType = PlaylistSourceType::Manual,
        ?string $sourceUrl = null,
        ?string $name = null,
        bool $isSaved = false,
    ): Playlist {
        $playlist = Playlist::query()->create([
            'onesi_box_id' => $onesiBox->id,
            'name' => $name,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'is_saved' => $isSaved,
        ]);

        $playlist->items()->createMany(
            collect($videos)->map(fn (array $video, int $position): array => [
                'media_url' => $video['url'],
                'title' => $video['title'] ?? null,
                'duration_seconds' => $video['duration_seconds'] ?? null,
                'position' => $position,
            ])->all()
        );

        return $playlist;
    }
}
