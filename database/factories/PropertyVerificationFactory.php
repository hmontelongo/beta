<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PropertyVerification>
 */
class PropertyVerificationFactory extends Factory
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
            'phone' => fake()->numerify('+52##########'),
            'message_sent' => 'Hola, ¿la propiedad en '.fake()->address().' sigue disponible?',
            'message_sent_at' => null,
            'response_raw' => null,
            'response_parsed' => null,
            'response_at' => null,
            'status' => VerificationStatus::Pending,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_sent_at' => fake()->dateTimeBetween('-7 days', '-1 day'),
            'status' => VerificationStatus::Sent,
        ]);
    }

    public function responded(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_sent_at' => fake()->dateTimeBetween('-7 days', '-2 days'),
            'response_raw' => 'Sí, la propiedad sigue disponible. ¿Le gustaría agendar una visita?',
            'response_parsed' => [
                'available' => true,
                'notes' => 'Agent confirmed availability',
            ],
            'response_at' => fake()->dateTimeBetween('-2 days', 'now'),
            'status' => VerificationStatus::Responded,
        ]);
    }

    public function noResponse(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_sent_at' => fake()->dateTimeBetween('-14 days', '-7 days'),
            'status' => VerificationStatus::NoResponse,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VerificationStatus::Failed,
        ]);
    }
}
