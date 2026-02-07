<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaylistItem>
 */
class PlaylistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'playlist_id' => Playlist::factory(),
            'media_url' => 'https://www.jw.org/media/video/'.fake()->slug().'.mp4',
            'title' => null,
            'duration_seconds' => null,
            'position' => 0,
        ];
    }

    /**
     * Indicate that the item has a title.
     */
    public function withTitle(?string $title = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => $title ?? fake()->sentence(4),
        ]);
    }

    /**
     * Indicate that the item has a known duration.
     */
    public function withDuration(?int $seconds = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'duration_seconds' => $seconds ?? fake()->numberBetween(60, 3600),
        ]);
    }

    /**
     * Set the position of this item in the playlist.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes): array => [
            'position' => $position,
        ]);
    }
}
