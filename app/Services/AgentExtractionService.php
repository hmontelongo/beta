<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Listing;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentExtractionService
{
    /**
     * Publisher types that indicate an agency.
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
     * Extract agent/agency from listing raw_data and link to the listing.
     */
    public function extractFromListing(Listing $listing): void
    {
        $rawData = $listing->raw_data ?? [];
        $publisherName = $rawData['publisher_name'] ?? null;

        if (empty($publisherName)) {
            return;
        }

        $platform = $listing->platform;

        if (! $platform) {
            Log::warning('AgentExtractionService: Listing has no platform', [
                'listing_id' => $listing->id,
            ]);

            return;
        }

        $publisherType = $rawData['publisher_type'] ?? null;

        if ($this->isAgencyType($publisherType, $publisherName)) {
            $listing->agency_id = $this->findOrCreateAgency($rawData, $platform)->id;
        } else {
            $listing->agent_id = $this->findOrCreateAgent($rawData, $platform)->id;
        }

        $listing->save();

        Log::debug('AgentExtractionService: Linked publisher to listing', [
            'listing_id' => $listing->id,
            'agent_id' => $listing->agent_id,
            'agency_id' => $listing->agency_id,
            'publisher_name' => $publisherName,
        ]);
    }

    /**
     * Process all existing listings that have publisher data but no agent/agency linked.
     */
    public function processUnlinkedListings(int $limit = 100): int
    {
        $processed = 0;

        $listings = Listing::query()
            ->whereNull('agent_id')
            ->whereNull('agency_id')
            ->whereNotNull('raw_data')
            ->whereRaw("JSON_EXTRACT(raw_data, '$.publisher_name') IS NOT NULL")
            ->limit($limit)
            ->get();

        foreach ($listings as $listing) {
            $this->extractFromListing($listing);
            $processed++;
        }

        return $processed;
    }

    /**
     * Determine if the publisher type indicates an agency.
     */
    protected function isAgencyType(?string $publisherType, string $publisherName): bool
    {
        if ($publisherType && in_array(strtolower($publisherType), self::AGENCY_TYPES, true)) {
            return true;
        }

        return Str::contains(strtolower($publisherName), self::AGENCY_NAME_INDICATORS);
    }

    /**
     * Find or create an agent from publisher data.
     *
     * @param  array<string, mixed>  $rawData
     */
    protected function findOrCreateAgent(array $rawData, Platform $platform): Agent
    {
        $publisherId = $rawData['publisher_id'] ?? null;
        $phone = $this->normalizePhone($rawData['whatsapp'] ?? null);
        $name = trim($rawData['publisher_name']);

        $agent = $this->findExistingAgent($publisherId, $phone, $name, $platform);

        if ($agent) {
            return $this->updatePlatformProfile($agent, $rawData, $platform, includeWhatsapp: true);
        }

        return Agent::create([
            'name' => $name,
            'phone' => $phone,
            'whatsapp' => $phone,
            'platform_profiles' => [
                $platform->slug => $this->buildPlatformProfile($rawData),
            ],
        ]);
    }

    /**
     * Find or create an agency from publisher data.
     *
     * @param  array<string, mixed>  $rawData
     */
    protected function findOrCreateAgency(array $rawData, Platform $platform): Agency
    {
        $publisherId = $rawData['publisher_id'] ?? null;
        $phone = $this->normalizePhone($rawData['whatsapp'] ?? null);
        $name = trim($rawData['publisher_name']);

        $agency = $this->findExistingAgency($publisherId, $phone, $name, $platform);

        if ($agency) {
            return $this->updatePlatformProfile($agency, $rawData, $platform, includeWhatsapp: false);
        }

        return Agency::create([
            'name' => $name,
            'phone' => $phone,
            'platform_profiles' => [
                $platform->slug => $this->buildPlatformProfile($rawData),
            ],
        ]);
    }

    /**
     * Find an existing agent by platform ID, phone, or name.
     */
    protected function findExistingAgent(
        ?string $publisherId,
        ?string $phone,
        string $name,
        Platform $platform
    ): ?Agent {
        if ($publisherId) {
            $agent = Agent::where("platform_profiles->{$platform->slug}->id", (string) $publisherId)->first();
            if ($agent) {
                return $agent;
            }
        }

        if ($phone) {
            $agent = Agent::where('phone', $phone)->orWhere('whatsapp', $phone)->first();
            if ($agent) {
                return $agent;
            }
        }

        return Agent::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }

    /**
     * Find an existing agency by platform ID, phone, or name.
     */
    protected function findExistingAgency(
        ?string $publisherId,
        ?string $phone,
        string $name,
        Platform $platform
    ): ?Agency {
        if ($publisherId) {
            $agency = Agency::where("platform_profiles->{$platform->slug}->id", (string) $publisherId)->first();
            if ($agency) {
                return $agency;
            }
        }

        if ($phone) {
            $agency = Agency::where('phone', $phone)->first();
            if ($agency) {
                return $agency;
            }
        }

        return Agency::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }

    /**
     * Update a model's platform profile with new data.
     *
     * @template T of Agent|Agency
     *
     * @param  T  $model
     * @param  array<string, mixed>  $rawData
     * @return T
     */
    protected function updatePlatformProfile(
        Agent|Agency $model,
        array $rawData,
        Platform $platform,
        bool $includeWhatsapp = false
    ): Agent|Agency {
        $profiles = $model->platform_profiles ?? [];
        $profiles[$platform->slug] = $this->buildPlatformProfile($rawData);
        $phone = $this->normalizePhone($rawData['whatsapp'] ?? null);

        $updates = [
            'platform_profiles' => $profiles,
            'phone' => $model->phone ?: $phone,
        ];

        if ($includeWhatsapp && $model instanceof Agent) {
            $updates['whatsapp'] = $model->whatsapp ?: $phone;
        }

        $model->update($updates);

        return $model;
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
