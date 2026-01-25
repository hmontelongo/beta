<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropertyImage>
 */
class PropertyImageFactory extends Factory
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
            'path' => 'property-images/'.fake()->uuid().'.jpg',
            'original_filename' => fake()->word().'.jpg',
            'size_bytes' => fake()->numberBetween(100000, 5000000),
            'position' => fake()->numberBetween(0, 10),
            'is_cover' => false,
        ];
    }

    /**
     * Mark the image as the cover image.
     */
    public function cover(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_cover' => true,
            'position' => 0,
        ]);
    }
}
