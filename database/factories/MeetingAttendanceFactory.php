<?php

namespace Database\Factories;

use App\Enums\MeetingAttendanceStatus;
use App\Enums\MeetingJoinMode;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MeetingAttendance>
 */
class MeetingAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_instance_id' => MeetingInstance::factory(),
            'onesi_box_id' => OnesiBox::factory(),
            'join_mode' => MeetingJoinMode::Manual,
            'status' => MeetingAttendanceStatus::Pending,
        ];
    }

    public function auto(): static
    {
        return $this->state(fn () => ['join_mode' => MeetingJoinMode::Auto]);
    }

    public function joined(): static
    {
        return $this->state(fn () => [
            'status' => MeetingAttendanceStatus::Joined,
            'joined_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => MeetingAttendanceStatus::Completed,
            'joined_at' => now()->subHour(),
            'left_at' => now(),
        ]);
    }
}
