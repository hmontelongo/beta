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
     * Mexican states for realistic data.
     *
     * @var array<string>
     */
    protected array $mexicanStates = [
        'Ciudad de México',
        'Jalisco',
        'Nuevo León',
        'Estado de México',
        'Querétaro',
        'Puebla',
        'Guanajuato',
        'Yucatán',
    ];

    /**
     * Mexican cities mapped to states.
     *
     * @var array<string, array<string>>
     */
    protected array $mexicanCities = [
        'Ciudad de México' => ['Ciudad de México', 'Coyoacán', 'Miguel Hidalgo', 'Benito Juárez'],
        'Jalisco' => ['Guadalajara', 'Zapopan', 'Tlaquepaque', 'Puerto Vallarta'],
        'Nuevo León' => ['Monterrey', 'San Pedro Garza García', 'San Nicolás de los Garza'],
        'Estado de México' => ['Toluca', 'Naucalpan', 'Tlalnepantla', 'Metepec'],
        'Querétaro' => ['Querétaro', 'San Juan del Río', 'El Marqués'],
        'Puebla' => ['Puebla', 'Cholula', 'Atlixco'],
        'Guanajuato' => ['León', 'Guanajuato', 'San Miguel de Allende', 'Irapuato'],
        'Yucatán' => ['Mérida', 'Valladolid', 'Progreso'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $state = fake()->randomElement($this->mexicanStates);
        $city = fake()->randomElement($this->mexicanCities[$state]);

        return [
            'address' => fake()->streetAddress(),
            'interior_number' => fake()->optional(0.3)->numerify('Int. ##'),
            'colonia' => fake()->words(2, true),
            'city' => $city,
            'state' => $state,
            'postal_code' => fake()->numerify('#####'),
            'latitude' => fake()->latitude(14, 32),
            'longitude' => fake()->longitude(-118, -86),
            'property_type' => fake()->randomElement(PropertyType::cases()),
            'bedrooms' => fake()->optional()->numberBetween(1, 6),
            'bathrooms' => fake()->optional()->numberBetween(1, 4),
            'half_bathrooms' => fake()->optional(0.5)->numberBetween(0, 2),
            'parking_spots' => fake()->optional()->numberBetween(0, 4),
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

    public function withAmenities(): static
    {
        return $this->state(fn (array $attributes) => [
            'amenities' => fake()->randomElements([
                'pool',
                'gym',
                'security',
                'garden',
                'terrace',
                'elevator',
                'storage',
                'laundry',
            ], fake()->numberBetween(2, 5)),
        ]);
    }
}
