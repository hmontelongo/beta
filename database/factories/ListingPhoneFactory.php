<?php

namespace Database\Factories;

use App\Enums\PhoneType;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListingPhone>
 */
class ListingPhoneFactory extends Factory
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
            'phone' => fake()->numerify('+52##########'),
            'phone_type' => PhoneType::Unknown,
            'contact_name' => fake()->optional(0.5)->name(),
        ];
    }

    public function whatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_type' => PhoneType::Whatsapp,
        ]);
    }

    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_type' => PhoneType::Mobile,
        ]);
    }

    public function landline(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_type' => PhoneType::Landline,
        ]);
    }
}
