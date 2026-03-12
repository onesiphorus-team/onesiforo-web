<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Congregation>
 */
class CongregationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Congregazione '.$this->faker->city(),
            'zoom_url' => 'https://us05web.zoom.us/j/'.$this->faker->numerify('##########').'?pwd='.$this->faker->lexify('????????????????'),
            'midweek_day' => Carbon::WEDNESDAY,
            'midweek_time' => '19:00',
            'weekend_day' => Carbon::SUNDAY,
            'weekend_time' => '10:00',
            'timezone' => 'Europe/Rome',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
