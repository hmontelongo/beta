<?php

namespace Database\Factories;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListingImage>
 */
class ListingImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'url' => fake()->imageUrl(800, 600, 'architecture'),
            'local_path' => null,
            'hash' => null,
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    public function downloaded(): static
    {
        return $this->state(fn (array $attributes) => [
            'local_path' => 'images/listings/'.fake()->uuid().'.jpg',
            'hash' => fake()->sha256(),
        ]);
    }
}
