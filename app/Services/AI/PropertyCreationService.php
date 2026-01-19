<?php

namespace App\Services\AI;

use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Enums\PropertyStatus;
use App\Models\Listing;
use App\Models\ListingGroup;
use App\Models\Property;
use App\Services\Google\GeocodingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PropertyCreationService
{
    public function __construct(
        protected ClaudeClient $claude,
        protected GeocodingService $geocoding,
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

        $group->markAsProcessingAi();

        try {
            $response = $this->claude->message(
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
        $group = ListingGroup::where('property_id', $property->id)->first();
        if (! $group) {
            $group = ListingGroup::create([
                'status' => ListingGroupStatus::ProcessingAi,
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
            $group->markAsProcessingAi();
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

        // Apply description
        if (! empty($toolResult['description'])) {
            $data['description'] = $toolResult['description'];
        }

        // Apply quality/confidence score
        if (isset($toolResult['quality_score'])) {
            $data['confidence_score'] = $toolResult['quality_score'];
        }

        // Apply geocoding if needed
        $data = $this->applyGeocoding($data, $toolResult, $listings);

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
     * @param  Collection<int, Listing>  $listings
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessages(Collection $listings): array
    {
        $isMultiple = $listings->count() > 1;

        $content = $isMultiple
            ? "## Property Creation from Matched Listings\n\nAnalyze these {$listings->count()} listings that represent the SAME physical property and create a unified property record.\n\n"
            : "## Property Creation from Single Listing\n\nAnalyze this listing and create a property record with enriched data.\n\n";

        foreach ($listings as $index => $listing) {
            $rawData = $listing->raw_data ?? [];
            $platformName = $listing->platform->name ?? 'Unknown';

            $content .= '### Listing '.($index + 1)." from {$platformName} (ID: {$listing->id})\n\n";

            // Title and description
            $content .= '**Title:** '.($rawData['title'] ?? 'N/A')."\n\n";
            $content .= "**Description:**\n".($rawData['description'] ?? 'N/A')."\n\n";

            // Location
            $content .= "**Location:**\n";
            $content .= '- Address: '.($rawData['address'] ?? 'N/A')."\n";
            $content .= '- Colonia: '.($rawData['colonia'] ?? 'N/A')."\n";
            $content .= '- City: '.($rawData['city'] ?? 'N/A')."\n";
            $content .= '- State: '.($rawData['state'] ?? 'N/A')."\n";
            $content .= '- Coordinates: '.($rawData['latitude'] ?? 'N/A').', '.($rawData['longitude'] ?? 'N/A')."\n\n";

            // Property details
            $content .= "**Details:**\n";
            $content .= '- Type: '.($rawData['property_type'] ?? 'N/A')."\n";
            $content .= '- Subtype: '.($rawData['property_subtype'] ?? 'N/A')."\n";
            $content .= '- Bedrooms: '.($rawData['bedrooms'] ?? 'N/A')."\n";
            $content .= '- Bathrooms: '.($rawData['bathrooms'] ?? 'N/A')."\n";
            $content .= '- Half Bathrooms: '.($rawData['half_bathrooms'] ?? 'N/A')."\n";
            $content .= '- Parking: '.($rawData['parking_spots'] ?? 'N/A')."\n";
            $content .= '- Lot Size: '.($rawData['lot_size_m2'] ?? 'N/A')." m²\n";
            $content .= '- Built Size: '.($rawData['built_size_m2'] ?? 'N/A')." m²\n";
            $content .= '- Age: '.($rawData['age_years'] ?? 'N/A')." years\n\n";

            // Operations/Pricing
            $content .= "**Operations:**\n";
            foreach ($rawData['operations'] ?? [] as $op) {
                $price = number_format($op['price'] ?? 0);
                $content .= "- {$op['type']}: \${$price} {$op['currency']}\n";
                if (! empty($op['maintenance_fee'])) {
                    $content .= "  Maintenance: \${$op['maintenance_fee']}\n";
                }
            }
            $content .= "\n";

            // Amenities
            $content .= '**Current Amenities:** '.implode(', ', $rawData['amenities'] ?? [])."\n\n";

            $content .= "---\n\n";
        }

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
You are a real estate data analyst. Your task is to analyze listing data and create a clean, canonical property record.

LANGUAGE RULES - CRITICAL:
- Description: MUST stay in the EXACT SAME LANGUAGE as input (usually Spanish for Mexican listings)
- NEVER translate descriptions. Translating is a DATA QUALITY ERROR.
- Tags/Amenities: ALWAYS use ENGLISH, lowercase, snake_case format (e.g., "swimming_pool", "gym", "24_hour_security")
- Quality issues: Always in English

CONTEXT:
- These are property listings from Mexican real estate platforms
- Addresses use Mexican format: Calle, Numero, Colonia, Ciudad, Estado
- Prices are typically in MXN (Mexican Pesos) or USD
- Property types: house, apartment, land, commercial, office, warehouse

PROMPT;

        if ($isMultiple) {
            $basePrompt .= <<<'PROMPT'

MULTIPLE LISTINGS - UNIFICATION RULES:
These listings have been matched as representing the SAME physical property.
Your job is to create ONE unified property record with the best data from all sources.

For each field:
- When sources agree: use the common value with high confidence
- When sources conflict: use your judgment to pick the most accurate value
  * Prefer more specific/detailed values
  * For bedrooms/bathrooms: prefer value consistent with the description
  * For sizes: prefer larger value (usually more accurate)
  * For addresses: prefer more complete address
- Flag all discrepancies so users know about data conflicts

For the description:
- Create ONE comprehensive description combining the best details from all listings
- Include unique details from each source (don't lose information)
- Remove duplicate information
- Structure clearly: intro, property features, amenities, location highlights

PROMPT;
        } else {
            $basePrompt .= <<<'PROMPT'

SINGLE LISTING - ENRICHMENT RULES:
This is a unique property with no matches. Enrich the data with AI analysis.

- Validate and correct obvious errors in numeric fields
- Extract additional amenities from the description text
- Clean the description (remove promotional text, contact info, duplicate paragraphs)
- Verify address consistency
- Generate a quality score

PROMPT;
        }

        $basePrompt .= <<<'PROMPT'

YOUR TASKS:

1. UNIFIED/ENRICHED FIELDS:
   - Determine the best value for each field
   - Validate numeric values make sense
   - Only include fields where you have confidence

2. ADDRESS FORMATTING - CRITICAL:
   - Use Title Case for street names (Avenida Reforma, not AVENIDA REFORMA)
   - Use proper Mexican abbreviations:
     * "Calle" (not "C." or "CALLE")
     * "No." for numbers (not "Num" or "#")
     * "Int." for interior numbers
     * "Col." for colonia only in compact formats
   - Format: "Calle Nombre No. 123" or "Avenida Principal No. 456 Int. 7"
   - Capitalize colonia names properly: "Del Valle" not "DEL VALLE"
   - City names in Title Case: "Ciudad de México" not "CIUDAD DE MEXICO"

3. DESCRIPTION:
   - Write in the SAME LANGUAGE as input (usually Spanish)
   - Clean and professional
   - Remove: promotional phrases, contact info, duplicate text
   - Keep: rental requirements, maintenance fees, move-in costs
   - Structure: intro paragraph, features, amenities, location

4. AMENITIES - EXTRACT FROM DESCRIPTION:
   Look for these patterns in the description and extract as amenities:
   - Physical features: swimming_pool, gym, garden, terrace, balcony, rooftop
   - Security: 24_hour_security, gated_community, security_cameras, doorman
   - Parking: covered_parking, uncovered_parking, guest_parking
   - Building: elevator, lobby, common_areas, meeting_room, business_center
   - Outdoor: bbq_area, playground, sports_court, jogging_track
   - Interior: air_conditioning, heating, fireplace, laundry_room, storage_room
   - Services: water_tank, backup_power, gas, water_included, pet_friendly
   - Location hints: near_park, near_schools, near_metro, near_shopping
   - Always use English snake_case format

5. GEOCODING ADDRESS:
   - Create clean address string for Google Maps geocoding
   - Format: "Street Number, Colonia, City, State, Mexico"
   - Return null if address too incomplete

6. FIELD SOURCES (for multiple listings):
   - Indicate which listing each field value came from
   - Include confidence level

7. DISCREPANCIES (for multiple listings):
   - List all conflicts between sources
   - Include resolved value and reasoning

8. QUALITY SCORE:
   - Rate 0-100 based on data completeness and consistency

Always use the create_property tool to submit your analysis.
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
                        'description' => 'Clean, comprehensive property description. Same language as input.',
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
                'required' => ['description', 'unified_fields', 'quality_score'],
            ],
        ];
    }
}
