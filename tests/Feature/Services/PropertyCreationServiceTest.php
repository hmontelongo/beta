<?php

use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Enums\PropertyStatus;
use App\Models\Listing;
use App\Models\ListingGroup;
use App\Models\Platform;
use App\Models\Property;
use App\Models\Publisher;
use App\Services\AI\ClaudeClient;
use App\Services\AI\PropertyCreationService;
use App\Services\ApiUsageTracker;
use App\Services\Google\GeocodingService;

beforeEach(function () {
    $this->platform = Platform::factory()->create(['name' => 'Inmuebles24']);
});

/**
 * Helper to create a mock ApiUsageTracker.
 */
function createMockUsageTracker(): ApiUsageTracker
{
    $mock = Mockery::mock(ApiUsageTracker::class);
    $mock->shouldReceive('logClaudeUsage')->andReturnUsing(fn () => new \App\Models\ApiUsageLog);

    return $mock;
}

/**
 * Helper to create a mock ClaudeClient with response.
 * Handles the withTracking() chain.
 */
function createMockClaudeClient(array $mockResponse, ?array $toolResult = null, ?array $usage = null): ClaudeClient
{
    $mockClaude = Mockery::mock(ClaudeClient::class);

    // withTracking returns self, allowing chained method calls
    $mockClaude->shouldReceive('withTracking')->andReturnSelf();
    $mockClaude->shouldReceive('message')->andReturn($mockResponse);
    $mockClaude->shouldReceive('extractToolUse')->andReturn($toolResult);
    $mockClaude->shouldReceive('getUsage')->andReturn($usage ?? ['input_tokens' => 1000, 'output_tokens' => 500]);

    return $mockClaude;
}

/**
 * Helper to create a mock AI response.
 */
function createMockAiResponse(array $toolInput): array
{
    return [
        'id' => 'msg_test123',
        'type' => 'message',
        'role' => 'assistant',
        'content' => [
            [
                'type' => 'tool_use',
                'id' => 'tool_123',
                'name' => 'create_property',
                'input' => $toolInput,
            ],
        ],
        'model' => 'claude-sonnet-4-20250514',
        'stop_reason' => 'tool_use',
        'usage' => [
            'input_tokens' => 1500,
            'output_tokens' => 800,
        ],
    ];
}

/**
 * Helper to create a valid tool input for testing.
 */
function createValidToolInput(array $overrides = []): array
{
    return array_merge([
        'description' => 'Hermoso departamento en Col. Del Valle con excelentes amenidades.',
        'unified_fields' => [
            'address' => 'Calle Insurgentes Sur No. 123',
            'colonia' => 'Del Valle',
            'city' => 'Ciudad de México',
            'state' => 'CDMX',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'built_size_m2' => 120,
            'property_type' => 'apartment',
            'amenities' => ['swimming_pool', 'gym', '24_hour_security'],
        ],
        'quality_score' => 85,
        'field_sources' => [
            'bedrooms' => ['source' => 'listing:1', 'confidence' => 'high'],
        ],
        'discrepancies' => [],
    ], $overrides);
}

