<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipient>
 */
class RecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->numerify('#####'),
            'province' => fake()->randomElement(['MI', 'RM', 'TO', 'NA', 'FI', 'BO', 'VE', 'PA', 'GE', 'BA']),
            'emergency_contacts' => null,
            'notes' => null,
        ];
    }

    /**
     * Set emergency contacts for the recipient.
     */
    public function withEmergencyContacts(): static
    {
        return $this->state(fn (array $attributes): array => [
            'emergency_contacts' => [
                [
                    'name' => fake()->name(),
                    'phone' => fake()->phoneNumber(),
                    'relationship' => fake()->randomElement(['Son', 'Daughter', 'Neighbor', 'Friend']),
                ],
            ],
        ]);
    }

    /**
     * Set notes for the recipient.
     */
    public function withNotes(): static
    {
        return $this->state(fn (array $attributes): array => [
            'notes' => fake()->sentence(),
        ]);
    }
}
