<?php

namespace Database\Factories;

use App\Enums\DedupCandidateStatus;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DedupCandidate>
 */
class DedupCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_a_id' => Listing::factory(),
            'listing_b_id' => Listing::factory(),
            'status' => DedupCandidateStatus::Pending,
            'distance_meters' => fake()->randomFloat(2, 10, 500),
            'coordinate_score' => fake()->randomFloat(4, 0.5, 1.0),
            'address_score' => fake()->randomFloat(4, 0.3, 1.0),
            'features_score' => fake()->randomFloat(4, 0.4, 1.0),
            'overall_score' => fake()->randomFloat(4, 0.5, 0.9),
            'ai_verified' => false,
            'ai_verdict' => null,
            'ai_reasoning' => null,
            'ai_response_raw' => null,
            'resolved_property_id' => null,
            'resolved_at' => null,
        ];
    }

    public function confirmedMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DedupCandidateStatus::ConfirmedMatch,
            'overall_score' => fake()->randomFloat(4, 0.85, 1.0),
            'resolved_at' => now(),
        ]);
    }

    public function confirmedDifferent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DedupCandidateStatus::ConfirmedDifferent,
            'overall_score' => fake()->randomFloat(4, 0.2, 0.5),
            'resolved_at' => now(),
        ]);
    }

    public function needsReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DedupCandidateStatus::NeedsReview,
            'overall_score' => fake()->randomFloat(4, 0.55, 0.79),
        ]);
    }

    public function aiVerified(): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_verified' => true,
            'ai_verdict' => fake()->randomElement(['match', 'different']),
            'ai_reasoning' => 'AI analysis completed based on property features.',
        ]);
    }
}