describe('createPropertyFromGroup', function () {
    it('creates a property from a single listing group', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        $listing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
            'dedup_status' => DedupStatus::Grouped,
            'raw_data' => [
                'title' => 'Departamento en Del Valle',
                'description' => 'Hermoso departamento',
                'bedrooms' => 3,
                'bathrooms' => 2,
            ],
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')
            ->once()
            ->andReturn(createMockAiResponse(createValidToolInput()));
        $mockClaude->shouldReceive('extractToolUse')
            ->once()
            ->with(Mockery::any(), 'create_property')
            ->andReturn(createValidToolInput());
        $mockClaude->shouldReceive('getUsage')
            ->once()
            ->andReturn(['input_tokens' => 1500, 'output_tokens' => 800]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);
        $mockGeocoding->shouldReceive('geocode')->andReturn(null);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());
        $property = $service->createPropertyFromGroup($group);

        expect($property)->toBeInstanceOf(Property::class)
            ->and($property->bedrooms)->toBe(3)
            ->and($property->bathrooms)->toBe(2)
            ->and((float) $property->built_size_m2)->toBe(120.0)
            ->and($property->property_type->value)->toBe('apartment')
            ->and($property->confidence_score)->toBe(85)
            ->and($property->status)->toBe(PropertyStatus::Active);

        // Verify listing was linked
        $listing->refresh();
        expect($listing->property_id)->toBe($property->id)
            ->and($listing->dedup_status)->toBe(DedupStatus::Completed);

        // Verify group was completed
        $group->refresh();
        expect($group->status)->toBe(ListingGroupStatus::Completed)
            ->and($group->property_id)->toBe($property->id);
    });

    it('links publishers from listings to the property', function () {
        $publisher = Publisher::factory()->create();
        $group = ListingGroup::factory()->pendingAi()->create();
        $listing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'publisher_id' => $publisher->id,
            'is_primary_in_group' => true,
            'dedup_status' => DedupStatus::Grouped,
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn(createMockAiResponse(createValidToolInput()));
        $mockClaude->shouldReceive('extractToolUse')->andReturn(createValidToolInput());
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 1500, 'output_tokens' => 800]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);
        $mockGeocoding->shouldReceive('geocode')->andReturn(null);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());
        $property = $service->createPropertyFromGroup($group);

        expect($property->publishers)->toHaveCount(1)
            ->and($property->publishers->first()->id)->toBe($publisher->id);
    });

    it('throws exception when group has no listings', function () {
        $group = ListingGroup::factory()->pendingAi()->create();

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockGeocoding = Mockery::mock(GeocodingService::class);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());

        expect(fn () => $service->createPropertyFromGroup($group))
            ->toThrow(InvalidArgumentException::class, 'ListingGroup has no listings');
    });

    it('throws exception when group is not in pending_ai status', function () {
        $group = ListingGroup::factory()->pendingReview()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockGeocoding = Mockery::mock(GeocodingService::class);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());

        expect(fn () => $service->createPropertyFromGroup($group))
            ->toThrow(RuntimeException::class, 'Group is not available for processing');
    });

    it('prevents race conditions with atomic status update', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        // Simulate another process claiming the group
        ListingGroup::where('id', $group->id)->update(['status' => ListingGroupStatus::ProcessingAi]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockGeocoding = Mockery::mock(GeocodingService::class);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());

        expect(fn () => $service->createPropertyFromGroup($group))
            ->toThrow(RuntimeException::class, 'Group is not available for processing');
    });
});

describe('AI response validation', function () {
    it('throws exception when AI does not return tool use', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn([
            'content' => [['type' => 'text', 'text' => 'Sorry, I cannot help with that.']],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]);
        $mockClaude->shouldReceive('extractToolUse')->andReturn(null);
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 100, 'output_tokens' => 50]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());

        expect(fn () => $service->createPropertyFromGroup($group))
            ->toThrow(RuntimeException::class, 'AI did not return structured property data');
    });

    it('throws exception when unified_fields is missing', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $invalidToolInput = [
            'description' => 'Test',
            'quality_score' => 80,
            // Missing unified_fields
        ];

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn(createMockAiResponse($invalidToolInput));
        $mockClaude->shouldReceive('extractToolUse')->andReturn($invalidToolInput);
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 100, 'output_tokens' => 50]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());

        expect(fn () => $service->createPropertyFromGroup($group))
            ->toThrow(RuntimeException::class, 'AI response missing or invalid unified_fields');
    });

    it('throws exception when quality_score is missing', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $invalidToolInput = [
            'description' => 'Test',
            'unified_fields' => ['bedrooms' => 2],
            // Missing quality_score
        ];

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn(createMockAiResponse($invalidToolInput));
        $mockClaude->shouldReceive('extractToolUse')->andReturn($invalidToolInput);
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 100, 'output_tokens' => 50]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());

        expect(fn () => $service->createPropertyFromGroup($group))
            ->toThrow(RuntimeException::class, 'AI response missing or invalid quality_score');
    });

    it('throws exception when description is missing', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $invalidToolInput = [
            'unified_fields' => ['bedrooms' => 2],
            'quality_score' => 80,
            // Missing description
        ];

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn(createMockAiResponse($invalidToolInput));
        $mockClaude->shouldReceive('extractToolUse')->andReturn($invalidToolInput);
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 100, 'output_tokens' => 50]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());

        expect(fn () => $service->createPropertyFromGroup($group))
            ->toThrow(RuntimeException::class, 'AI response missing or invalid description');
    });
});

