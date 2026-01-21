<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OnesiBox;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnesiBox>
 */
class OnesiBoxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'OnesiBox-'.fake()->unique()->numerify('###'),
            'serial_number' => 'OB-'.fake()->unique()->uuid(),
            'recipient_id' => null,
            'firmware_version' => '1.'.fake()->numberBetween(0, 9).'.'.fake()->numberBetween(0, 9),
            'last_seen_at' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the OnesiBox is assigned to a recipient.
     */
    public function forRecipient(?Recipient $recipient = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'recipient_id' => $recipient?->id ?? Recipient::factory(),
        ]);
    }

    /**
     * Indicate that the OnesiBox is online (recently seen).
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_seen_at' => now()->subSeconds(fake()->numberBetween(1, 60)),
        ]);
    }

    /**
     * Indicate that the OnesiBox is offline (not seen for a while).
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(10, 60)),
        ]);
    }

    /**
     * Indicate that the OnesiBox has never been seen.
     */
    public function neverSeen(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_seen_at' => null,
        ]);
    }

    /**
     * Indicate that the OnesiBox is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
