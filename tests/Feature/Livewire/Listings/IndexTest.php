<?php

use App\Enums\AiEnrichmentStatus;
use App\Enums\DedupCandidateStatus;
use App\Enums\DedupStatus;
use App\Livewire\Listings\Index;
use App\Models\DedupCandidate;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('computes ai enrichment stats from actual records', function () {
    $platform = Platform::factory()->create();

    // Create listings with various AI statuses
    Listing::factory()->count(5)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Pending,
    ]);
    Listing::factory()->count(3)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Processing,
    ]);
    Listing::factory()->count(10)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Completed,
    ]);
    Listing::factory()->count(2)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Failed,
    ]);

    $component = Livewire::test(Index::class);

    // Access computed properties via instance()
    $instance = $component->instance();

    expect($instance->stats['ai_pending'])->toBe(5)
        ->and($instance->stats['ai_processing'])->toBe(3)
        ->and($instance->stats['ai_completed'])->toBe(10)
        ->and($instance->stats['ai_failed'])->toBe(2);
});

it('computes dedup stats from actual records', function () {
    $platform = Platform::factory()->create();

    // Create listings with various dedup statuses (all must be AI completed for dedup_pending)
    Listing::factory()->count(4)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Completed,
        'dedup_status' => DedupStatus::Pending,
    ]);
    Listing::factory()->count(2)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Completed,
        'dedup_status' => DedupStatus::Processing,
    ]);
    Listing::factory()->count(6)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Completed,
        'dedup_status' => DedupStatus::Matched,
    ]);
    Listing::factory()->count(3)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Completed,
        'dedup_status' => DedupStatus::NeedsReview,
    ]);
    Listing::factory()->count(1)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Completed,
        'dedup_status' => DedupStatus::Failed,
    ]);

    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->stats['dedup_pending'])->toBe(4)
        ->and($instance->stats['dedup_processing'])->toBe(2)
        ->and($instance->stats['dedup_matched'])->toBe(6)
        ->and($instance->stats['dedup_needs_review'])->toBe(3)
        ->and($instance->stats['dedup_failed'])->toBe(1);
});

it('counts dedup candidates pending review', function () {
    $platform = Platform::factory()->create();

    $listingA = Listing::factory()->create(['platform_id' => $platform->id]);
    $listingB = Listing::factory()->create(['platform_id' => $platform->id]);
    $listingC = Listing::factory()->create(['platform_id' => $platform->id]);
    $listingD = Listing::factory()->create(['platform_id' => $platform->id]);

    // Create dedup candidates with various statuses (manually, no factory)
    DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingB->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.8,
    ]);
    DedupCandidate::create([
        'listing_a_id' => $listingA->id,
        'listing_b_id' => $listingC->id,
        'status' => DedupCandidateStatus::NeedsReview,
        'overall_score' => 0.7,
    ]);
    DedupCandidate::create([
        'listing_a_id' => $listingC->id,
        'listing_b_id' => $listingD->id,
        'status' => DedupCandidateStatus::ConfirmedMatch,
        'overall_score' => 0.9,
    ]);

    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->stats['candidates_pending_review'])->toBe(2);
});

it('identifies when processing is active', function () {
    $platform = Platform::factory()->create();

    // No processing initially
    Listing::factory()->count(5)->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Pending,
        'dedup_status' => DedupStatus::Pending,
    ]);

    $component = Livewire::test(Index::class);
    expect($component->instance()->isProcessing)->toBeFalse();

    // Add processing AI job
    Listing::factory()->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Processing,
    ]);

    // Refresh computed property
    $component = Livewire::test(Index::class);
    expect($component->instance()->isProcessing)->toBeTrue();
});

it('identifies enrichment processing separately', function () {
    $platform = Platform::factory()->create();

    Listing::factory()->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Processing,
        'dedup_status' => DedupStatus::Pending,
    ]);

    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->isEnrichmentProcessing)->toBeTrue()
        ->and($instance->isDeduplicationProcessing)->toBeFalse();
});

it('identifies deduplication processing separately', function () {
    $platform = Platform::factory()->create();

    Listing::factory()->create([
        'platform_id' => $platform->id,
        'ai_status' => AiEnrichmentStatus::Completed,
        'dedup_status' => DedupStatus::Processing,
    ]);

    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->isEnrichmentProcessing)->toBeFalse()
        ->and($instance->isDeduplicationProcessing)->toBeTrue();
});

it('returns zero stats when no listings exist', function () {
    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->stats['ai_pending'])->toBe(0)
        ->and($instance->stats['ai_processing'])->toBe(0)
        ->and($instance->stats['ai_completed'])->toBe(0)
        ->and($instance->stats['ai_failed'])->toBe(0)
        ->and($instance->stats['dedup_pending'])->toBe(0)
        ->and($instance->stats['dedup_processing'])->toBe(0)
        ->and($instance->stats['dedup_matched'])->toBe(0)
        ->and($instance->stats['dedup_needs_review'])->toBe(0)
        ->and($instance->stats['dedup_failed'])->toBe(0)
        ->and($instance->stats['candidates_pending_review'])->toBe(0)
        ->and($instance->stats['ai_queued'])->toBe(0)
        ->and($instance->stats['dedup_queued'])->toBe(0);
});

it('includes queue depth in processing detection', function () {
    // When no jobs in queue and no processing in DB, should not be processing
    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->isProcessing)->toBeFalse()
        ->and($instance->isEnrichmentProcessing)->toBeFalse()
        ->and($instance->isDeduplicationProcessing)->toBeFalse();

    // Stats should include queue depth keys
    expect($instance->stats)->toHaveKeys(['ai_queued', 'dedup_queued']);
});
