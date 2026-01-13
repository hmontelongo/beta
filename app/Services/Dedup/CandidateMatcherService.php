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
     *
     * @return Collection<int, DedupCandidate>
     */
    public function findCandidates(Listing $listing): Collection
    {
        $candidates = collect();

        // Get raw data for coordinates and address info
        $rawData = $listing->raw_data ?? [];
        $latitude = $rawData['latitude'] ?? null;
        $longitude = $rawData['longitude'] ?? null;

        // Find nearby listings by coordinates first
        if ($latitude && $longitude) {
            $nearbyListings = $this->findByCoordinates($listing, $latitude, $longitude);

            foreach ($nearbyListings as $candidate) {
                $dedupCandidate = $this->createCandidate($listing, $candidate);
                if ($dedupCandidate) {
                    $candidates->push($dedupCandidate);
                }
            }
        }

        // Also check by address if we have address info
        if (! empty($rawData['colonia']) || ! empty($rawData['city'])) {
            $addressMatches = $this->findByAddress($listing, $rawData);

            foreach ($addressMatches as $candidate) {
                // Skip if already added via coordinate match
                if ($candidates->contains(fn ($c) => $c->listing_b_id === $candidate->id)) {
                    continue;
                }

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
            ->whereRaw("JSON_EXTRACT(raw_data, '$.latitude') IS NOT NULL")
            ->whereRaw("JSON_EXTRACT(raw_data, '$.longitude') IS NOT NULL")
            ->selectRaw("*, (
                {$earthRadius} * acos(
                    cos(radians(?)) * cos(radians(JSON_EXTRACT(raw_data, '$.latitude'))) *
                    cos(radians(JSON_EXTRACT(raw_data, '$.longitude')) - radians(?)) +
                    sin(radians(?)) * sin(radians(JSON_EXTRACT(raw_data, '$.latitude')))
                )
            ) AS distance_meters", [$lat, $lng, $lat])
            ->having('distance_meters', '<=', $this->distanceThreshold)
            ->orderBy('distance_meters')
            ->limit(10)
            ->get();
    }

    /**
     * Find listings by address similarity.
     *
     * @param  array<string, mixed>  $rawData
     * @return Collection<int, Listing>
     */
    protected function findByAddress(Listing $listing, array $rawData): Collection
    {
        $query = Listing::query()->where('id', '!=', $listing->id);

        // Match by colonia and city (most specific)
        if (! empty($rawData['colonia'])) {
            $colonia = $this->normalizeAddress($rawData['colonia']);
            $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.colonia'))) LIKE ?", ["%{$colonia}%"]);
        }

        if (! empty($rawData['city'])) {
            $city = $this->normalizeAddress($rawData['city']);
            $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.city'))) LIKE ?", ["%{$city}%"]);
        }

        return $query->limit(20)->get();
    }

    /**
     * Create a dedup candidate record with calculated scores.
     */
    protected function createCandidate(Listing $listingA, Listing $listingB): ?DedupCandidate
    {
        // Ensure consistent ordering (lower ID first)
        if ($listingA->id > $listingB->id) {
            [$listingA, $listingB] = [$listingB, $listingA];
        }

        // Check if candidate pair already exists
        $exists = DedupCandidate::where('listing_a_id', $listingA->id)
            ->where('listing_b_id', $listingB->id)
            ->exists();

        if ($exists) {
            return null;
        }

        $rawDataA = $listingA->raw_data ?? [];
        $rawDataB = $listingB->raw_data ?? [];

        // Calculate scores
        $coordinateScore = $this->calculateCoordinateScore($rawDataA, $rawDataB);
        $addressScore = $this->calculateAddressScore($rawDataA, $rawDataB);
        $featuresScore = $this->calculateFeaturesScore($rawDataA, $rawDataB);
        $distanceMeters = $this->calculateDistance($rawDataA, $rawDataB);

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
     * Calculate coordinate-based similarity score (0-1).
     * Score decreases with distance.
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function calculateCoordinateScore(array $dataA, array $dataB): float
    {
        $distance = $this->calculateDistance($dataA, $dataB);

        if ($distance === null) {
            return 0.0;
        }

        // Perfect match at 0 meters, score decreases linearly
        // At threshold distance, score is 0
        if ($distance >= $this->distanceThreshold) {
            return 0.0;
        }

        return 1.0 - ($distance / $this->distanceThreshold);
    }

    /**
     * Calculate address similarity score (0-1).
     *
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function calculateAddressScore(array $dataA, array $dataB): float
    {
        $score = 0.0;
        $weights = [
            'colonia' => 0.4,
            'city' => 0.3,
            'state' => 0.2,
            'address' => 0.1,
        ];

        foreach ($weights as $field => $weight) {
            $valueA = $this->normalizeAddress($dataA[$field] ?? '');
            $valueB = $this->normalizeAddress($dataB[$field] ?? '');

            if (empty($valueA) || empty($valueB)) {
                continue;
            }

            $similarity = $this->stringSimilarity($valueA, $valueB);
            $score += $similarity * $weight;
        }

        return min(1.0, $score);
    }

    /**
     * Calculate features similarity score (0-1).
     * Compares bedrooms, bathrooms, size, property type.
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

        if ($total === 0) {
            return 0.5; // Neutral score if no features to compare
        }

        return $matches / $total;
    }

    /**
     * Calculate overall weighted score.
     *
     * @param  array{coordinate: float, address: float, features: float}  $scores
     */
    public function calculateOverallScore(array $scores): float
    {
        // Weights as defined in the plan
        $weights = [
            'coordinate' => 0.35,
            'address' => 0.25,
            'features' => 0.40,
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
     * @param  array<string, mixed>  $dataA
     * @param  array<string, mixed>  $dataB
     */
    protected function calculateDistance(array $dataA, array $dataB): ?float
    {
        $latA = $dataA['latitude'] ?? null;
        $lngA = $dataA['longitude'] ?? null;
        $latB = $dataB['latitude'] ?? null;
        $lngB = $dataB['longitude'] ?? null;

        if (! $latA || ! $lngA || ! $latB || ! $lngB) {
            return null;
        }

        $earthRadius = 6371000; // meters

        $latDiff = deg2rad($latB - $latA);
        $lngDiff = deg2rad($lngB - $lngA);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
             cos(deg2rad($latA)) * cos(deg2rad($latB)) *
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
