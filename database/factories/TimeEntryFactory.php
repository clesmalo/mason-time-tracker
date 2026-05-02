<?php

namespace Database\Factories;

use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-30 days', '-1 hour');
        $endedAt   = (clone $startedAt)->modify('+' . $this->faker->numberBetween(30, 480) . ' minutes');

        return [
            'started_at' => $startedAt,
            'ended_at'   => $endedAt,
        ];
    }
}
