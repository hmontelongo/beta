<?php

namespace App\Services\Dedup;

use App\Enums\DedupCandidateStatus;
use App\Models\DedupCandidate;
use App\Models\Listing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CandidateMatcherService
{
    protected float $distanceThreshold;

    protected float $autoMatchThreshold;

    protected float $reviewThreshold;

    public function __construct()
    {
        $this->distanceThreshold = config('services.dedup.distance_threshold_meters', 100);
        $this->autoMatchThreshold = config('services.dedup.auto_match_threshold', 0.9);
        $this->reviewThreshold = config('services.dedup.review_threshold', 0.6);
    }

    /**
     * Find potential duplicate candidates for a listing.
     * Only uses coordinate-based matching to avoid excessive false positives.
     *
     * @return Collection<int, DedupCandidate>
     */
    public function findCandidates(Listing $listing): Collection
    {
        $candidates = collect();

        // Use direct columns for coordinates (populated by geocoding job)
        $latitude = $listing->latitude;
        $longitude = $listing->longitude;

        // Only match by coordinates - address-only matching creates too many false positives
        // (same colonia doesn't mean same property)
        if ($latitude && $longitude) {
            $nearbyListings = $this->findByCoordinates($listing, (float) $latitude, (float) $longitude);

            foreach ($nearbyListings as $candidate) {
                $dedupCandidate = $this->createCandidate($listing, $candidate);
                if ($dedupCandidate) {
                    $candidates->push($dedupCandidate);
                }
            }
        }

        return $candidates;
    }

    /**
     * Find listings near given coordinates using Haversine formula.
     *
     * @return Collection<int, Listing>
     */
    protected function findByCoordinates(Listing $listing, float $lat, float $lng): Collection
    {
        // Haversine formula in raw SQL for distance calculation
        $earthRadius = 6371000; // meters

        return Listing::query()
            ->where('id', '!=', $listing->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("*, (
                {$earthRadius} * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance_meters", [$lat, $lng, $lat])
            ->having('distance_meters', '<=', $this->distanceThreshold)
            ->orderBy('distance_meters')
            ->limit(10)
            ->get();
    }

    /**
     * Create or retrieve a dedup candidate record with calculated scores.
     */
    protected function createCandidate(Listing $listingA, Listing $listingB): ?DedupCandidate
    {
        // Ensure consistent ordering (lower ID first)
        if ($listingA->id > $listingB->id) {
            [$listingA, $listingB] = [$listingB, $listingA];
        }

        // Check if candidate pair already exists - return it if so
        $existing = DedupCandidate::where('listing_a_id', $listingA->id)
            ->where('listing_b_id', $listingB->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $rawDataA = $listingA->raw_data ?? [];
        $rawDataB = $listingB->raw_data ?? [];

        // Early rejection - don't create candidate if obviously different
        if (! $this->shouldCreateCandidate($rawDataA, $rawDataB)) {
            return null;
        }

        // Calculate scores (pass listing models for direct column access to coordinates)
        $coordinateScore = $this->calculateCoordinateScore($rawDataA, $rawDataB, $listingA, $listingB);
        $addressScore = $this->calculateAddressScore($rawDataA, $rawDataB);
        $featuresScore = $this->calculateFeaturesScore($rawDataA, $rawDataB);
        $distanceMeters = $this->calculateDistance($rawDataA, $rawDataB, $listingA, $listingB);

        $overallScore = $this->calculateOverallScore([
            'coordinate' => $coordinateScore,
            'address' => $addressScore,
            'features' => $featuresScore,
        ]);

        // Determine status based on overall score
        $status = $this->determineStatus($overallScore);

        $candidate = DedupCandidate::create([
            'listing_a_id' => $listingA->id,
            'listing_b_id' => $listingB->id,
            'status' => $status,
            'distance_meters' => $distanceMeters,
            'coordinate_score' => $coordinateScore,
            'address_score' => $addressScore,
            'features_score' => $featuresScore,
            'overall_score' => $overallScore,
        ]);

        Log::info('Dedup candidate created', [
            'listing_a_id' => $listingA->id,
            'listing_b_id' => $listingB->id,
            'overall_score' => $overallScore,
            'status' => $status->value,
        ]);

        return $candidate;
    }

    /**
     * Check if two listings should even be considered as potential duplicates.
     * Rejects obvious non-matches early to reduce candidate count.
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function shouldCreateCandidate(array $dataA, array $dataB): bool
    {
        // Property type must match (if both have it)
        if (! empty($dataA['property_type']) && ! empty($dataB['property_type'])) {
            if ($dataA['property_type'] !== $dataB['property_type']) {
                return false;
            }
        }

        // Operation type must match - rent vs sale are different listings
        if (! $this->hasMatchingOperationType($dataA, $dataB)) {
            return false;
        }

        // Price difference check - reject if prices differ by more than 20%
        $priceDiff = $this->getPriceDifferenceRatio($dataA, $dataB);
        if ($priceDiff !== null && $priceDiff > 0.20) {
            return false;
        }

        // Size difference check - reject if sizes differ by more than 20%
        $sizeDiff = $this->getSizeDifferenceRatio($dataA, $dataB);
        if ($sizeDiff !== null && $sizeDiff > 0.20) {
            return false;
        }

        return true;
    }

    /**
     * Check if two listings have at least one matching operation type.
     * Rent vs Sale listings are definitely not the same.
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function hasMatchingOperationType(array $dataA, array $dataB): bool
    {
        $opsA = $dataA['operations'] ?? [];
        $opsB = $dataB['operations'] ?? [];

        // If either has no operations, can't determine - allow comparison
        if (empty($opsA) || empty($opsB)) {
            return true;
        }

        $typesA = array_column($opsA, 'type');
        $typesB = array_column($opsB, 'type');

        // Must have at least one matching operation type
        return ! empty(array_intersect($typesA, $typesB));
    }

    /**
     * Get the price difference ratio between two listings.
     * Returns null if prices can't be compared.
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function getPriceDifferenceRatio(array $dataA, array $dataB): ?float
    {
        $opsA = $dataA['operations'] ?? [];
        $opsB = $dataB['operations'] ?? [];

        if (empty($opsA) || empty($opsB)) {
            return null;
        }

        // Compare matching operation types
        foreach ($opsA as $opA) {
            foreach ($opsB as $opB) {
                if (($opA['type'] ?? '') === ($opB['type'] ?? '') &&
                    ($opA['currency'] ?? 'MXN') === ($opB['currency'] ?? 'MXN')) {
                    $priceA = (float) ($opA['price'] ?? 0);
                    $priceB = (float) ($opB['price'] ?? 0);

                    if ($priceA > 0 && $priceB > 0) {
                        return abs($priceA - $priceB) / max($priceA, $priceB);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get the size difference ratio between two listings.
     * Returns null if sizes can't be compared.
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function getSizeDifferenceRatio(array $dataA, array $dataB): ?float
    {
        // Try built size first
        $sizeA = (float) ($dataA['built_size_m2'] ?? 0);
        $sizeB = (float) ($dataB['built_size_m2'] ?? 0);

        // Fall back to lot size if no built size
        if ($sizeA <= 0 || $sizeB <= 0) {
            $sizeA = (float) ($dataA['lot_size_m2'] ?? 0);
            $sizeB = (float) ($dataB['lot_size_m2'] ?? 0);
        }

        if ($sizeA <= 0 || $sizeB <= 0) {
            return null;
        }

        return abs($sizeA - $sizeB) / max($sizeA, $sizeB);
    }

    /**
     * Calculate coordinate-based similarity score (0-1).
     * Uses exponential decay - score drops quickly for nearby, slower for far.
     * This accounts for imprecise geocoding where coordinates can be 500m+ off.
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     * @param  Listing|null  $listingA  Optional listing model for direct column access
     * @param  Listing|null  $listingB  Optional listing model for direct column access
     */
    protected function calculateCoordinateScore(array $dataA, array $dataB, ?Listing $listingA = null, ?Listing $listingB = null): float
    {
        $distance = $this->calculateDistance($dataA, $dataB, $listingA, $listingB);

        if ($distance === null) {
            return 0.5; // Unknown - neutral score
        }

        // Perfect match at 0 meters
        if ($distance <= 10) {
            return 1.0;
        }

        // Use exponential decay: e^(-distance/halflife)
        // At 200m: ~0.61, at 500m: ~0.29, at 1000m: ~0.08
        $halfLife = 300; // Distance at which score is ~0.37

        return exp(-$distance / $halfLife);
    }

    /**
     * Calculate address similarity score (0-1).
     * Street address is weighted highest - it's the most specific identifier.
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function calculateAddressScore(array $dataA, array $dataB): float
    {
        $score = 0.0;
        $totalWeight = 0.0;

        // Street address is most important, then colonia, city, state
        $weights = [
            'address' => 0.40,
            'colonia' => 0.30,
            'city' => 0.20,
            'state' => 0.10,
        ];

        foreach ($weights as $field => $weight) {
            $valueA = $this->normalizeAddress($dataA[$field] ?? '');
            $valueB = $this->normalizeAddress($dataB[$field] ?? '');

            if (empty($valueA) || empty($valueB)) {
                continue;
            }

            $totalWeight += $weight;
            $similarity = $this->stringSimilarity($valueA, $valueB);
            $score += $similarity * $weight;
        }

        // Normalize by actual weight used (in case some fields missing)
        if ($totalWeight === 0.0) {
            return 0.0;
        }

        return min(1.0, $score / $totalWeight);
    }

    /**
     * Calculate features similarity score (0-1).
     * Compares bedrooms, bathrooms, size, property type, and price.
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function calculateFeaturesScore(array $dataA, array $dataB): float
    {
        $matches = 0;
        $total = 0;

        // Property type (exact match required)
        if (! empty($dataA['property_type']) && ! empty($dataB['property_type'])) {
            $total++;
            if ($dataA['property_type'] === $dataB['property_type']) {
                $matches++;
            }
        }

        // Bedrooms (exact match)
        if (isset($dataA['bedrooms']) && isset($dataB['bedrooms'])) {
            $total++;
            if ((int) $dataA['bedrooms'] === (int) $dataB['bedrooms']) {
                $matches++;
            }
        }

        // Bathrooms (within 1)
        if (isset($dataA['bathrooms']) && isset($dataB['bathrooms'])) {
            $total++;
            if (abs((int) $dataA['bathrooms'] - (int) $dataB['bathrooms']) <= 1) {
                $matches++;
            }
        }

        // Built size (within 10%)
        if (isset($dataA['built_size_m2']) && isset($dataB['built_size_m2'])) {
            $sizeA = (float) $dataA['built_size_m2'];
            $sizeB = (float) $dataB['built_size_m2'];

            if ($sizeA > 0 && $sizeB > 0) {
                $total++;
                $difference = abs($sizeA - $sizeB) / max($sizeA, $sizeB);
                if ($difference <= 0.1) {
                    $matches++;
                }
            }
        }

        // Lot size (within 10%)
        if (isset($dataA['lot_size_m2']) && isset($dataB['lot_size_m2'])) {
            $sizeA = (float) $dataA['lot_size_m2'];
            $sizeB = (float) $dataB['lot_size_m2'];

            if ($sizeA > 0 && $sizeB > 0) {
                $total++;
                $difference = abs($sizeA - $sizeB) / max($sizeA, $sizeB);
                if ($difference <= 0.1) {
                    $matches++;
                }
            }
        }

        // Price comparison (within 5% for same currency/operation)
        $priceMatch = $this->comparePrices($dataA, $dataB);
        if ($priceMatch !== null) {
            $total++;
            if ($priceMatch) {
                $matches++;
            }
        }

        if ($total === 0) {
            return 0.5; // Neutral score if no features to compare
        }

        return $matches / $total;
    }

    /**
     * Compare prices between two listings.
     * Returns true if prices match (within 5%), false if different, null if can't compare.
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function comparePrices(array $dataA, array $dataB): ?bool
    {
        $opsA = $dataA['operations'] ?? [];
        $opsB = $dataB['operations'] ?? [];

        if (empty($opsA) || empty($opsB)) {
            return null;
        }

        // Compare matching operation types
        foreach ($opsA as $opA) {
            foreach ($opsB as $opB) {
                // Same operation type and currency
                if (($opA['type'] ?? '') === ($opB['type'] ?? '') &&
                    ($opA['currency'] ?? 'MXN') === ($opB['currency'] ?? 'MXN')) {
                    $priceA = (float) ($opA['price'] ?? 0);
                    $priceB = (float) ($opB['price'] ?? 0);

                    if ($priceA > 0 && $priceB > 0) {
                        $difference = abs($priceA - $priceB) / max($priceA, $priceB);

                        return $difference <= 0.05; // Within 5%
                    }
                }
            }
        }

        return null;
    }

    /**
     * Calculate overall weighted score.
     *
     * Features are most reliable - bedrooms, bathrooms, price, size are unique to a unit.
     * Coordinates help with geographic clustering but are often imprecise.
     * Address is least reliable - same building different units, or enrichment-generated.
     *
     * @param  array{coordinate: float, address: float, features: float}  $scores
     */
    public function calculateOverallScore(array $scores): float
    {
        $weights = [
            'coordinate' => 0.20,
            'address' => 0.15,
            'features' => 0.65,
        ];

        $weighted = 0.0;
        foreach ($scores as $key => $score) {
            $weighted += $score * ($weights[$key] ?? 0);
        }

        return round($weighted, 4);
    }

    /**
     * Calculate distance between two listings using Haversine formula.
     *
     * @param  array<string, mixed>  $dataA  Raw data from listing A
     * @param  array<string, mixed>  $dataB  Raw data from listing B
     * @param  Listing|null  $listingA  Optional listing model for direct column access
     * @param  Listing|null  $listingB  Optional listing model for direct column access
     */
    protected function calculateDistance(array $dataA, array $dataB, ?Listing $listingA = null, ?Listing $listingB = null): ?float
    {
        // Prefer direct columns over raw_data
        $latA = $listingA?->latitude ?? $dataA['latitude'] ?? null;
        $lngA = $listingA?->longitude ?? $dataA['longitude'] ?? null;
        $latB = $listingB?->latitude ?? $dataB['latitude'] ?? null;
        $lngB = $listingB?->longitude ?? $dataB['longitude'] ?? null;

        if (! $latA || ! $lngA || ! $latB || ! $lngB) {
            return null;
        }

        $earthRadius = 6371000; // meters

        $latDiff = deg2rad((float) $latB - (float) $latA);
        $lngDiff = deg2rad((float) $lngB - (float) $lngA);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad((float) $latA)) * cos(deg2rad((float) $latB)) *
             sin($lngDiff / 2) * sin($lngDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Normalize address string for comparison.
     */
    protected function normalizeAddress(string $value): string
    {
        // Lowercase and remove accents
        $value = mb_strtolower($value);
        $value = $this->removeAccents($value);

        // Remove common prefixes/suffixes
        $remove = ['col.', 'col ', 'colonia ', 'fracc.', 'fracc ', 'fraccionamiento '];
        $value = str_ireplace($remove, '', $value);

        // Remove extra whitespace
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Remove accents from string.
     */
    protected function removeAccents(string $value): string
    {
        $accents = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u',
        ];

        return strtr($value, $accents);
    }

    /**
     * Calculate string similarity (Levenshtein-based, normalized).
     */
    protected function stringSimilarity(string $a, string $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $maxLen = max(strlen($a), strlen($b));
        $distance = levenshtein($a, $b);

        return 1.0 - ($distance / $maxLen);
    }

    /**
     * Determine dedup status based on overall score.
     */
    protected function determineStatus(float $score): DedupCandidateStatus
    {
        if ($score >= $this->autoMatchThreshold) {
            return DedupCandidateStatus::ConfirmedMatch;
        }

        if ($score >= $this->reviewThreshold) {
            return DedupCandidateStatus::NeedsReview;
        }

        return DedupCandidateStatus::ConfirmedDifferent;
    }
}
