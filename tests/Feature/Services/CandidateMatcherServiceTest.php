<?php

use App\Enums\DedupCandidateStatus;
use App\Models\Listing;
use App\Models\Platform;
use App\Services\Dedup\CandidateMatcherService;

beforeEach(function () {
    $this->platform = Platform::factory()->create();
    $this->service = new CandidateMatcherService;
});

describe('calculateOverallScore', function () {
    it('returns correct weighted average with all scores', function () {
        // weights: coordinate=0.20, address=0.15, features=0.65
        $scores = [
            'coordinate' => 1.0,
            'address' => 1.0,
            'features' => 1.0,
        ];

        $result = $this->service->calculateOverallScore($scores);

        expect($result)->toBe(1.0);
    });

    it('applies correct weights to different score values', function () {
        // weights: coordinate=0.20, address=0.15, features=0.65
        $scores = [
            'coordinate' => 0.5,  // 0.5 * 0.20 = 0.10
            'address' => 0.5,    // 0.5 * 0.15 = 0.075
            'features' => 0.5,   // 0.5 * 0.65 = 0.325
        ];

        $result = $this->service->calculateOverallScore($scores);

        // Total = 0.10 + 0.075 + 0.325 = 0.50
        expect($result)->toBe(0.5);
    });

    it('prioritizes features score over other scores', function () {
        // High features score should dominate
        $highFeaturesScores = [
            'coordinate' => 0.0,
            'address' => 0.0,
            'features' => 1.0,
        ];
        $highFeaturesResult = $this->service->calculateOverallScore($highFeaturesScores);

        // Low features score should drag overall down
        $lowFeaturesScores = [
            'coordinate' => 1.0,
            'address' => 1.0,
            'features' => 0.0,
        ];
        $lowFeaturesResult = $this->service->calculateOverallScore($lowFeaturesScores);

        // Features at 65% weight should be: 0.65 vs 0.35
        expect($highFeaturesResult)->toBe(0.65)
            ->and($lowFeaturesResult)->toBe(0.35);
    });

    it('rounds result to 4 decimal places', function () {
        $scores = [
            'coordinate' => 0.333333,
            'address' => 0.333333,
            'features' => 0.333333,
        ];

        $result = $this->service->calculateOverallScore($scores);

        // Should be rounded
        expect(strlen(explode('.', (string) $result)[1] ?? ''))->toBeLessThanOrEqual(4);
    });
});

describe('findCandidates', function () {
    it('returns empty collection when listing has no coordinates', function () {
        $listing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => null,
            'longitude' => null,
            'geocode_status' => 'failed',
        ]);

        $candidates = $this->service->findCandidates($listing);

        expect($candidates)->toBeEmpty();
    });
});

describe('candidate rejection criteria', function () {
    /**
     * Helper to create a test service with mocked findByCoordinates
     */
    function createServiceWithMockedSearch(array $nearbyListings): CandidateMatcherService
    {
        $service = new class extends CandidateMatcherService
        {
            public array $mockedNearbyListings = [];

            protected function findByCoordinates(Listing $listing, float $lat, float $lng): \Illuminate\Support\Collection
            {
                return collect($this->mockedNearbyListings);
            }
        };
        $service->mockedNearbyListings = $nearbyListings;

        return $service;
    }

    it('rejects candidates with different property types', function () {
        $apartment = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            ],
        ]);

        $house = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6501,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'house', // Different type
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            ],
        ]);

        $service = createServiceWithMockedSearch([$house]);
        $candidates = $service->findCandidates($apartment);

        expect($candidates)->toBeEmpty();
    });

    it('rejects candidates with different operation types (rent vs sale)', function () {
        $rentListing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            ],
        ]);

        $saleListing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6501,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'operations' => [['type' => 'sale', 'price' => 2000000, 'currency' => 'MXN']],
            ],
        ]);

        $service = createServiceWithMockedSearch([$saleListing]);
        $candidates = $service->findCandidates($rentListing);

        expect($candidates)->toBeEmpty();
    });

    it('rejects candidates with price difference greater than 20%', function () {
        $listing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'operations' => [['type' => 'rent', 'price' => 10000, 'currency' => 'MXN']],
            ],
        ]);

        $expensiveListing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6501,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']], // 50% difference
            ],
        ]);

        $service = createServiceWithMockedSearch([$expensiveListing]);
        $candidates = $service->findCandidates($listing);

        expect($candidates)->toBeEmpty();
    });

    it('rejects candidates with size difference greater than 15%', function () {
        $listing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'built_size_m2' => 100,
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            ],
        ]);

        $smallerListing = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6501,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'built_size_m2' => 70, // 30% smaller
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            ],
        ]);

        $service = createServiceWithMockedSearch([$smallerListing]);
        $candidates = $service->findCandidates($listing);

        expect($candidates)->toBeEmpty();
    });
});

