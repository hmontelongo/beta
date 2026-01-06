<?php

namespace Database\Factories;

use App\Enums\ScrapePhase;
use App\Enums\ScrapeRunStatus;
use App\Models\Platform;
use App\Models\SearchQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScrapeRun>
 */
class ScrapeRunFactory extends Factory
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
            'search_query_id' => SearchQuery::factory(),
            'status' => ScrapeRunStatus::Pending,
            'phase' => ScrapePhase::Discover,
            'stats' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function discovering(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScrapeRunStatus::Discovering,
            'phase' => ScrapePhase::Discover,
            'started_at' => now(),
            'stats' => [
                'pages_total' => 0,
                'pages_done' => 0,
                'listings_found' => 0,
            ],
        ]);
    }

    public function scraping(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScrapeRunStatus::Scraping,
            'phase' => ScrapePhase::Scrape,
            'started_at' => fake()->dateTimeBetween('-1 hour', '-30 minutes'),
            'stats' => [
                'pages_total' => 10,
                'pages_done' => 10,
                'listings_found' => 200,
                'listings_scraped' => 50,
            ],
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScrapeRunStatus::Completed,
            'phase' => ScrapePhase::Scrape,
            'started_at' => fake()->dateTimeBetween('-2 hours', '-1 hour'),
            'completed_at' => fake()->dateTimeBetween('-30 minutes', 'now'),
            'stats' => [
                'pages_total' => 10,
                'pages_done' => 10,
                'listings_found' => 200,
                'listings_scraped' => 200,
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScrapeRunStatus::Failed,
            'started_at' => fake()->dateTimeBetween('-1 hour', '-30 minutes'),
            'error_message' => fake()->sentence(),
        ]);
    }

    public function forSearchQuery(SearchQuery $searchQuery): static
    {
        return $this->state(fn (array $attributes) => [
            'search_query_id' => $searchQuery->id,
            'platform_id' => $searchQuery->platform_id,
        ]);
    }
}
