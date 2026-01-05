<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agent>
 */
class AgentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'agency_id' => null,
            'name' => $name,
            'normalized_name' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)),
            'phone' => fake()->optional(0.7)->numerify('+52 33 #### ####'),
            'email' => fake()->optional(0.8)->safeEmail(),
            'whatsapp' => fake()->optional(0.6)->numerify('+52 33 #### ####'),
            'platform_profiles' => null,
            'properties_count' => 0,
        ];
    }

    public function withAgency(?Agency $agency = null): static
    {
        return $this->state(fn (array $attributes) => [
            'agency_id' => $agency?->id ?? Agency::factory(),
        ]);
    }

    public function independent(): static
    {
        return $this->state(fn (array $attributes) => [
            'agency_id' => null,
        ]);
    }

    public function withPlatformProfiles(): static
    {
        $agentSlug = fake()->slug(2);

        return $this->state(fn (array $attributes) => [
            'platform_profiles' => [
                'inmuebles24' => 'https://www.inmuebles24.com/agente/'.$agentSlug,
                'vivanuncios' => 'https://www.vivanuncios.com.mx/agente/'.$agentSlug,
            ],
        ]);
    }
}
