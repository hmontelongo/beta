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
        $hasRent = fake()->boolean(60);
        $hasSale = fake()->boolean(40);

        if (! $hasRent && ! $hasSale) {
            $hasRent = true;
        }

        $operations = [];

        if ($hasRent) {
            $operations[] = [
                'type' => OperationType::Rent->value,
                'price' => fake()->numberBetween(8, 50) * 1000,
                'currency' => 'MXN',
            ];
        }

        if ($hasSale) {
            $operations[] = [
                'type' => OperationType::Sale->value,
                'price' => fake()->numberBetween(1, 10) * 1000000,
                'currency' => 'MXN',
            ];
        }

        return [
            'property_id' => Property::factory(),
            'platform_id' => Platform::factory(),
            'agent_id' => null,
            'agency_id' => null,
            'external_id' => fake()->unique()->uuid(),
            'original_url' => fake()->url(),
            'operations' => $operations,
            'raw_data' => [
                'title' => 'Departamento en '.fake()->randomElement(['renta', 'venta']),
                'description' => fake()->paragraphs(2, true),
                'scraped_at' => now()->subDays(fake()->numberBetween(0, 30))->toIso8601String(),
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
                    'price' => fake()->numberBetween(1, 10) * 1000000,
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
                    'price' => fake()->numberBetween(8, 50) * 1000,
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

    public function withQualityIssues(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_quality' => [
                'missing' => fake()->randomElements(['latitude', 'longitude', 'bedrooms', 'bathrooms'], fake()->numberBetween(1, 2)),
                'suspect' => fake()->optional(0.3)->randomElements(['price'], 1) ?? [],
                'zero_values' => fake()->optional(0.2)->randomElements(['m2_lot', 'm2_built'], 1) ?? [],
            ],
        ]);
    }
}
