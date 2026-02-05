<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlaybackSessionStatus;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaybackSession>
 */
class PlaybackSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'onesi_box_id' => OnesiBox::factory(),
            'playlist_id' => Playlist::factory(),
            'status' => PlaybackSessionStatus::Active,
            'duration_minutes' => 60,
            'started_at' => now(),
            'ended_at' => null,
            'current_position' => 0,
            'items_played' => 0,
            'items_skipped' => 0,
        ];
    }

    /**
     * Indicate that the session is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlaybackSessionStatus::Active,
            'ended_at' => null,
        ]);
    }

    /**
     * Indicate that the session is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlaybackSessionStatus::Completed,
            'ended_at' => now(),
        ]);
    }

    /**
     * Indicate that the session was manually stopped.
     */
    public function stopped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlaybackSessionStatus::Stopped,
            'ended_at' => now(),
        ]);
    }

    /**
     * Indicate that the session ended with an error.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlaybackSessionStatus::Error,
            'ended_at' => now(),
        ]);
    }

    /**
     * Set a custom duration in minutes.
     */
    public function withDuration(int $minutes): static
    {
        return $this->state(fn (array $attributes): array => [
            'duration_minutes' => $minutes,
        ]);
    }

    /**
     * Set the OnesiBox for this session.
     */
    public function forOnesiBox(OnesiBox $onesiBox): static
    {
        return $this->state(fn (array $attributes): array => [
            'onesi_box_id' => $onesiBox->id,
        ]);
    }

    /**
     * Set the playlist for this session.
     */
    public function forPlaylist(Playlist $playlist): static
    {
        return $this->state(fn (array $attributes): array => [
            'playlist_id' => $playlist->id,
        ]);
    }
}
