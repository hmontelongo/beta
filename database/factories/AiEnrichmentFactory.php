<?php

namespace Database\Factories;

use App\Enums\AiEnrichmentStatus;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiEnrichment>
 */
class AiEnrichmentFactory extends Factory
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
            'status' => AiEnrichmentStatus::Pending,
            'validated_data' => null,
            'extracted_tags' => null,
            'improved_description' => null,
            'address_verification' => null,
            'quality_score' => null,
            'quality_issues' => null,
            'suggested_property_type' => null,
            'confidence_scores' => null,
            'ai_response_raw' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'error_message' => null,
            'processed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiEnrichmentStatus::Completed,
            'quality_score' => fake()->numberBetween(60, 100),
            'input_tokens' => fake()->numberBetween(500, 2000),
            'output_tokens' => fake()->numberBetween(200, 1000),
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiEnrichmentStatus::Failed,
            'error_message' => 'Test error: API call failed',
            'processed_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiEnrichmentStatus::Processing,
        ]);
    }
}
