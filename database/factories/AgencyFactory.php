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
            'phone' => fake()->optional(0.7)->numerify('+52 33 #### ####'),
            'email' => fake()->optional(0.8)->companyEmail(),
            'website' => fake()->optional(0.5)->url(),
            'platform_profiles' => null,
            'properties_count' => 0,
        ];
    }

    public function withPlatformProfiles(): static
    {
        $agencySlug = fake()->slug(2);

        return $this->state(fn (array $attributes) => [
            'platform_profiles' => [
                'inmuebles24' => 'https://www.inmuebles24.com/agencia/'.$agencySlug,
                'vivanuncios' => 'https://www.vivanuncios.com.mx/agencia/'.$agencySlug,
            ],
        ]);
    }
}
