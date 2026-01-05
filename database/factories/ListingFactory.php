<?php

namespace Database\Factories;

use App\Enums\OperationType;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Platform;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $operationType = fake()->randomElement(OperationType::cases());

        return [
            'property_id' => Property::factory(),
            'platform_id' => Platform::factory(),
            'agent_id' => null,
            'agency_id' => null,
            'external_id' => fake()->unique()->uuid(),
            'original_url' => fake()->url(),
            'operations' => [
                [
                    'type' => $operationType->value,
                    'price' => $operationType === OperationType::Rent
                        ? fake()->numberBetween(5000, 50000)
                        : fake()->numberBetween(500000, 10000000),
                    'currency' => 'MXN',
                ],
            ],
            'raw_data' => [
                'title' => fake()->sentence(),
                'description' => fake()->paragraphs(2, true),
            ],
            'data_quality' => null,
            'scraped_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function forSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'operations' => [
                [
                    'type' => OperationType::Sale->value,
                    'price' => fake()->numberBetween(500000, 10000000),
                    'currency' => 'MXN',
                ],
            ],
        ]);
    }

    public function forRent(): static
    {
        return $this->state(fn (array $attributes) => [
            'operations' => [
                [
                    'type' => OperationType::Rent->value,
                    'price' => fake()->numberBetween(5000, 50000),
                    'currency' => 'MXN',
                ],
            ],
        ]);
    }

    public function withAgent(?Agent $agent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_id' => $agent?->id ?? Agent::factory(),
        ]);
    }

    public function withAgency(?Agency $agency = null): static
    {
        return $this->state(fn (array $attributes) => [
            'agency_id' => $agency?->id ?? Agency::factory(),
        ]);
    }

    public function withDataQualityIssues(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_quality' => [
                'missing' => ['bedrooms', 'bathrooms'],
                'suspect' => ['price'],
                'zero_values' => [],
            ],
        ]);
    }
}
