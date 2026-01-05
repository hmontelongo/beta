<?php

namespace Database\Factories;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Guadalajara colonias for realistic data.
     *
     * @var array<string>
     */
    protected array $colonias = [
        'Providencia',
        'Americana',
        'Country Club',
        'Chapalita',
        'Colomos Providencia',
        'Lafayette',
        'Arcos Vallarta',
        'Ladrón de Guevara',
        'Italia Providencia',
        'Jardines del Bosque',
        'Monraz',
        'Prados Providencia',
    ];

    /**
     * Available amenities.
     *
     * @var array<string>
     */
    protected array $amenities = [
        'pool',
        'gym',
        'security_24h',
        'elevator',
        'rooftop',
        'pet_friendly',
        'garden',
        'terrace',
        'storage',
        'laundry',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement(['Guadalajara', 'Guadalajara', 'Guadalajara', 'Zapopan', 'Tlaquepaque']);

        return [
            'address' => fake()->streetAddress(),
            'interior_number' => fake()->optional(0.3)->randomElement(['101', '202', 'A', 'B', 'PH1', 'PH2', '1A', '3B']),
            'colonia' => fake()->randomElement($this->colonias),
            'city' => $city,
            'state' => 'Jalisco',
            'postal_code' => fake()->optional(0.8)->numerify(fake()->randomElement(['44', '45']).'###'),
            'latitude' => fake()->optional(0.7)->randomFloat(7, 20.6, 20.7),
            'longitude' => fake()->optional(0.7)->randomFloat(7, -103.4, -103.3),
            'property_type' => fake()->randomElement(PropertyType::cases()),
            'bedrooms' => fake()->optional(0.9)->numberBetween(1, 5),
            'bathrooms' => fake()->optional(0.9)->numberBetween(1, 4),
            'half_bathrooms' => fake()->optional(0.5)->numberBetween(0, 2),
            'parking_spots' => fake()->optional(0.8)->numberBetween(0, 4),
            'amenities' => null,
            'status' => PropertyStatus::Unverified,
            'listings_count' => 0,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PropertyStatus::Verified,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PropertyStatus::Active,
        ]);
    }

    public function withCoordinates(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => fake()->randomFloat(7, 20.6, 20.7),
            'longitude' => fake()->randomFloat(7, -103.4, -103.3),
        ]);
    }

    public function apartment(): static
    {
        return $this->state(fn (array $attributes) => [
            'property_type' => PropertyType::Apartment,
        ]);
    }

    public function house(): static
    {
        return $this->state(fn (array $attributes) => [
            'property_type' => PropertyType::House,
        ]);
    }

    public function withAmenities(): static
    {
        return $this->state(fn (array $attributes) => [
            'amenities' => fake()->randomElements(
                $this->amenities,
                fake()->numberBetween(0, 5)
            ),
        ]);
    }
}
