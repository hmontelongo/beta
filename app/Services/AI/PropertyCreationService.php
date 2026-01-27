<?php

namespace App\Services\AI;

use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Enums\PropertyStatus;
use App\Enums\PropertySubtype;
use App\Enums\PropertyType;
use App\Jobs\CreatePropertyFromListingJob;
use App\Jobs\CreatePropertyFromListingsJob;
use App\Models\Listing;
use App\Models\ListingGroup;
use App\Models\Property;
use App\Services\ApiUsageTracker;
use App\Services\Google\GeocodingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyCreationService
{
    public function __construct(
        protected ClaudeClient $claude,
        protected GeocodingService $geocoding,
        protected ApiUsageTracker $usageTracker,
    ) {}

    /**
     * Create or update a property from a listing group using AI analysis.
     */
    public function createPropertyFromGroup(ListingGroup $group): Property
    {
        $group->load('listings.platform');
        $listings = $group->listings;

        if ($listings->isEmpty()) {
            throw new \InvalidArgumentException('ListingGroup has no listings');
        }

        // Atomically claim the group to prevent race conditions
        $claimed = ListingGroup::where('id', $group->id)
            ->where('status', ListingGroupStatus::PendingAi)
            ->update(['status' => ListingGroupStatus::ProcessingAi]);

        if ($claimed === 0) {
            // Group was already claimed by another process or is in wrong state
            Log::info('PropertyCreationService: Group already claimed or not pending', [
                'listing_group_id' => $group->id,
                'current_status' => $group->fresh()->status->value ?? 'unknown',
            ]);

            throw new \RuntimeException('Group is not available for processing');
        }

        // Refresh the group model to get updated status
        $group->refresh();

        try {
            $context = ClaudeCallContext::forListingGroup($group->id, CreatePropertyFromListingsJob::class);

            $response = $this->claude
                ->withTracking($this->usageTracker, $context)
                ->message(
                    messages: $this->buildMessages($listings),
                    tools: [$this->getPropertyCreationToolSchema()],
                    system: $this->getSystemPrompt($listings->count() > 1)
                );

            $toolResult = $this->claude->extractToolUse($response, 'create_property');
            $usage = $this->claude->getUsage($response);

            if (! $toolResult) {
                throw new \RuntimeException('AI did not return structured property data');
            }

            // Validate required fields in AI response
            if (empty($toolResult['unified_fields']) || ! is_array($toolResult['unified_fields'])) {
                throw new \RuntimeException('AI response missing or invalid unified_fields');
            }
            if (! isset($toolResult['quality_score']) || ! is_int($toolResult['quality_score'])) {
                throw new \RuntimeException('AI response missing or invalid quality_score');
            }
            if (empty($toolResult['description']) || ! is_string($toolResult['description'])) {
                throw new \RuntimeException('AI response missing or invalid description');
            }

            // Create or update property in a transaction
            $property = DB::transaction(function () use ($group, $listings, $toolResult, $usage) {
                // Check if updating existing property
                $property = $group->property_id ? Property::find($group->property_id) : null;

                // Build property data from AI analysis
                $propertyData = $this->buildPropertyData($toolResult, $listings);

                // Add AI metadata
                $propertyData['ai_unification'] = [
                    'version' => 2,
                    'sources' => $listings->map(fn ($l) => [
                        'listing_id' => $l->id,
                        'platform' => $l->platform->name ?? 'Unknown',
                    ])->values()->toArray(),
                    'field_sources' => $toolResult['field_sources'] ?? [],
                    'discrepancies' => $toolResult['discrepancies'] ?? [],
                    'model' => config('services.anthropic.model', 'claude-sonnet-4-20250514'),
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                ];
                $propertyData['ai_unified_at'] = now();
                $propertyData['needs_reanalysis'] = false;
                $propertyData['discrepancies'] = $toolResult['discrepancies'] ?? [];

                if ($property) {
                    $property->update($propertyData);
                } else {
                    $propertyData['status'] = PropertyStatus::Active;
                    $propertyData['listings_count'] = $listings->count();
                    $property = Property::create($propertyData);
                }

                // Link all listings to the property
                foreach ($listings as $listing) {
                    $listing->update([
                        'property_id' => $property->id,
                        'dedup_status' => DedupStatus::Completed,
                        'dedup_checked_at' => now(),
                    ]);
                }

                // Sync publishers from listings to property
                $publisherIds = $listings
                    ->pluck('publisher_id')
                    ->filter()
                    ->unique()
                    ->values();

                if ($publisherIds->isNotEmpty()) {
                    $property->publishers()->syncWithoutDetaching($publisherIds);
                }

                // Update the group
                $group->markAsCompleted($property, $toolResult);

                return $property;
            });

            Log::info('Property created from listing group', [
                'property_id' => $property->id,
                'listing_group_id' => $group->id,
                'listings_count' => $listings->count(),
                'quality_score' => $toolResult['quality_score'] ?? null,
                'discrepancies_count' => count($toolResult['discrepancies'] ?? []),
                'input_tokens' => $usage['input_tokens'],
                'output_tokens' => $usage['output_tokens'],
            ]);

            return $property;

        } catch (\Throwable $e) {
            // Race condition - group was already claimed by another process
            // Don't update status, just silently exit
            if (str_contains($e->getMessage(), 'not available for processing')) {
                throw $e;
            }

            Log::error('Property creation from listing group failed', [
                'listing_group_id' => $group->id,
                'error' => $e->getMessage(),
            ]);

            // Re-throw rate limit errors so job retry mechanism can handle them
            if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'rate_limit')) {
                // Reset status to pending_ai so it can be retried
                $group->update(['status' => ListingGroupStatus::PendingAi]);
                throw $e;
            }

            // For other errors, mark the group as failed (back to pending_review for manual handling)
            $group->update([
                'status' => ListingGroupStatus::PendingReview,
                'rejection_reason' => 'AI processing failed: '.$e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a property directly from a unique listing (no group needed).
     * Used for listings with no duplicates that should go straight to property creation.
     */
    public function createPropertyFromListing(Listing $listing): Property
    {
        $listing->load('platform');
        $listings = collect([$listing]);

        // Atomically claim the listing to prevent race conditions
        $claimed = Listing::where('id', $listing->id)
            ->where('dedup_status', DedupStatus::Unique)
            ->update(['dedup_status' => DedupStatus::Processing]);

        if ($claimed === 0) {
            Log::info('PropertyCreationService: Listing already claimed or not unique', [
                'listing_id' => $listing->id,
                'current_status' => $listing->fresh()->dedup_status->value ?? 'unknown',
            ]);

            throw new \RuntimeException('Listing is not available for processing');
        }

        // Refresh the listing model to get updated status
        $listing->refresh();

        try {
            $context = ClaudeCallContext::forListing($listing->id, CreatePropertyFromListingJob::class);

            $response = $this->claude
                ->withTracking($this->usageTracker, $context)
                ->message(
                    messages: $this->buildMessages($listings),
                    tools: [$this->getPropertyCreationToolSchema()],
                    system: $this->getSystemPrompt(false) // Single listing, not multiple
                );

            $toolResult = $this->claude->extractToolUse($response, 'create_property');
            $usage = $this->claude->getUsage($response);

            if (! $toolResult) {
                throw new \RuntimeException('AI did not return structured property data');
            }

            // Validate required fields in AI response
            if (empty($toolResult['unified_fields']) || ! is_array($toolResult['unified_fields'])) {
                throw new \RuntimeException('AI response missing or invalid unified_fields');
            }
            if (! isset($toolResult['quality_score']) || ! is_int($toolResult['quality_score'])) {
                throw new \RuntimeException('AI response missing or invalid quality_score');
            }
            if (empty($toolResult['description']) || ! is_string($toolResult['description'])) {
                throw new \RuntimeException('AI response missing or invalid description');
            }

            // Create property in a transaction
            $property = DB::transaction(function () use ($listing, $listings, $toolResult, $usage) {
                // Build property data from AI analysis
                $propertyData = $this->buildPropertyData($toolResult, $listings);

                // Add AI metadata
                $propertyData['ai_unification'] = [
                    'version' => 2,
                    'sources' => [[
                        'listing_id' => $listing->id,
                        'platform' => $listing->platform->name ?? 'Unknown',
                    ]],
                    'field_sources' => $toolResult['field_sources'] ?? [],
                    'discrepancies' => [], // No discrepancies for single listing
                    'model' => config('services.anthropic.model', 'claude-sonnet-4-20250514'),
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                ];
                $propertyData['ai_unified_at'] = now();
                $propertyData['needs_reanalysis'] = false;
                $propertyData['discrepancies'] = [];

                $propertyData['status'] = PropertyStatus::Active;
                $propertyData['listings_count'] = 1;
                $property = Property::create($propertyData);

                // Link the listing to the property
                $listing->update([
                    'property_id' => $property->id,
                    'dedup_status' => DedupStatus::Completed,
                    'dedup_checked_at' => now(),
                ]);

                // Sync publisher if available
                if ($listing->publisher_id) {
                    $property->publishers()->syncWithoutDetaching([$listing->publisher_id]);
                }

                return $property;
            });

            Log::info('Property created from unique listing', [
                'property_id' => $property->id,
                'listing_id' => $listing->id,
                'quality_score' => $toolResult['quality_score'] ?? null,
                'input_tokens' => $usage['input_tokens'],
                'output_tokens' => $usage['output_tokens'],
            ]);

            return $property;

        } catch (\Throwable $e) {
            // Race condition - listing was already claimed by another process
            if (str_contains($e->getMessage(), 'not available for processing')) {
                throw $e;
            }

            Log::error('Property creation from unique listing failed', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage(),
            ]);

            // Re-throw rate limit errors so job retry mechanism can handle them
            if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'rate_limit')) {
                // Reset status to unique so it can be retried
                $listing->update(['dedup_status' => DedupStatus::Unique]);
                throw $e;
            }

            // For other errors, reset to unique for retry
            $listing->update(['dedup_status' => DedupStatus::Unique]);

            throw $e;
        }
    }

    /**
     * Re-analyze a property that has been flagged for re-analysis.
     */
    public function reanalyzeProperty(Property $property): Property
    {
        $property->load('listings.platform');
        $listings = $property->listings;

        if ($listings->isEmpty()) {
            Log::warning('Cannot re-analyze property without listings', [
                'property_id' => $property->id,
            ]);

            return $property;
        }

        // Create or get the listing group for this property
        // Set to PendingAi so createPropertyFromGroup can atomically claim it
        $group = ListingGroup::where('property_id', $property->id)->first();
        if (! $group) {
            $group = ListingGroup::create([
                'status' => ListingGroupStatus::PendingAi,
                'property_id' => $property->id,
                'match_score' => 100.0, // Existing property
            ]);

            // Link all listings to the group via column
            $firstListing = $listings->first();
            foreach ($listings as $listing) {
                $listing->update([
                    'listing_group_id' => $group->id,
                    'is_primary_in_group' => $listing->id === $firstListing->id,
                ]);
            }
        } else {
            $group->update(['status' => ListingGroupStatus::PendingAi]);
        }

        return $this->createPropertyFromGroup($group);
    }

    /**
     * Build property data from AI tool result.
     *
     * @param  array<string, mixed>  $toolResult
     * @param  Collection<int, Listing>  $listings
     * @return array<string, mixed>
     */
    protected function buildPropertyData(array $toolResult, Collection $listings): array
    {
        $data = [];

        // Apply unified fields
        $unifiedFields = $toolResult['unified_fields'] ?? [];
        $allowedFields = [
            'address', 'interior_number', 'colonia', 'city', 'state', 'postal_code',
            'latitude', 'longitude', 'property_type', 'property_subtype',
            'bedrooms', 'bathrooms', 'half_bathrooms', 'parking_spots',
            'lot_size_m2', 'built_size_m2', 'age_years', 'amenities',
        ];

        foreach ($allowedFields as $field) {
            if (isset($unifiedFields[$field]) && $unifiedFields[$field] !== null) {
                $data[$field] = $unifiedFields[$field];
            }
        }

        // Fallback: if no address from AI, try geocoded_formatted_address from listings
        if (empty($data['address'])) {
            foreach ($listings as $listing) {
                $geocodedAddress = $listing->raw_data['geocoded_formatted_address'] ?? null;
                if ($geocodedAddress) {
                    $data['address'] = $geocodedAddress;
                    break;
                }
            }
        }

        // Sanitize enum fields - AI may return invalid values
        $data = $this->sanitizeEnumFields($data);

        // Apply description
        if (! empty($toolResult['description'])) {
            $data['description'] = $toolResult['description'];
        }

        // Apply quality/confidence score
        if (isset($toolResult['quality_score'])) {
            $data['confidence_score'] = $toolResult['quality_score'];
        }

        // Store extracted data from AI (pricing, terms, amenities, location, inferred)
        if (! empty($toolResult['extracted_data'])) {
            $data['ai_extracted_data'] = $toolResult['extracted_data'];
        }

        // Apply geocoding if needed
        $data = $this->applyGeocoding($data, $toolResult, $listings);

        return $data;
    }

    /**
     * Sanitize enum fields to handle invalid AI responses.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeEnumFields(array $data): array
    {
        // Validate property_type
        if (isset($data['property_type'])) {
            $validTypes = array_column(PropertyType::cases(), 'value');
            if (! in_array($data['property_type'], $validTypes, true)) {
                Log::warning('Invalid property_type from AI, removing', [
                    'value' => $data['property_type'],
                ]);
                unset($data['property_type']);
            }
        }

        // Validate property_subtype
        if (isset($data['property_subtype'])) {
            $validSubtypes = array_column(PropertySubtype::cases(), 'value');
            if (! in_array($data['property_subtype'], $validSubtypes, true)) {
                Log::warning('Invalid property_subtype from AI, removing', [
                    'value' => $data['property_subtype'],
                ]);
                unset($data['property_subtype']);
            }
        }

        return $data;
    }

    /**
     * Apply geocoding to improve coordinates.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $toolResult
     * @param  Collection<int, Listing>  $listings
     * @return array<string, mixed>
     */
    protected function applyGeocoding(array $data, array $toolResult, Collection $listings): array
    {
        $hasCoordinates = ! empty($data['latitude']) && ! empty($data['longitude']);
        $geocodingAddress = $toolResult['geocoding_address'] ?? null;

        // Skip if we have coordinates and no better address suggestion
        if ($hasCoordinates && ! $geocodingAddress) {
            return $data;
        }

        // Try geocoding with AI's suggested address
        if ($geocodingAddress) {
            $result = $this->geocoding->geocode(
                address: $geocodingAddress,
                city: $data['city'] ?? null,
                state: $data['state'] ?? null,
            );

            if ($result) {
                $data['latitude'] = $result['lat'];
                $data['longitude'] = $result['lng'];

                return $data;
            }
        }

        // Fall back to best coordinates from listings
        if (! $hasCoordinates) {
            foreach ($listings as $listing) {
                $rawData = $listing->raw_data ?? [];
                if (! empty($rawData['latitude']) && ! empty($rawData['longitude'])) {
                    $data['latitude'] = $rawData['latitude'];
                    $data['longitude'] = $rawData['longitude'];
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * Build the messages array for Claude.
     *
     * Sends full raw_data as JSON so Claude has access to ALL information
     * including descriptions, operations, amenities, and metadata.
     *
     * @param  Collection<int, Listing>  $listings
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessages(Collection $listings): array
    {
        $isMultiple = $listings->count() > 1;

        $intro = $isMultiple
            ? "## Property Creation from Matched Listings\n\nAnalyze these {$listings->count()} listings that represent the SAME physical property. Create a unified property record and extract structured data from descriptions.\n\n"
            : "## Property Creation from Single Listing\n\nAnalyze this listing and create a property record with enriched data extracted from the description.\n\n";

        $listingsData = $listings->map(function (Listing $listing) {
            $rawData = $listing->raw_data ?? [];

            // Add listing metadata to raw_data for AI context
            return [
                'listing_id' => $listing->id,
                'platform' => $listing->platform->name ?? 'Unknown',
                'scraped_at' => $listing->scraped_at?->toIso8601String(),
                'latitude' => $listing->latitude,
                'longitude' => $listing->longitude,
                'raw_data' => $rawData,
            ];
        })->values()->toArray();

        $content = $intro."```json\n".json_encode($listingsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n```";

        return [
            ['role' => 'user', 'content' => $content],
        ];
    }

    /**
     * Get the system prompt based on whether we're handling single or multiple listings.
     */
    protected function getSystemPrompt(bool $isMultiple): string
    {
        $basePrompt = <<<'PROMPT'
You are a real estate data analyst specializing in Mexican property markets. Your task is to analyze listing data and create an ELEVATED property record with structured data extracted from descriptions.

You receive the FULL raw_data from each listing in JSON format. This includes structured fields AND free-text descriptions that contain valuable information not captured in the structured data.

LANGUAGE RULES - CRITICAL:
- Description: MUST stay in the EXACT SAME LANGUAGE as input (usually Spanish for Mexican listings)
- NEVER translate descriptions. Translating is a DATA QUALITY ERROR.
- Tags/Amenities: ALWAYS use ENGLISH, lowercase, snake_case format
- All extracted_data values: Use appropriate types (numbers, booleans, arrays)

CONTEXT:
- These are property listings from Mexican real estate platforms
- Addresses use Mexican format: Calle, Numero, Colonia, Ciudad, Estado
- Prices are typically in MXN (Mexican Pesos) or USD
- Property types: house, apartment, land, commercial, office, warehouse

PROMPT;

        if ($isMultiple) {
            $basePrompt .= <<<'PROMPT'

MULTIPLE LISTINGS - UNIFICATION RULES:
These listings represent the SAME physical property from different platforms.
Create ONE unified property record with the best data from all sources.
Cross-reference descriptions to build a complete picture.

For conflicts:
- Prefer more specific/detailed values
- For bedrooms/bathrooms: prefer value consistent with descriptions
- For sizes: prefer larger value (usually more accurate)
- For addresses: prefer more complete address
- Flag all discrepancies

PROMPT;
        } else {
            $basePrompt .= <<<'PROMPT'

SINGLE LISTING - ENRICHMENT RULES:
This is a unique property. Enrich the data by extracting structured information from the description.

PROMPT;
        }

        $basePrompt .= <<<'PROMPT'

=== YOUR 5 EXTRACTION TASKS ===

TASK 1: UNIFY PROPERTY FIELDS
Standard property fields from structured data:
- Address, colonia, city, state (use geocoded_* when available, they're authoritative from Google Maps)
- Property type, subtype, bedrooms, bathrooms, sizes
- Validate numeric values make sense

TASK 2: EXTRACT PRICING DATA
From descriptions, extract into extracted_data.pricing:
- price_range: {min, max, currency} - actual price range if mentioned
- extra_costs: [{item, price, period, note}] - parking fees, laundry, utilities
- included_services: [{service, details}] - internet, maintenance, utilities
- price_per_m2: if calculable

Examples from descriptions:
- "PRECIOS DESDE $12,500 a $13,500" → price_range: {min: 12500, max: 13500}
- "Estacionamiento $1,500 mensuales" → extra_costs: [{item: "parking", price: 1500, period: "monthly"}]
- "Internet (50 mb) incluido" → included_services: [{service: "internet", details: "50mb"}]

TASK 3: EXTRACT RENTAL/PURCHASE TERMS
From descriptions, extract into extracted_data.terms:
- deposit_months: number of months required as deposit
- advance_months: number of months rent in advance
- income_proof_months: months of income statements required
- guarantor_required: boolean (look for "aval", "fiador")
- legal_policy: any mention of "póliza jurídica" or legal requirements
- pets_allowed: boolean (look for "no se aceptan mascotas", "pet friendly")
- max_occupants: if mentioned (e.g., "PARA 1 PERSONA")
- restrictions: array of any other restrictions mentioned

Examples:
- "1 mes de depósito y 1 mes de renta por adelantado" → deposit_months: 1, advance_months: 1
- "NO SE PIDE AVAL" → guarantor_required: false
- "No se aceptan mascotas" → pets_allowed: false

TASK 4: CATEGORIZE AMENITIES
Extract amenities into extracted_data.amenities_categorized:
- unit: amenities inside the unit (murphy_bed, closet, kitchenette, ac, tv, desk)
- building: shared building amenities (gym, rooftop, coworking, pool, lobby, recording_studio)
- services: included services (internet, maintenance, security)
- optional: amenities that cost extra or vary by unit

This is SEPARATE from unified_fields.amenities which remains a flat list.

TASK 5: IDENTIFY PROPERTY & LOCATION
Extract into extracted_data.location:
- building_name: official building/development name (e.g., "CIUDAD 901")
- building_type: coliving, residential, mixed_use, etc.
- nearby: [{name, type, distance}] - universities, malls, metro stations

Extract into extracted_data.inferred:
- target_audience: who this property is marketed to (students, families, professionals)
- occupancy_type: single_person, couple, family, roommates
- property_condition: new, excellent, good, needs_work

=== TASK 6: WRITE A STRUCTURED, COMPREHENSIVE DESCRIPTION ===

Write a well-organized property description that is ACCURATE and COMPLETE. Do not exaggerate.

OUTPUT FORMAT: Clean HTML (rendered directly on the page).

STRUCTURE (use this consistent format for ALL properties):

<h3>Introducción</h3>
<p>What type of property and where (1-2 sentences). Building name if applicable.</p>

<h3>El Espacio</h3>
<p>Brief intro about the unit.</p>
<ul>
  <li>Room, furniture, appliance details</li>
  <li>Size and layout</li>
  <li>Special features</li>
</ul>

<h3>Amenidades del Edificio</h3>
<ul>
  <li>Shared amenities</li>
  <li>Building features</li>
</ul>

<h3>Servicios Incluidos</h3>
<ul>
  <li>What's included in the price</li>
  <li>What costs extra (with prices if known)</li>
</ul>

<h3>Requisitos</h3>
<ul>
  <li>Deposit and advance</li>
  <li>Documentation needed</li>
  <li>Restrictions (pets, occupancy, etc.)</li>
</ul>

<h3>Ubicación</h3>
<ul>
  <li>Nearby landmarks, universities, transit</li>
</ul>

HTML FORMAT REQUIREMENTS:
- Headers: Use <h3> tags (title case with accents: "Introducción")
- Lists: Use <ul> and <li> tags
- Paragraphs: Use <p> tags
- NO markdown syntax (no ###, no -, no **)
- NO ALL CAPS
- Omit sections that have no data

ACCURACY IS CRITICAL:
- Only include information actually in the source data
- Do not invent or assume features
- If unclear, omit rather than guess
- No exaggeration - describe what IS there

LENGTH: As comprehensive as needed. Include all relevant details.

=== OUTPUT STRUCTURE ===

Return via the create_property tool:

1. unified_fields: Standard property fields (address, bedrooms, etc.)
2. description: Clean HTML using <h3>, <p>, <ul>, <li> tags. NO markdown.
3. extracted_data: NEW structured data from descriptions:
   - pricing: {price_range, extra_costs, included_services}
   - terms: {deposit_months, advance_months, guarantor_required, pets_allowed, restrictions}
   - amenities_categorized: {unit, building, services, optional}
   - location: {building_name, building_type, nearby}
   - inferred: {target_audience, occupancy_type, property_condition}
4. quality_score: 0-100 based on data completeness
5. field_sources: Which listing each field came from
6. discrepancies: Conflicts between sources

=== ADDRESS FORMATTING ===

- GEOCODED DATA IS AUTHORITATIVE: Use geocoded_colonia, geocoded_city, geocoded_state when available
- Format addresses properly: "Calz. Central No. 503" not "CALZ CENTRAL 503"
- Use Title Case, proper abbreviations (No., Int., Col.)

Always use the create_property tool to submit your complete analysis.
PROMPT;

        return $basePrompt;
    }

    /**
     * Get the tool schema for property creation.
     *
     * @return array<string, mixed>
     */
    protected function getPropertyCreationToolSchema(): array
    {
        return [
            'name' => 'create_property',
            'description' => 'Submit the property data after analyzing listing(s).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'description' => [
                        'type' => 'string',
                        'description' => 'Clean HTML property description. Same language as input. Use <h3> for headers, <ul>/<li> for lists, <p> for paragraphs. Sections: Introducción, El Espacio, Amenidades del Edificio, Servicios Incluidos, Requisitos, Ubicación. Omit empty sections. Be accurate.',
                    ],
                    'unified_fields' => [
                        'type' => 'object',
                        'description' => 'Best values for property fields.',
                        'properties' => [
                            'address' => ['type' => ['string', 'null']],
                            'interior_number' => ['type' => ['string', 'null']],
                            'colonia' => ['type' => ['string', 'null']],
                            'city' => ['type' => ['string', 'null']],
                            'state' => ['type' => ['string', 'null']],
                            'postal_code' => ['type' => ['string', 'null']],
                            'latitude' => ['type' => ['number', 'null']],
                            'longitude' => ['type' => ['number', 'null']],
                            'bedrooms' => ['type' => ['integer', 'null']],
                            'bathrooms' => ['type' => ['integer', 'null']],
                            'half_bathrooms' => ['type' => ['integer', 'null']],
                            'parking_spots' => ['type' => ['integer', 'null']],
                            'lot_size_m2' => ['type' => ['number', 'null']],
                            'built_size_m2' => ['type' => ['number', 'null']],
                            'age_years' => ['type' => ['integer', 'null']],
                            'property_type' => [
                                'type' => ['string', 'null'],
                                'enum' => ['house', 'apartment', 'land', 'commercial', 'office', 'warehouse', null],
                            ],
                            'property_subtype' => ['type' => ['string', 'null']],
                            'amenities' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'All amenities in English snake_case (swimming_pool, gym, etc.)',
                            ],
                        ],
                    ],
                    'geocoding_address' => [
                        'type' => ['string', 'null'],
                        'description' => 'Clean address for geocoding. Format: "Street, Colonia, City, State, Mexico"',
                    ],
                    'extracted_data' => [
                        'type' => 'object',
                        'description' => 'Structured data extracted from descriptions - the "elevated" property data.',
                        'properties' => [
                            'pricing' => [
                                'type' => 'object',
                                'properties' => [
                                    'price_range' => [
                                        'type' => ['object', 'null'],
                                        'properties' => [
                                            'min' => ['type' => ['number', 'null']],
                                            'max' => ['type' => ['number', 'null']],
                                            'currency' => ['type' => 'string'],
                                        ],
                                    ],
                                    'extra_costs' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'item' => ['type' => 'string'],
                                                'price' => ['type' => ['number', 'null']],
                                                'period' => ['type' => ['string', 'null']],
                                                'note' => ['type' => ['string', 'null']],
                                            ],
                                        ],
                                    ],
                                    'included_services' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'service' => ['type' => 'string'],
                                                'details' => ['type' => ['string', 'null']],
                                            ],
                                        ],
                                    ],
                                    'price_per_m2' => ['type' => ['number', 'null']],
                                ],
                            ],
                            'terms' => [
                                'type' => 'object',
                                'properties' => [
                                    'deposit_months' => ['type' => ['integer', 'null']],
                                    'advance_months' => ['type' => ['integer', 'null']],
                                    'income_proof_months' => ['type' => ['integer', 'null']],
                                    'guarantor_required' => ['type' => ['boolean', 'null']],
                                    'legal_policy' => ['type' => ['string', 'null']],
                                    'pets_allowed' => ['type' => ['boolean', 'null']],
                                    'max_occupants' => ['type' => ['integer', 'null']],
                                    'restrictions' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                            'amenities_categorized' => [
                                'type' => 'object',
                                'properties' => [
                                    'unit' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                        'description' => 'Amenities inside the unit (murphy_bed, closet, ac, etc.)',
                                    ],
                                    'building' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                        'description' => 'Shared building amenities (gym, rooftop, pool, etc.)',
                                    ],
                                    'services' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                        'description' => 'Included services (internet, maintenance, security)',
                                    ],
                                    'optional' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                        'description' => 'Optional amenities that cost extra or vary by unit',
                                    ],
                                ],
                            ],
                            'location' => [
                                'type' => 'object',
                                'properties' => [
                                    'building_name' => ['type' => ['string', 'null']],
                                    'building_type' => ['type' => ['string', 'null']],
                                    'nearby' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'name' => ['type' => 'string'],
                                                'type' => ['type' => ['string', 'null']],
                                                'distance' => ['type' => ['string', 'null']],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'inferred' => [
                                'type' => 'object',
                                'properties' => [
                                    'target_audience' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                        'description' => 'Who this property targets (students, families, professionals)',
                                    ],
                                    'occupancy_type' => ['type' => ['string', 'null']],
                                    'property_condition' => ['type' => ['string', 'null']],
                                ],
                            ],
                        ],
                    ],
                    'field_sources' => [
                        'type' => 'object',
                        'description' => 'For each field, indicate source and confidence.',
                        'additionalProperties' => [
                            'type' => 'object',
                            'properties' => [
                                'source' => [
                                    'type' => 'string',
                                    'description' => 'e.g., "listing:1", "listing:74", or "unified"',
                                ],
                                'confidence' => [
                                    'type' => 'string',
                                    'enum' => ['high', 'medium', 'low'],
                                ],
                            ],
                        ],
                    ],
                    'discrepancies' => [
                        'type' => 'array',
                        'description' => 'Data conflicts between sources (for multiple listings).',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'field' => ['type' => 'string'],
                                'values' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'listing_id' => ['type' => 'integer'],
                                            'value' => ['type' => ['string', 'number', 'integer', 'null']],
                                        ],
                                    ],
                                ],
                                'resolved_value' => ['type' => ['string', 'number', 'integer', 'null']],
                                'reasoning' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'quality_score' => [
                        'type' => 'integer',
                        'description' => 'Data quality/completeness score from 0-100',
                        'minimum' => 0,
                        'maximum' => 100,
                    ],
                    'quality_issues' => [
                        'type' => 'array',
                        'description' => 'Specific data quality issues found',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => ['description', 'unified_fields', 'extracted_data', 'quality_score'],
            ],
        ];
    }
}
