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
        return [
            'agency_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'whatsapp' => fake()->phoneNumber(),
            'platform_profiles' => null,
            'properties_count' => 0,
        ];
    }

    public function forAgency(?Agency $agency = null): static
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
}