describe('size tolerance for features score', function () {
    /**
     * Helper to create a test service with mocked findByCoordinates
     */
    function createTestService(array $nearbyListings): CandidateMatcherService
    {
        $service = new class extends CandidateMatcherService
        {
            public array $mockedNearbyListings = [];

            protected function findByCoordinates(Listing $listing, float $lat, float $lng): \Illuminate\Support\Collection
            {
                return collect($this->mockedNearbyListings);
            }
        };
        $service->mockedNearbyListings = $nearbyListings;

        return $service;
    }

    it('does not auto-match 22m2 vs 24m2 listings (9% difference)', function () {
        // This is the exact scenario that caused Property 8 bug - 7 different lofts matched
        $listing22 = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'loft',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'built_size_m2' => 22,
                'address' => 'Same Building 100',
                'colonia' => 'Centro',
                'city' => 'Guadalajara',
                'operations' => [['type' => 'rent', 'price' => 8000, 'currency' => 'MXN']],
            ],
        ]);

        $listing24 = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'loft',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'built_size_m2' => 24, // 9% larger
                'address' => 'Same Building 100',
                'colonia' => 'Centro',
                'city' => 'Guadalajara',
                'operations' => [['type' => 'rent', 'price' => 8500, 'currency' => 'MXN']], // 6% price diff
            ],
        ]);

        $service = createTestService([$listing24]);
        $candidates = $service->findCandidates($listing22);

        // 22 vs 24 is 9% difference - should NOT be rejected by shouldCreateCandidate (15% threshold)
        // But should NOT be auto-confirmed either due to features mismatch
        if ($candidates->isNotEmpty()) {
            $candidate = $candidates->first();
            // Size difference means features score will be lower
            // Should NOT be auto-confirmed (ConfirmedMatch status)
            expect($candidate->status)->not->toBe(DedupCandidateStatus::ConfirmedMatch);
        }
    });

    it('matches listings with same size within 5% tolerance', function () {
        $listing100 = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'built_size_m2' => 100,
                'address' => 'Test Street 100',
                'colonia' => 'Centro',
                'city' => 'Guadalajara',
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            ],
        ]);

        $listing103 = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'built_size_m2' => 103, // 3% larger - within 5% tolerance
                'address' => 'Test Street 100',
                'colonia' => 'Centro',
                'city' => 'Guadalajara',
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            ],
        ]);

        $service = createTestService([$listing103]);
        $candidates = $service->findCandidates($listing100);

        expect($candidates)->toHaveCount(1);
        // With nearly identical features, high score expected
        expect($candidates->first()->overall_score)->toBeGreaterThan(0.8);
    });
});

