<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Platform>
 */
class PlatformFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().fake()->unique()->randomNumber(4),
            'base_url' => fake()->url(),
            'is_active' => true,
            'config' => null,
        ];
    }

    public function withConfig(array $config = []): static
    {
        return $this->state(fn (array $attributes) => [
            'config' => $config ?: [
                'scraper_class' => 'Inmuebles24',
                'rate_limit' => 10,
                'timeout' => 30,
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
