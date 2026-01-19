<?php

use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Livewire\Listings\Index;
use App\Models\Listing;
use App\Models\ListingGroup;
use App\Models\Platform;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('computes dedup stats from actual records', function () {
    $platform = Platform::factory()->create();

    // Create listings with various dedup statuses
    Listing::factory()->count(4)->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Pending,
    ]);
    Listing::factory()->count(2)->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Processing,
    ]);
    Listing::factory()->count(6)->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Grouped,
    ]);
    Listing::factory()->count(3)->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Completed,
    ]);
    Listing::factory()->count(1)->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Failed,
    ]);

    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->stats['dedup_pending'])->toBe(4)
        ->and($instance->stats['dedup_processing'])->toBe(2)
        ->and($instance->stats['dedup_grouped'])->toBe(6)
        ->and($instance->stats['dedup_completed'])->toBe(3)
        ->and($instance->stats['dedup_failed'])->toBe(1);
});

it('counts listing groups by status', function () {
    $platform = Platform::factory()->create();

    // Create listing groups with various statuses
    ListingGroup::factory()->count(2)->create(['status' => ListingGroupStatus::PendingReview]);
    ListingGroup::factory()->count(3)->create(['status' => ListingGroupStatus::PendingAi]);
    ListingGroup::factory()->count(1)->create(['status' => ListingGroupStatus::ProcessingAi]);

    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->stats['groups_pending_review'])->toBe(2)
        ->and($instance->stats['groups_pending_ai'])->toBe(3)
        ->and($instance->stats['groups_processing_ai'])->toBe(1);
});

it('identifies when processing is active', function () {
    $platform = Platform::factory()->create();

    // No processing initially
    Listing::factory()->count(5)->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Pending,
    ]);

    $component = Livewire::test(Index::class);
    expect($component->instance()->isProcessing)->toBeFalse();

    // Add processing dedup job
    Listing::factory()->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Processing,
    ]);

    // Refresh computed property
    $component = Livewire::test(Index::class);
    expect($component->instance()->isProcessing)->toBeTrue();
});

it('identifies deduplication processing', function () {
    $platform = Platform::factory()->create();

    Listing::factory()->create([
        'platform_id' => $platform->id,
        'dedup_status' => DedupStatus::Processing,
    ]);

    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->isDeduplicationProcessing)->toBeTrue();
});

it('identifies property creation processing', function () {
    ListingGroup::factory()->create(['status' => ListingGroupStatus::ProcessingAi]);

    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->isPropertyCreationProcessing)->toBeTrue();
});

it('returns zero stats when no listings exist', function () {
    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->stats['dedup_pending'])->toBe(0)
        ->and($instance->stats['dedup_processing'])->toBe(0)
        ->and($instance->stats['dedup_grouped'])->toBe(0)
        ->and($instance->stats['dedup_completed'])->toBe(0)
        ->and($instance->stats['dedup_failed'])->toBe(0)
        ->and($instance->stats['groups_pending_review'])->toBe(0)
        ->and($instance->stats['groups_pending_ai'])->toBe(0)
        ->and($instance->stats['groups_processing_ai'])->toBe(0)
        ->and($instance->stats['dedup_queued'])->toBe(0)
        ->and($instance->stats['property_creation_queued'])->toBe(0);
});

it('includes queue depth in processing detection', function () {
    // When no jobs in queue and no processing in DB, should not be processing
    $component = Livewire::test(Index::class);
    $instance = $component->instance();

    expect($instance->isProcessing)->toBeFalse()
        ->and($instance->isDeduplicationProcessing)->toBeFalse()
        ->and($instance->isPropertyCreationProcessing)->toBeFalse();

    // Stats should include queue depth keys
    expect($instance->stats)->toHaveKeys(['dedup_queued', 'property_creation_queued']);
});
