<?php

namespace Database\Factories;

use App\Enums\ListingGroupStatus;
use App\Models\ListingGroup;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingGroup>
 */
class ListingGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ListingGroupStatus::PendingReview,
            'property_id' => null,
            'match_score' => fake()->optional(0.7)->randomFloat(2, 0.5, 1.0),
            'ai_analysis' => null,
            'ai_processed_at' => null,
            'rejection_reason' => null,
        ];
    }

    /**
     * Indicate that the group is pending review.
     */
    public function pendingReview(): static
    {
        return $this->state(fn () => [
            'status' => ListingGroupStatus::PendingReview,
        ]);
    }

    /**
     * Indicate that the group is pending AI processing.
     */
    public function pendingAi(): static
    {
        return $this->state(fn () => [
            'status' => ListingGroupStatus::PendingAi,
        ]);
    }

    /**
     * Indicate that the group is being processed by AI.
     */
    public function processingAi(): static
    {
        return $this->state(fn () => [
            'status' => ListingGroupStatus::ProcessingAi,
        ]);
    }

    /**
     * Indicate that the group is completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ListingGroupStatus::Completed,
            'property_id' => Property::factory(),
            'ai_analysis' => [
                'quality_score' => fake()->numberBetween(60, 100),
                'description' => fake()->paragraph(),
            ],
            'ai_processed_at' => now(),
        ]);
    }

    /**
     * Indicate that the group is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ListingGroupStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate a unique listing (no match score).
     */
    public function unique(): static
    {
        return $this->state(fn () => [
            'match_score' => null,
        ]);
    }

    /**
     * Indicate a high confidence match.
     */
    public function highConfidence(): static
    {
        return $this->state(fn () => [
            'match_score' => fake()->randomFloat(2, 0.85, 1.0),
        ]);
    }

    /**
     * Indicate a low confidence match requiring review.
     */
    public function lowConfidence(): static
    {
        return $this->state(fn () => [
            'match_score' => fake()->randomFloat(2, 0.5, 0.84),
        ]);
    }
}