describe('error handling', function () {
    it('resets group to pending_ai on rate limit error', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')
            ->andThrow(new RuntimeException('Error: 429 Too Many Requests'));

        $mockGeocoding = Mockery::mock(GeocodingService::class);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());

        expect(fn () => $service->createPropertyFromGroup($group))
            ->toThrow(RuntimeException::class, '429');

        $group->refresh();
        expect($group->status)->toBe(ListingGroupStatus::PendingAi);
    });

    it('sets group to pending_review on other errors', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')
            ->andThrow(new RuntimeException('Connection timeout'));

        $mockGeocoding = Mockery::mock(GeocodingService::class);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());

        expect(fn () => $service->createPropertyFromGroup($group))
            ->toThrow(RuntimeException::class);

        $group->refresh();
        expect($group->status)->toBe(ListingGroupStatus::PendingReview)
            ->and($group->rejection_reason)->toContain('AI processing failed');
    });
});

describe('enum sanitization', function () {
    it('removes invalid property_subtype values', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $toolInput = createValidToolInput([
            'unified_fields' => [
                'address' => 'Calle Test No. 123',
                'colonia' => 'Del Valle',
                'city' => 'Ciudad de México',
                'state' => 'CDMX',
                'bedrooms' => 2,
                'property_type' => 'apartment',
                'property_subtype' => 'invalid_subtype', // Invalid enum value
            ],
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn(createMockAiResponse($toolInput));
        $mockClaude->shouldReceive('extractToolUse')->andReturn($toolInput);
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 1500, 'output_tokens' => 800]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);
        $mockGeocoding->shouldReceive('geocode')->andReturn(null);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());
        $property = $service->createPropertyFromGroup($group);

        // Property subtype should be null since invalid value was removed
        expect($property->property_subtype)->toBeNull();
    });

    it('preserves valid property_type values', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $toolInput = createValidToolInput([
            'unified_fields' => [
                'address' => 'Calle Test No. 123',
                'colonia' => 'Del Valle',
                'city' => 'Ciudad de México',
                'state' => 'CDMX',
                'bedrooms' => 2,
                'property_type' => 'house', // Valid enum value
            ],
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn(createMockAiResponse($toolInput));
        $mockClaude->shouldReceive('extractToolUse')->andReturn($toolInput);
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 1500, 'output_tokens' => 800]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);
        $mockGeocoding->shouldReceive('geocode')->andReturn(null);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());
        $property = $service->createPropertyFromGroup($group);

        expect($property->property_type->value)->toBe('house');
    });
});

describe('geocoding', function () {
    it('uses geocoding service when geocoding_address is provided', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $toolInput = createValidToolInput([
            'geocoding_address' => 'Calle Insurgentes Sur 123, Del Valle, CDMX',
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn(createMockAiResponse($toolInput));
        $mockClaude->shouldReceive('extractToolUse')->andReturn($toolInput);
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 1500, 'output_tokens' => 800]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);
        $mockGeocoding->shouldReceive('geocode')
            ->once()
            ->with('Calle Insurgentes Sur 123, Del Valle, CDMX', 'Ciudad de México', 'CDMX')
            ->andReturn(['lat' => 19.3875, 'lng' => -99.1769]);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());
        $property = $service->createPropertyFromGroup($group);

        expect((float) $property->latitude)->toBe(19.3875)
            ->and((float) $property->longitude)->toBe(-99.1769);
    });

    it('falls back to listing coordinates when geocoding fails', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
            'raw_data' => [
                'latitude' => 19.4000,
                'longitude' => -99.1500,
            ],
        ]);

        $toolInput = createValidToolInput([
            'geocoding_address' => 'Invalid Address',
            'unified_fields' => [
                'address' => 'Calle Test No. 123',
                'colonia' => 'Del Valle',
                'city' => 'Ciudad de México',
                'state' => 'CDMX',
                'property_type' => 'apartment',
                'bedrooms' => 2,
                // No coordinates in unified_fields
            ],
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn(createMockAiResponse($toolInput));
        $mockClaude->shouldReceive('extractToolUse')->andReturn($toolInput);
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 1500, 'output_tokens' => 800]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);
        $mockGeocoding->shouldReceive('geocode')->andReturn(null);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());
        $property = $service->createPropertyFromGroup($group);

        expect((float) $property->latitude)->toBe(19.4)
            ->and((float) $property->longitude)->toBe(-99.15);
    });
});

