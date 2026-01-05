<?php

namespace Database\Factories;

use App\Enums\DiscoveredListingStatus;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscoveredListing>
 */
class DiscoveredListingFactory extends Factory
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
            'url' => fake()->url(),
            'external_id' => fake()->optional(0.8)->uuid(),
            'batch_id' => null,
            'status' => DiscoveredListingStatus::Pending,
            'priority' => 0,
            'attempts' => 0,
            'last_attempt_at' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscoveredListingStatus::Queued,
        ]);
    }

    public function scraped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscoveredListingStatus::Scraped,
            'attempts' => 1,
            'last_attempt_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscoveredListingStatus::Failed,
            'attempts' => fake()->numberBetween(1, 3),
            'last_attempt_at' => now(),
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DiscoveredListingStatus::Skipped,
        ]);
    }

    public function withBatch(?string $batchId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_id' => $batchId ?? fake()->uuid(),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 10,
        ]);
    }
}
