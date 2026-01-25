<?php

namespace Database\Factories;

use App\Enums\OperationType;
use App\Enums\PropertySourceType;
use App\Enums\PropertyStatus;
use App\Enums\PropertySubtype;
use App\Enums\PropertyType;
use App\Models\User;
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
            // Source type defaults to scraped for backwards compatibility
            'source_type' => PropertySourceType::Scraped,
            'user_id' => null,
            'address' => fake()->streetAddress(),
            'interior_number' => fake()->optional(0.3)->randomElement(['101', '202', 'A', 'B', 'PH1', 'PH2', '1A', '3B']),
            'colonia' => fake()->randomElement($this->colonias),
            'city' => $city,
            'state' => 'Jalisco',
            'postal_code' => fake()->optional(0.8)->numerify(fake()->randomElement(['44', '45']).'###'),
            'latitude' => fake()->optional(0.7)->randomFloat(7, 20.6, 20.7),
            'longitude' => fake()->optional(0.7)->randomFloat(7, -103.4, -103.3),
            'property_type' => fake()->randomElement(PropertyType::cases()),
            'property_subtype' => fake()->optional(0.2)->randomElement(PropertySubtype::cases()),
            'lot_size_m2' => fake()->optional(0.6)->randomFloat(2, 50, 500),
            'built_size_m2' => fake()->optional(0.8)->randomFloat(2, 40, 300),
            'bedrooms' => fake()->optional(0.9)->numberBetween(1, 5),
            'bathrooms' => fake()->optional(0.9)->numberBetween(1, 4),
            'half_bathrooms' => fake()->optional(0.5)->numberBetween(0, 2),
            'parking_spots' => fake()->optional(0.8)->numberBetween(0, 4),
            'age_years' => fake()->optional(0.5)->numberBetween(0, 50),
            'amenities' => null,
            'status' => PropertyStatus::Unverified,
            'confidence_score' => fake()->optional(0.3)->randomFloat(2, 0.5, 1.0),
            'listings_count' => 0,
        ];
    }

    /**
     * Create a native (agent-uploaded) property.
     */
    public function native(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => PropertySourceType::Native,
            'user_id' => $user?->id ?? User::factory(),
            'operation_type' => fake()->randomElement(OperationType::cases()),
            'price' => fake()->randomFloat(2, 500000, 50000000),
            'price_currency' => 'MXN',
            'is_collaborative' => false,
            'commission_split' => null,
            'description' => fake()->paragraph(3),
            'original_description' => fake()->paragraph(2),
        ]);
    }

    /**
     * Create a native property that is open for collaboration.
     */
    public function collaborative(?User $user = null): static
    {
        return $this->native($user)->state(fn (array $attributes) => [
            'is_collaborative' => true,
            'commission_split' => fake()->randomElement([30.00, 40.00, 50.00]),
        ]);
    }

    /**
     * Create a native property for rent.
     */
    public function forRent(?User $user = null): static
    {
        return $this->native($user)->state(fn (array $attributes) => [
            'operation_type' => OperationType::Rent,
            'price' => fake()->randomFloat(2, 8000, 150000),
        ]);
    }

    /**
     * Create a native property for sale.
     */
    public function forSale(?User $user = null): static
    {
        return $this->native($user)->state(fn (array $attributes) => [
            'operation_type' => OperationType::Sale,
            'price' => fake()->randomFloat(2, 1000000, 50000000),
        ]);
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

    public function penthouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'property_type' => PropertyType::Apartment,
            'property_subtype' => PropertySubtype::Penthouse,
        ]);
    }

    public function withSizes(): static
    {
        return $this->state(fn (array $attributes) => [
            'lot_size_m2' => fake()->randomFloat(2, 80, 500),
            'built_size_m2' => fake()->randomFloat(2, 60, 300),
        ]);
    }
}
