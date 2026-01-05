<?php

namespace Database\Factories;

use App\Enums\ScrapeJobStatus;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScrapeJob>
 */
class ScrapeJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_id' => Platform::factory(),
            'target_url' => fake()->url(),
            'filters' => null,
            'status' => ScrapeJobStatus::Pending,
            'properties_found' => 0,
            'properties_new' => 0,
            'properties_updated' => 0,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScrapeJobStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        $found = fake()->numberBetween(10, 100);
        $new = fake()->numberBetween(0, $found);

        return $this->state(fn (array $attributes) => [
            'status' => ScrapeJobStatus::Completed,
            'started_at' => fake()->dateTimeBetween('-1 hour', '-30 minutes'),
            'completed_at' => now(),
            'properties_found' => $found,
            'properties_new' => $new,
            'properties_updated' => $found - $new,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScrapeJobStatus::Failed,
            'started_at' => fake()->dateTimeBetween('-1 hour', '-30 minutes'),
            'completed_at' => now(),
            'error_message' => fake()->sentence(),
        ]);
    }

    public function withFilters(): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => [
                'city' => 'Ciudad de México',
                'property_type' => 'apartment',
                'min_price' => 500000,
                'max_price' => 2000000,
            ],
        ]);
    }
}
