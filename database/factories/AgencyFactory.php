<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agency>
 */
class AgencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'website' => fake()->url(),
            'platform_profiles' => null,
            'properties_count' => 0,
        ];
    }

    public function withPlatformProfiles(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform_profiles' => [
                'inmuebles24' => fake()->url(),
                'vivanuncios' => fake()->url(),
            ],
        ]);
    }
}