describe('AI metadata', function () {
    it('stores AI unification metadata on the property', function () {
        $group = ListingGroup::factory()->pendingAi()->create();
        $listing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
        ]);

        $toolInput = createValidToolInput([
            'field_sources' => [
                'bedrooms' => ['source' => 'listing:1', 'confidence' => 'high'],
                'address' => ['source' => 'listing:1', 'confidence' => 'medium'],
            ],
            'discrepancies' => [
                [
                    'field' => 'bathrooms',
                    'values' => [
                        ['listing_id' => 1, 'value' => 2],
                        ['listing_id' => 2, 'value' => 3],
                    ],
                    'resolved_value' => 2,
                    'reasoning' => 'Description mentions 2 bathrooms',
                ],
            ],
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')->andReturn(createMockAiResponse($toolInput));
        $mockClaude->shouldReceive('extractToolUse')->andReturn($toolInput);
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 1500, 'output_tokens' => 800]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);
        $mockGeocoding->shouldReceive('geocode')->andReturn(null);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());
        $property = $service->createPropertyFromGroup($group);

        expect($property->ai_unification)->toBeArray()
            ->and($property->ai_unification['version'])->toBe(2)
            ->and($property->ai_unification['input_tokens'])->toBe(1500)
            ->and($property->ai_unification['output_tokens'])->toBe(800)
            ->and($property->ai_unification['field_sources'])->toHaveKey('bedrooms')
            ->and($property->ai_unified_at)->not->toBeNull()
            ->and($property->needs_reanalysis)->toBeFalse()
            ->and($property->discrepancies)->toHaveCount(1);
    });
});

describe('multiple listings unification', function () {
    it('handles multiple listings from different platforms', function () {
        $platform2 = Platform::factory()->create(['name' => 'Vivanuncios']);

        $group = ListingGroup::factory()->pendingAi()->create();

        $listing1 = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => true,
            'dedup_status' => DedupStatus::Grouped,
            'raw_data' => [
                'title' => 'Depa en Del Valle',
                'bedrooms' => 3,
            ],
        ]);

        $listing2 = Listing::factory()->create([
            'platform_id' => $platform2->id,
            'listing_group_id' => $group->id,
            'is_primary_in_group' => false,
            'dedup_status' => DedupStatus::Grouped,
            'raw_data' => [
                'title' => 'Departamento Del Valle',
                'bedrooms' => 3,
            ],
        ]);

        $mockClaude = Mockery::mock(ClaudeClient::class);
        $mockClaude->shouldReceive('withTracking')->andReturnSelf();
        $mockClaude->shouldReceive('message')
            ->once()
            ->andReturn(createMockAiResponse(createValidToolInput()));
        $mockClaude->shouldReceive('extractToolUse')->andReturn(createValidToolInput());
        $mockClaude->shouldReceive('getUsage')->andReturn(['input_tokens' => 2000, 'output_tokens' => 900]);

        $mockGeocoding = Mockery::mock(GeocodingService::class);
        $mockGeocoding->shouldReceive('geocode')->andReturn(null);

        $service = new PropertyCreationService($mockClaude, $mockGeocoding, createMockUsageTracker());
        $property = $service->createPropertyFromGroup($group);

        // Both listings should be linked to the property
        $listing1->refresh();
        $listing2->refresh();

        expect($listing1->property_id)->toBe($property->id)
            ->and($listing2->property_id)->toBe($property->id)
            ->and($listing1->dedup_status)->toBe(DedupStatus::Completed)
            ->and($listing2->dedup_status)->toBe(DedupStatus::Completed);

        // AI metadata should include both sources
        expect($property->ai_unification['sources'])->toHaveCount(2);
    });
});
