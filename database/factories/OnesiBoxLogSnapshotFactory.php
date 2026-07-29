<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LogSnapshotSource;
use App\Models\OnesiBox;
use App\Models\OnesiBoxLogSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnesiBoxLogSnapshot>
 */
class OnesiBoxLogSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lines = array_map(
            fn (int $i): string => sprintf(
                '{"level":"info","message":"%s","timestamp":"%s"}',
                fake()->sentence(),
                now()->subMinutes($i)->toIso8601String(),
            ),
            range(1, 5),
        );

        return [
            'onesi_box_id' => OnesiBox::factory(),
            'command_id' => null,
            'source' => LogSnapshotSource::Scheduled,
            'captured_at' => now(),
            'log_file' => 'onesibox-'.now()->format('Y-m-d').'.log',
            'total_lines' => 500,
            'returned_lines' => count($lines),
            'lines' => $lines,
            'error_message' => null,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => LogSnapshotSource::Manual,
        ]);
    }

    public function failed(string $message = 'Failed to retrieve logs'): static
    {
        return $this->state(fn (array $attributes): array => [
            'lines' => [],
            'total_lines' => null,
            'returned_lines' => null,
            'log_file' => null,
            'error_message' => $message,
        ]);
    }
}
