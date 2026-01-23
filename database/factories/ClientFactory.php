<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'whatsapp' => '+52 33 '.fake()->numerify('#### ####'),
            'email' => fake()->optional()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Client with all contact info.
     */
    public function withFullContact(): static
    {
        return $this->state(fn (array $attributes): array => [
            'whatsapp' => '+52 33 '.fake()->numerify('#### ####'),
            'email' => fake()->safeEmail(),
        ]);
    }
}
