<?php

namespace App\Services;

use App\Enums\PublisherType;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\Publisher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PublisherExtractionService
{
    /**
     * Publisher types from platforms that indicate an agency.
     *
     * @var array<string>
     */
    protected const AGENCY_TYPES = ['agency', 'real_estate', 'inmobiliaria', 'realestate', 'developer'];

    /**
     * Name patterns that indicate an agency.
     *
     * @var array<string>
     */
    protected const AGENCY_NAME_INDICATORS = [
        'inmobiliaria', 'real estate', 'realty', 'properties', 'desarrollos',
        'grupo', 'bienes raices', 's.a.', 's.c.', 's. de r.l.', 'corp',
        'inc.', 'llc', 'ltd',
    ];

    /**
     * Name patterns that indicate a developer.
     *
     * @var array<string>
     */
    protected const DEVELOPER_NAME_INDICATORS = [
        'desarrollos', 'desarrolladora', 'construcciones', 'constructora',
    ];

    /**
     * Extract publisher from listing raw_data and link to the listing.
     */
    public function extractFromListing(Listing $listing): void
    {
        $rawData = $listing->raw_data ?? [];
        $publisherName = $rawData['publisher_name'] ?? null;
        $publisherId = $rawData['publisher_id'] ?? null;
        $phone = $this->normalizePhone($rawData['whatsapp'] ?? null);

        // Allow extraction if we have name OR platform ID OR phone
        if (empty($publisherName) && empty($publisherId) && empty($phone)) {
            return;
        }

        $platform = $listing->platform;

        if (! $platform) {
            Log::warning('PublisherExtractionService: Listing has no platform', [
                'listing_id' => $listing->id,
            ]);

            return;
        }

        $publisher = $this->findOrCreatePublisher($rawData, $platform);
        $listing->publisher_id = $publisher->id;
        $listing->save();

        Log::debug('PublisherExtractionService: Linked publisher to listing', [
            'listing_id' => $listing->id,
            'publisher_id' => $publisher->id,
            'publisher_name' => $publisher->name,
        ]);
    }

    /**
     * Process all existing listings that have publisher data but no publisher linked.
     */
    public function processUnlinkedListings(int $limit = 100): int
    {
        $processed = 0;

        // Find listings with any publisher identifier (name, platform ID, or phone)
        $listings = Listing::query()
            ->with('platform')
            ->whereNull('publisher_id')
            ->whereNotNull('raw_data')
            ->where(function ($query) {
                $query->whereRaw("JSON_EXTRACT(raw_data, '$.publisher_name') IS NOT NULL")
                    ->orWhereRaw("JSON_EXTRACT(raw_data, '$.publisher_id') IS NOT NULL")
                    ->orWhereRaw("JSON_EXTRACT(raw_data, '$.whatsapp') IS NOT NULL");
            })
            ->limit($limit)
            ->get();

        foreach ($listings as $listing) {
            $this->extractFromListing($listing);
            $processed++;
        }

        return $processed;
    }

    /**
     * Find or create a publisher from listing data.
     *
     * @param  array<string, mixed>  $rawData
     */
    protected function findOrCreatePublisher(array $rawData, Platform $platform): Publisher
    {
        $publisherId = $rawData['publisher_id'] ?? null;
        $phone = $this->normalizePhone($rawData['whatsapp'] ?? null);
        $providedName = trim($rawData['publisher_name'] ?? '');
        $name = $providedName ?: $this->generatePlaceholderName($publisherId, $phone, $platform);

        $publisher = $this->findExistingPublisher($publisherId, $phone, $name, $platform);

        if ($publisher) {
            return $this->updatePlatformProfile($publisher, $rawData, $platform, $phone);
        }

        $publisherType = $rawData['publisher_type'] ?? null;

        return Publisher::create([
            'name' => $name,
            'type' => $this->determineType($publisherType, $name),
            'phone' => $phone,
            'whatsapp' => $phone,
            'platform_profiles' => [
                $platform->slug => $this->buildPlatformProfile($rawData),
            ],
        ]);
    }

    /**
     * Generate a placeholder name when no publisher name is provided.
     */
    protected function generatePlaceholderName(?string $publisherId, ?string $phone, Platform $platform): string
    {
        if ($publisherId) {
            return "Publisher #{$publisherId} ({$platform->name})";
        }

        if ($phone) {
            return "Publisher {$phone}";
        }

        return "Unknown Publisher ({$platform->name})";
    }

    /**
     * Find an existing publisher by platform ID, phone, or name.
     */
    protected function findExistingPublisher(
        ?string $publisherId,
        ?string $phone,
        string $name,
        Platform $platform
    ): ?Publisher {
        // First try to match by platform-specific publisher ID
        if ($publisherId) {
            $publisher = Publisher::where("platform_profiles->{$platform->slug}->id", (string) $publisherId)->first();
            if ($publisher) {
                return $publisher;
            }
        }

        // Then try to match by phone or WhatsApp
        if ($phone) {
            $publisher = Publisher::where('phone', $phone)->orWhere('whatsapp', $phone)->first();
            if ($publisher) {
                return $publisher;
            }
        }

        // Finally try to match by name (case-insensitive)
        return Publisher::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }

    /**
     * Determine the publisher type based on platform data and name.
     */
    protected function determineType(?string $platformType, string $name): PublisherType
    {
        $nameLower = strtolower($name);

        // Check if platform explicitly says it's an agency
        if ($platformType && in_array(strtolower($platformType), self::AGENCY_TYPES, true)) {
            // But check if it's actually a developer
            if (Str::contains($nameLower, self::DEVELOPER_NAME_INDICATORS)) {
                return PublisherType::Developer;
            }

            return PublisherType::Agency;
        }

        // Check name for developer indicators first (more specific)
        if (Str::contains($nameLower, self::DEVELOPER_NAME_INDICATORS)) {
            return PublisherType::Developer;
        }

        // Check name for agency indicators
        if (Str::contains($nameLower, self::AGENCY_NAME_INDICATORS)) {
            return PublisherType::Agency;
        }

        // If name looks like a person name (has spaces, no company indicators), it's individual
        if (preg_match('/^[A-Za-zÀ-ÿ]+\s+[A-Za-zÀ-ÿ]+/', $name)) {
            return PublisherType::Individual;
        }

        return PublisherType::Unknown;
    }

    /**
     * Update a publisher's platform profile with new data.
     *
     * @param  array<string, mixed>  $rawData
     */
    protected function updatePlatformProfile(
        Publisher $publisher,
        array $rawData,
        Platform $platform,
        ?string $phone
    ): Publisher {
        $profiles = $publisher->platform_profiles ?? [];
        $profiles[$platform->slug] = $this->buildPlatformProfile($rawData);

        $publisher->update([
            'platform_profiles' => $profiles,
            'phone' => $publisher->phone ?: $phone,
            'whatsapp' => $publisher->whatsapp ?: $phone,
        ]);

        return $publisher;
    }

    /**
     * Build a platform profile array from raw data.
     *
     * @param  array<string, mixed>  $rawData
     * @return array{id: string|null, url: string|null, logo: string|null, scraped_at: string}
     */
    protected function buildPlatformProfile(array $rawData): array
    {
        return [
            'id' => $rawData['publisher_id'] ?? null,
            'url' => $rawData['publisher_url'] ?? null,
            'logo' => $rawData['publisher_logo'] ?? null,
            'scraped_at' => now()->toISOString(),
        ];
    }

    /**
     * Normalize a phone number by removing formatting.
     */
    protected function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $normalized = preg_replace('/[^\d+]/', '', $phone);

        if ($normalized && ! str_starts_with($normalized, '+') && strlen($normalized) > 10) {
            $normalized = '+'.$normalized;
        }

        return $normalized ?: null;
    }
}
