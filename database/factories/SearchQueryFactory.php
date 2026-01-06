<?php

namespace Database\Factories;

use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SearchQuery>
 */
class SearchQueryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locations = ['Jalisco', 'CDMX', 'Nuevo León', 'Querétaro', 'Puebla'];
        $types = ['en-renta', 'en-venta'];
        $location = fake()->randomElement($locations);
        $type = fake()->randomElement($types);

        return [
            'platform_id' => Platform::factory(),
            'name' => ucfirst(str_replace('-', ' ', $type)).' en '.$location,
            'search_url' => 'https://www.inmuebles24.com/inmuebles-'.$type.'-en-'.strtolower(str_replace(' ', '-', $location)).'.html',
            'is_active' => true,
            'last_run_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withLastRun(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_run_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function forPlatform(Platform $platform): static
    {
        return $this->state(fn (array $attributes) => [
            'platform_id' => $platform->id,
        ]);
    }
}
