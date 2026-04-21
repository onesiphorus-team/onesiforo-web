<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingType;
use App\Models\Congregation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MeetingInstance>
 */
class MeetingInstanceFactory extends Factory
{
    public function definition(): array
    {
        $congregation = Congregation::factory();

        return [
            'congregation_id' => $congregation,
            'type' => MeetingType::Midweek,
            'scheduled_at' => now()->addHour(),
            'zoom_url' => 'https://us05web.zoom.us/j/'.$this->faker->numerify('##########'),
            'status' => MeetingInstanceStatus::Scheduled,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => MeetingInstanceStatus::Completed]);
    }

    public function notified(): static
    {
        return $this->state(fn (): array => ['status' => MeetingInstanceStatus::Notified]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => MeetingInstanceStatus::InProgress]);
    }
}
