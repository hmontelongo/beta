<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropertyConflict>
 */
class PropertyConflictFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'listing_id' => Listing::factory(),
            'field' => fake()->randomElement(['bedrooms', 'bathrooms', 'parking_spots', 'address']),
            'canonical_value' => (string) fake()->numberBetween(1, 5),
            'source_value' => (string) fake()->numberBetween(1, 5),
            'resolved' => false,
            'resolution' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved' => true,
            'resolution' => [
                'resolved_by' => 'system',
                'chosen_value' => $attributes['canonical_value'],
                'notes' => 'Auto-resolved based on majority consensus',
            ],
            'resolved_at' => now(),
        ]);
    }
}
