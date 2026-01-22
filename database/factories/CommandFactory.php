<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Models\Command;
use App\Models\OnesiBox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Command>
 */
class CommandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(CommandType::cases());

        return [
            'uuid' => fake()->uuid(),
            'onesi_box_id' => OnesiBox::factory(),
            'type' => $type,
            'payload' => $this->generatePayloadForType($type),
            'priority' => fake()->numberBetween(1, 5),
            'status' => CommandStatus::Pending,
            'expires_at' => now()->addMinutes($type->defaultExpiresInMinutes()),
            'executed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the command is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommandStatus::Pending,
            'executed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the command is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommandStatus::Completed,
            'executed_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the command has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommandStatus::Failed,
            'executed_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
            'error_code' => 'E005',
            'error_message' => 'URL non raggiungibile',
        ]);
    }

    /**
     * Indicate that the command has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommandStatus::Expired,
            'expires_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
            'executed_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the command will expire in a given number of minutes.
     */
    public function expiresIn(int $minutes): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    /**
     * Indicate that the command has already expired but is still pending.
     */
    public function expiredButPending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommandStatus::Pending,
            'expires_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
            'executed_at' => null,
        ]);
    }

    /**
     * Set the command type.
     */
    public function ofType(CommandType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
            'payload' => $this->generatePayloadForType($type),
            'expires_at' => now()->addMinutes($type->defaultExpiresInMinutes()),
        ]);
    }

    /**
     * Set the command priority.
     */
    public function withPriority(int $priority): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => $priority,
        ]);
    }

    /**
     * Set the command payload.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function withPayload(?array $payload): static
    {
        return $this->state(fn (array $attributes): array => [
            'payload' => $payload,
        ]);
    }

    /**
     * Generate a payload for a specific command type.
     *
     * @return array<string, mixed>|null
     */
    private function generatePayloadForType(CommandType $type): ?array
    {
        return match ($type) {
            CommandType::PlayMedia => [
                'url' => 'https://www.jw.org/media/video/'.fake()->slug().'.mp4',
                'media_type' => fake()->randomElement(['audio', 'video']),
                'autoplay' => true,
            ],
            CommandType::SetVolume => [
                'level' => fake()->numberBetween(0, 100),
            ],
            CommandType::JoinZoom => [
                'meeting_url' => 'https://zoom.us/j/'.fake()->numerify('##########'),
                'meeting_id' => fake()->numerify('##########'),
                'password' => fake()->regexify('[A-Za-z0-9]{6}'),
            ],
            CommandType::StartJitsi => [
                'room_name' => fake()->slug(),
                'display_name' => fake()->name(),
            ],
            CommandType::SpeakText => [
                'text' => fake()->sentence(),
                'language' => 'it',
                'voice' => fake()->randomElement(['male', 'female']),
            ],
            CommandType::ShowMessage => [
                'title' => fake()->sentence(3),
                'body' => fake()->paragraph(),
                'duration' => fake()->numberBetween(5, 30),
            ],
            CommandType::Reboot, CommandType::Shutdown => [
                'delay' => fake()->numberBetween(0, 60),
            ],
            CommandType::StartVnc => [
                'server_host' => fake()->ipv4(),
                'server_port' => fake()->numberBetween(5900, 5999),
            ],
            CommandType::UpdateConfig => [
                'config_key' => fake()->slug(),
                'config_value' => fake()->word(),
            ],
            default => null,
        };
    }
}
