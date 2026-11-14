<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OnesiBox;
use App\Models\ZoomMeeting;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZoomMeeting>
 */
class ZoomMeetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'onesi_box_id' => OnesiBox::factory(),
            'name' => 'Adunanza '.fake()->city(),
            'zoom_url' => 'https://us05web.zoom.us/j/'.fake()->numerify('##########').'?pwd='.fake()->regexify('[A-Za-z0-9]{20}'),
            'last_used_at' => null,
        ];
    }

    public function usedAt(DateTimeInterface $usedAt): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_used_at' => $usedAt,
        ]);
    }
}