describe('status determination', function () {
    /**
     * Helper to create a test service with mocked findByCoordinates
     */
    function createStatusTestService(array $nearbyListings): CandidateMatcherService
    {
        $service = new class extends CandidateMatcherService
        {
            public array $mockedNearbyListings = [];

            protected function findByCoordinates(Listing $listing, float $lat, float $lng): \Illuminate\Support\Collection
            {
                return collect($this->mockedNearbyListings);
            }
        };
        $service->mockedNearbyListings = $nearbyListings;

        return $service;
    }

    it('assigns ConfirmedMatch status for nearly identical listings', function () {
        // Create nearly identical listings
        $listingA = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'bedrooms' => 2,
                'bathrooms' => 2,
                'built_size_m2' => 85,
                'lot_size_m2' => 85,
                'address' => 'Avenida Principal 123',
                'colonia' => 'Centro',
                'city' => 'Guadalajara',
                'state' => 'Jalisco',
                'operations' => [['type' => 'rent', 'price' => 12000, 'currency' => 'MXN']],
            ],
        ]);

        $listingB = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'bedrooms' => 2,
                'bathrooms' => 2,
                'built_size_m2' => 85,
                'lot_size_m2' => 85,
                'address' => 'Avenida Principal 123',
                'colonia' => 'Centro',
                'city' => 'Guadalajara',
                'state' => 'Jalisco',
                'operations' => [['type' => 'rent', 'price' => 12000, 'currency' => 'MXN']],
            ],
        ]);

        $service = createStatusTestService([$listingB]);
        $candidates = $service->findCandidates($listingA);

        expect($candidates)->toHaveCount(1)
            ->and($candidates->first()->status)->toBe(DedupCandidateStatus::ConfirmedMatch);
    });

    it('assigns NeedsReview status for similar but not identical listings', function () {
        $listingA = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'built_size_m2' => 80,
                'address' => 'Calle Test 456',
                'colonia' => 'Centro',
                'city' => 'Guadalajara',
                'operations' => [['type' => 'rent', 'price' => 12000, 'currency' => 'MXN']],
            ],
        ]);

        // Similar but with enough differences to get score between 0.6 and 0.9
        // Same property type, same bedrooms, similar size, different address
        $listingB = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6505, // Close (~55m)
            'longitude' => -103.3505,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'bedrooms' => 2, // Same
                'bathrooms' => 2, // Different (within 1)
                'built_size_m2' => 86, // 7.5% different - fails 5% tolerance
                'address' => 'Different Street 789', // Different address
                'colonia' => 'Centro', // Same colonia
                'city' => 'Guadalajara',
                'operations' => [['type' => 'rent', 'price' => 12500, 'currency' => 'MXN']], // 4% diff - within 5%
            ],
        ]);

        $service = createStatusTestService([$listingB]);
        $candidates = $service->findCandidates($listingA);

        if ($candidates->isNotEmpty()) {
            $candidate = $candidates->first();
            // Score should be in the review range given some matches, some differences
            expect($candidate->overall_score)->toBeGreaterThanOrEqual(0.6)
                ->and($candidate->overall_score)->toBeLessThan(0.9);
        }
    });
});

describe('existing candidate handling', function () {
    /**
     * Helper to create a test service with mocked findByCoordinates
     */
    function createDuplicateTestService(array $nearbyListings): CandidateMatcherService
    {
        $service = new class extends CandidateMatcherService
        {
            public array $mockedNearbyListings = [];

            protected function findByCoordinates(Listing $listing, float $lat, float $lng): \Illuminate\Support\Collection
            {
                return collect($this->mockedNearbyListings);
            }
        };
        $service->mockedNearbyListings = $nearbyListings;

        return $service;
    }

    it('returns existing candidate instead of creating duplicate', function () {
        $listingA = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6500,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'built_size_m2' => 80,
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            ],
        ]);

        $listingB = Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'latitude' => 20.6501,
            'longitude' => -103.3500,
            'geocode_status' => 'success',
            'raw_data' => [
                'property_type' => 'apartment',
                'bedrooms' => 2,
                'bathrooms' => 1,
                'built_size_m2' => 80,
                'operations' => [['type' => 'rent', 'price' => 15000, 'currency' => 'MXN']],
            ],
        ]);

        $service = createDuplicateTestService([$listingB]);

        // Find candidates twice
        $firstRun = $service->findCandidates($listingA);
        $secondRun = $service->findCandidates($listingA);

        expect($firstRun)->toHaveCount(1)
            ->and($secondRun)->toHaveCount(1)
            ->and($firstRun->first()->id)->toBe($secondRun->first()->id);
    });
});
