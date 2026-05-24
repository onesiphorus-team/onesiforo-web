<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CustomCommand;
use App\Models\OnesiBox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomCommand>
 */
class CustomCommandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onesi_box_id' => OnesiBox::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'script_name' => fake()->regexify('[a-z0-9-]{4,12}').'.sh',
            'static_args' => [],
            'icon' => fake()->randomElement(['heroicon-o-bolt', 'heroicon-o-tv', 'heroicon-o-power']),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_enabled' => false,
        ]);
    }

    public function forBox(OnesiBox $box): static
    {
        return $this->state(fn (array $attributes): array => [
            'onesi_box_id' => $box->id,
        ]);
    }
}
