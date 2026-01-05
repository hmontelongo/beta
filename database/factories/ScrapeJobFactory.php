<?php

namespace Database\Factories;

use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeJobType;
use App\Models\DiscoveredListing;
use App\Models\Platform;
use App\Models\ScrapeJob;
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
            'discovered_listing_id' => null,
            'parent_id' => null,
            'target_url' => fake()->url(),
            'job_type' => ScrapeJobType::Discovery,
            'filters' => null,
            'status' => ScrapeJobStatus::Pending,
            'total_results' => null,
            'total_pages' => null,
            'current_page' => null,
            'properties_found' => 0,
            'properties_new' => 0,
            'properties_updated' => 0,
            'result' => null,
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
            'error_message' => 'Connection timeout after 30 seconds',
        ]);
    }

    public function withFilters(): static
    {
        return $this->state(fn (array $attributes) => [
            'filters' => [
                'city' => 'guadalajara',
                'type' => 'apartment',
                'operation' => 'rent',
            ],
        ]);
    }

    public function discovery(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_type' => ScrapeJobType::Discovery,
        ]);
    }

    public function listing(): static
    {
        return $this->state(fn (array $attributes) => [
            'job_type' => ScrapeJobType::Listing,
            'discovered_listing_id' => DiscoveredListing::factory(),
        ]);
    }

    public function withParent(?ScrapeJob $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent?->id ?? ScrapeJob::factory(),
        ]);
    }

    public function withPagination(int $total = 100, int $pages = 5, int $current = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'total_results' => $total,
            'total_pages' => $pages,
            'current_page' => $current,
        ]);
    }

    public function withResult(array $result = []): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => $result ?: [
                'listings_discovered' => fake()->numberBetween(10, 50),
                'duplicates_skipped' => fake()->numberBetween(0, 10),
            ],
        ]);
    }
}
