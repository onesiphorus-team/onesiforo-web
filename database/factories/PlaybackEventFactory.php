<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlaybackEventType;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaybackEvent>
 */
class PlaybackEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $duration = fake()->numberBetween(60, 7200);
        $position = fake()->numberBetween(0, $duration);

        return [
            'onesi_box_id' => OnesiBox::factory(),
            'event' => fake()->randomElement(PlaybackEventType::cases()),
            'media_url' => 'https://www.jw.org/media/video/'.fake()->slug().'.mp4',
            'media_type' => fake()->randomElement(['audio', 'video']),
            'position' => $position,
            'duration' => $duration,
            'error_message' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the playback has started.
     */
    public function started(): static
    {
        return $this->state(fn (array $attributes): array => [
            'event' => PlaybackEventType::Started,
            'position' => 0,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the playback is paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'event' => PlaybackEventType::Paused,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the playback has resumed.
     */
    public function resumed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'event' => PlaybackEventType::Resumed,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the playback has stopped.
     */
    public function stopped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'event' => PlaybackEventType::Stopped,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the playback has completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes): array {
            $duration = $attributes['duration'] ?? fake()->numberBetween(60, 7200);

            return [
                'event' => PlaybackEventType::Completed,
                'position' => $duration,
                'duration' => $duration,
                'error_message' => null,
            ];
        });
    }

    /**
     * Indicate that the playback has an error.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes): array => [
            'event' => PlaybackEventType::Error,
            'error_message' => fake()->randomElement([
                'Codec video non supportato',
                'URL non raggiungibile',
                'Errore di rete',
                'File non trovato',
                'Timeout di connessione',
            ]),
        ]);
    }

    /**
     * Set the media type to audio.
     */
    public function audio(): static
    {
        return $this->state(fn (array $attributes): array => [
            'media_type' => 'audio',
            'media_url' => 'https://www.jw.org/media/audio/'.fake()->slug().'.mp3',
        ]);
    }

    /**
     * Set the media type to video.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes): array => [
            'media_type' => 'video',
            'media_url' => 'https://www.jw.org/media/video/'.fake()->slug().'.mp4',
        ]);
    }

    /**
     * Set the media URL.
     */
    public function withMediaUrl(string $url): static
    {
        return $this->state(fn (array $attributes): array => [
            'media_url' => $url,
        ]);
    }

    /**
     * Set the playback position.
     */
    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes): array => [
            'position' => $position,
        ]);
    }

    /**
     * Set the playback duration.
     */
    public function withDuration(int $duration): static
    {
        return $this->state(fn (array $attributes): array => [
            'duration' => $duration,
        ]);
    }

    /**
     * Set the created_at timestamp.
     */
    public function createdAt(DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => $date,
        ]);
    }
}
