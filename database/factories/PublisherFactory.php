<?php

namespace Database\Factories;

use App\Enums\PublisherType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Publisher>
 */
class PublisherFactory extends Factory
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
            'type' => fake()->randomElement(PublisherType::cases()),
            'phone' => fake()->optional(0.7)->numerify('+52 33 #### ####'),
            'email' => fake()->optional(0.5)->companyEmail(),
            'whatsapp' => fake()->optional(0.6)->numerify('+52 33 #### ####'),
            'platform_profiles' => null,
            'parent_id' => null,
        ];
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->name(),
            'type' => PublisherType::Individual,
        ]);
    }

    public function agency(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->company().' Inmobiliaria',
            'type' => PublisherType::Agency,
        ]);
    }

    public function developer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->company().' Desarrollos',
            'type' => PublisherType::Developer,
        ]);
    }

    public function withPlatformProfiles(): static
    {
        $publisherSlug = fake()->slug(2);
        $publisherId = fake()->numberBetween(100000, 999999);

        return $this->state(fn (array $attributes) => [
            'platform_profiles' => [
                'inmuebles24' => [
                    'id' => $publisherId,
                    'url' => 'https://www.inmuebles24.com/inmobiliarias/'.$publisherSlug.'-inmuebles.html',
                    'logo' => null,
                    'scraped_at' => now()->toIso8601String(),
                ],
            ],
        ]);
    }
}
