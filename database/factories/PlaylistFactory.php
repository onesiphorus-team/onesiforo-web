<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlaylistSourceType;
use App\Models\OnesiBox;
use App\Models\Playlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Playlist>
 */
class PlaylistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onesi_box_id' => OnesiBox::factory(),
            'name' => null,
            'source_type' => PlaylistSourceType::Manual,
            'source_url' => null,
            'is_saved' => false,
        ];
    }

    /**
     * Indicate that the playlist is saved.
     */
    public function saved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_saved' => true,
            'name' => fake()->sentence(3),
        ]);
    }

    /**
     * Indicate that the playlist is from a JW.org section.
     */
    public function jworgSection(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source_type' => PlaylistSourceType::JworgSection,
            'source_url' => 'https://www.jw.org/it/biblioteca/video/#it/categories/VODStudio',
        ]);
    }

    /**
     * Set the OnesiBox for this playlist.
     */
    public function forOnesiBox(OnesiBox $onesiBox): static
    {
        return $this->state(fn (array $attributes): array => [
            'onesi_box_id' => $onesiBox->id,
        ]);
    }
}
