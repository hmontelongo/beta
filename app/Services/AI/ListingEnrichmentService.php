<?php

namespace App\Services\AI;

use App\Enums\AiEnrichmentStatus;
use App\Models\AiEnrichment;
use App\Models\Listing;
use App\Services\Google\GeocodingService;
use Illuminate\Support\Facades\Log;

class ListingEnrichmentService
{
    public function __construct(
        protected ClaudeClient $claude,
        protected GeocodingService $geocoding,
    ) {}

    /**
     * Enrich a single listing with AI analysis.
     */
    public function enrichListing(Listing $listing, bool $autoApply = true): AiEnrichment
    {
        $enrichment = AiEnrichment::updateOrCreate(
            ['listing_id' => $listing->id],
            ['status' => AiEnrichmentStatus::Processing]
        );

        try {
            $response = $this->claude->message(
                messages: $this->buildMessages($listing),
                tools: [$this->getEnrichmentToolSchema()],
                system: $this->getSystemPrompt()
            );

            $toolResult = $this->claude->extractToolUse($response, 'submit_enrichment_analysis');
            $usage = $this->claude->getUsage($response);

            if (! $toolResult) {
                throw new \RuntimeException('AI did not return structured enrichment data');
            }

            $enrichment->update([
                'status' => AiEnrichmentStatus::Completed,
                'validated_data' => $toolResult['validated_data'] ?? null,
                'extracted_tags' => $toolResult['extracted_tags'] ?? null,
                'improved_description' => $toolResult['improved_description'] ?? null,
                'address_verification' => $toolResult['address_verification'] ?? null,
                'quality_score' => $toolResult['quality_score'] ?? null,
                'quality_issues' => $toolResult['quality_issues'] ?? null,
                'suggested_property_type' => $toolResult['suggested_property_type'] ?? null,
                'confidence_scores' => $toolResult['confidence_scores'] ?? null,
                'ai_response_raw' => $response,
                'input_tokens' => $usage['input_tokens'],
                'output_tokens' => $usage['output_tokens'],
                'processed_at' => now(),
            ]);

            // Auto-apply enrichment to listing data
            if ($autoApply) {
                $this->applyEnrichment($listing, $toolResult);
            }

            $listing->update([
                'ai_status' => AiEnrichmentStatus::Completed,
                'ai_processed_at' => now(),
            ]);

            Log::info('Listing enrichment completed', [
                'listing_id' => $listing->id,
                'quality_score' => $toolResult['quality_score'] ?? null,
                'auto_applied' => $autoApply,
                'input_tokens' => $usage['input_tokens'],
                'output_tokens' => $usage['output_tokens'],
            ]);

        } catch (\Throwable $e) {
            Log::error('Listing enrichment failed', [
                'listing_id' => $listing->id,
                'error' => $e->getMessage(),
            ]);

            $enrichment->update([
                'status' => AiEnrichmentStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            $listing->update([
                'ai_status' => AiEnrichmentStatus::Failed,
            ]);
        }

        return $enrichment->fresh();
    }

    /**
     * Apply enrichment data to a listing's raw_data.
     *
     * @param  array<string, mixed>  $toolResult
     */
    public function applyEnrichment(Listing $listing, array $toolResult): void
    {
        $rawData = $listing->raw_data ?? [];
        $changes = [];

        // 1. Apply validated/corrected numeric fields
        if (! empty($toolResult['validated_data'])) {
            foreach ($toolResult['validated_data'] as $field => $value) {
                if ($value !== null && isset($rawData[$field])) {
                    // Only update if AI suggests a different value
                    if ($rawData[$field] != $value) {
                        $changes[$field] = ['old' => $rawData[$field], 'new' => $value];
                        $rawData[$field] = $value;
                    }
                } elseif ($value !== null && ! isset($rawData[$field])) {
                    // Fill in missing values
                    $changes[$field] = ['old' => null, 'new' => $value];
                    $rawData[$field] = $value;
                }
            }
        }

        // 2. Merge extracted tags into amenities (normalize to lowercase snake_case)
        if (! empty($toolResult['extracted_tags'])) {
            $existingAmenities = $rawData['amenities'] ?? [];
            $newTags = $toolResult['extracted_tags'];

            // Normalize existing amenities to snake_case
            $normalizedExisting = array_map(fn ($a) => strtolower(str_replace(' ', '_', $a)), $existingAmenities);
            $existingAmenities = array_map(fn ($a) => strtolower(str_replace(' ', '_', $a)), $existingAmenities);

            foreach ($newTags as $tag) {
                $normalizedTag = strtolower(str_replace(' ', '_', $tag));
                if (! in_array($normalizedTag, $normalizedExisting)) {
                    $existingAmenities[] = $normalizedTag;
                    $normalizedExisting[] = $normalizedTag;
                    $changes['amenities_added'][] = $normalizedTag;
                }
            }

            $rawData['amenities'] = array_values(array_unique($existingAmenities));
        }

        // 3. Apply cleaned description if provided
        if (! empty($toolResult['cleaned_description'])) {
            $oldDesc = $rawData['description'] ?? '';
            $newDesc = $toolResult['cleaned_description'];

            if (trim($oldDesc) !== trim($newDesc)) {
                $rawData['original_description'] = $oldDesc;
                $rawData['description'] = $newDesc;
                $changes['description'] = 'cleaned';
            }
        }

        // 4. Apply address corrections if suggested (but skip placeholder text)
        if (! empty($toolResult['address_verification']['suggested_corrections'])) {
            foreach ($toolResult['address_verification']['suggested_corrections'] as $field => $correctedValue) {
                // Skip if it's placeholder/instruction text
                if (! $this->isValidAddress($correctedValue)) {
                    continue;
                }
                if (isset($rawData[$field]) && $rawData[$field] !== $correctedValue) {
                    $changes["address_{$field}"] = ['old' => $rawData[$field], 'new' => $correctedValue];
                    $rawData[$field] = $correctedValue;
                }
            }
        }

        // 5. Apply suggested property type if different
        if (! empty($toolResult['suggested_property_type'])) {
            $currentType = $rawData['property_type'] ?? null;
            $suggestedType = $toolResult['suggested_property_type'];

            if ($currentType !== $suggestedType) {
                $rawData['original_property_type'] = $currentType;
                $rawData['property_type'] = $suggestedType;
                $changes['property_type'] = ['old' => $currentType, 'new' => $suggestedType];
            }
        }

        // 6. Geocode address if coordinates are missing or inaccurate
        $rawData = $this->applyGeocoding($rawData, $toolResult, $changes);

        // 7. Store enrichment metadata
        $rawData['ai_enriched'] = true;
        $rawData['ai_enriched_at'] = now()->toIso8601String();
        $rawData['ai_quality_score'] = $toolResult['quality_score'] ?? null;

        // Update the listing
        $listing->update(['raw_data' => $rawData]);

        if (! empty($changes)) {
            Log::info('Enrichment applied to listing', [
                'listing_id' => $listing->id,
                'changes' => $changes,
            ]);
        }
    }

    /**
     * Apply geocoding to get or improve coordinates.
     *
     * @param  array<string, mixed>  $rawData
     * @param  array<string, mixed>  $toolResult
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    protected function applyGeocoding(array $rawData, array $toolResult, array &$changes): array
    {
        $hasCoordinates = ! empty($rawData['latitude']) && ! empty($rawData['longitude']);
        $geocodingAddress = $toolResult['geocoding_address'] ?? null;

        // Skip geocoding if we have accurate coordinates and no better address from AI
        if ($hasCoordinates && ! $geocodingAddress) {
            // Still generate maps URL if missing
            if (empty($rawData['google_maps_url'])) {
                $rawData['google_maps_url'] = $this->geocoding->getMapsUrl(
                    (float) $rawData['latitude'],
                    (float) $rawData['longitude']
                );
            }

            return $rawData;
        }

        // Build address for geocoding - prefer AI's clean address, then colonia + city
        $address = $this->buildGeocodingAddress($rawData, $geocodingAddress);

        if (! $address) {
            return $rawData;
        }

        $result = $this->geocoding->geocode(
            address: $address,
            city: $rawData['city'] ?? null,
            state: $rawData['state'] ?? null,
        );

        if (! $result) {
            return $rawData;
        }

        // Store original coordinates if we're updating them
        if ($hasCoordinates) {
            $rawData['original_latitude'] = $rawData['latitude'];
            $rawData['original_longitude'] = $rawData['longitude'];
        }

        // Apply geocoded coordinates
        $rawData['latitude'] = $result['lat'];
        $rawData['longitude'] = $result['lng'];
        $rawData['geocoded_address'] = $result['formatted_address'];
        $rawData['google_place_id'] = $result['place_id'];
        $rawData['geocoding_accuracy'] = $result['location_type'];
        $rawData['google_maps_url'] = $this->geocoding->getMapsUrl($result['lat'], $result['lng']);

        $changes['geocoding'] = [
            'address_used' => $address,
            'result' => $result['formatted_address'],
            'accuracy' => $result['location_type'],
        ];

        Log::info('Geocoding applied', [
            'address' => $address,
            'result' => $result,
        ]);

        return $rawData;
    }

    /**
     * Build the best address string for geocoding.
     *
     * @param  array<string, mixed>  $rawData
     */
    protected function buildGeocodingAddress(array $rawData, ?string $aiGeocodingAddress): ?string
    {
        // 1. Use AI's clean geocoding address if provided
        if ($aiGeocodingAddress) {
            return $aiGeocodingAddress;
        }

        // 2. Check if raw address looks valid (not a placeholder)
        $rawAddress = $rawData['address'] ?? null;
        if ($rawAddress && $this->isValidAddress($rawAddress)) {
            return $rawAddress;
        }

        // 3. Fall back to colonia (neighborhood) - usually geocodes well in Mexico
        if (! empty($rawData['colonia'])) {
            return $rawData['colonia'];
        }

        return null;
    }

    /**
     * Check if an address string looks like a real address (not a placeholder).
     */
    protected function isValidAddress(string $address): bool
    {
        $lowered = strtolower($address);

        // Skip placeholder/instruction text
        $invalidPatterns = [
            'complete',
            'needed',
            'required',
            'missing',
            'n/a',
            'not available',
            'no disponible',
            'sin dirección',
        ];

        foreach ($invalidPatterns as $pattern) {
            if (str_contains($lowered, $pattern)) {
                return false;
            }
        }

        // Should have at least some substance
        return strlen(trim($address)) > 5;
    }

    /**
     * Build the messages array for Claude.
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessages(Listing $listing): array
    {
        $rawData = $listing->raw_data ?? [];

        $content = "Please analyze this real estate listing and provide enrichment data.\n\n";
        $content .= "## Listing Data\n\n";
        $content .= "**Title:** {$rawData['title']}\n\n";
        $content .= "**Description:**\n{$rawData['description']}\n\n";
        $content .= "**Location:**\n";
        $content .= '- Address: '.($rawData['address'] ?? 'N/A')."\n";
        $content .= '- Colonia: '.($rawData['colonia'] ?? 'N/A')."\n";
        $content .= '- City: '.($rawData['city'] ?? 'N/A')."\n";
        $content .= '- State: '.($rawData['state'] ?? 'N/A')."\n";
        $content .= '- Coordinates: '.($rawData['latitude'] ?? 'N/A').', '.($rawData['longitude'] ?? 'N/A')."\n\n";
        $content .= "**Property Details:**\n";
        $content .= '- Type: '.($rawData['property_type'] ?? 'N/A')."\n";
        $content .= '- Subtype: '.($rawData['property_subtype'] ?? 'N/A')."\n";
        $content .= '- Bedrooms: '.($rawData['bedrooms'] ?? 'N/A')."\n";
        $content .= '- Bathrooms: '.($rawData['bathrooms'] ?? 'N/A')."\n";
        $content .= '- Half Bathrooms: '.($rawData['half_bathrooms'] ?? 'N/A')."\n";
        $content .= '- Parking: '.($rawData['parking_spots'] ?? 'N/A')."\n";
        $content .= '- Lot Size: '.($rawData['lot_size_m2'] ?? 'N/A')." m²\n";
        $content .= '- Built Size: '.($rawData['built_size_m2'] ?? 'N/A')." m²\n";
        $content .= '- Age: '.($rawData['age_years'] ?? 'N/A')." years\n\n";
        $content .= "**Operations:**\n";
        foreach ($rawData['operations'] ?? [] as $op) {
            $content .= "- {$op['type']}: \${$op['price']} {$op['currency']}\n";
        }
        $content .= "\n**Currently Extracted Amenities:** ".implode(', ', $rawData['amenities'] ?? [])."\n";

        return [
            ['role' => 'user', 'content' => $content],
        ];
    }

    /**
     * Get the system prompt for enrichment.
     */
    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a real estate data analyst. Your task is to analyze listing data and provide structured enrichment that will be automatically applied to improve data quality.

LANGUAGE RULES - CRITICAL:
- Description: MUST stay in the EXACT SAME LANGUAGE as input. Spanish input = Spanish output. English input = English output.
- NEVER translate descriptions. Translating is a DATA QUALITY ERROR.
- Tags/Amenities: ALWAYS use ENGLISH, lowercase, snake_case format (e.g., "swimming_pool", "gym", "24_hour_security")
- Quality issues: Always in English
- Geocoding address: Use proper Spanish/local format for best geocoding results

IMPORTANT: Most listings are in SPANISH. Your cleaned_description output MUST be in SPANISH if the original is Spanish.

CONTEXT:
- These are property listings from Mexican real estate platforms
- Addresses use Mexican format: Calle, Numero, Colonia, Ciudad, Estado
- Prices are typically in MXN (Mexican Pesos) or USD
- Property types: house, apartment, land, commercial, office, warehouse

YOUR TASKS:

1. VALIDATE DATA (validated_data):
   - Check if numeric values make sense (bedrooms, bathrooms, sizes)
   - Correct obvious errors (e.g., 100 bedrooms is likely 1, 50000 m² for an apartment is likely 50)
   - Only include fields that need correction or are missing but can be inferred

2. EXTRACT TAGS (extracted_tags):
   - Find amenities/features mentioned in description that aren't in the extracted list
   - ALWAYS use English, lowercase, snake_case: swimming_pool, gym, security, covered_parking, garden, terrace, balcony, storage, elevator, air_conditioning, heating, furnished, pet_friendly, laundry_room, rooftop, bbq_area, playground, concierge, etc.
   - Translate Spanish amenities to English (alberca -> swimming_pool, gimnasio -> gym, seguridad -> security)
   - Only include genuinely new tags not already captured

3. CLEAN DESCRIPTION (cleaned_description):
   - ⚠️ CRITICAL: OUTPUT IN THE SAME LANGUAGE AS INPUT - DO NOT TRANSLATE ⚠️
   - If input is Spanish, output MUST be Spanish
   - If input is English, output MUST be English
   - REMOVE all pricing information (rent amounts, sale prices, maintenance fees)
   - REMOVE promotional phrases like "¡GRAN OPORTUNIDAD!", "NO TE LO PIERDAS!", "PRECIO NEGOCIABLE"
   - REMOVE contact information (phone numbers, WhatsApp, email)
   - REMOVE repeated phrases and duplicate paragraphs
   - STANDARDIZE FORMAT for all listings:
     * Write in proper sentence case (capitalize first letter of sentences only)
     * Use consistent paragraph structure: intro paragraph, then features list, then additional details
     * Use simple dashes (-) for lists, one item per line
     * No bullet points (•), no excessive punctuation
     * Proper capitalization for proper nouns and brand names only
   - REWRITE into clean, professional prose IN THE ORIGINAL LANGUAGE:
     * Don't just clean - rewrite into flowing, readable text
     * Start with a brief intro sentence describing the property
     * Group features logically (bedrooms, common areas, amenities, parking)
     * Use complete sentences where appropriate
   - Focus on describing the PROPERTY ITSELF (features, location, condition)
   - Return null ONLY if description is already well-formatted

4. GEOCODING ADDRESS (geocoding_address):
   - Create a clean, complete address string optimized for Google Maps geocoding
   - Format: "Street Number, Colonia, City, State, Mexico"
   - Include the most specific location info available
   - Fix any obvious misspellings in location names
   - Return null if address info is too incomplete for geocoding

5. VERIFY ADDRESS (address_verification):
   - Check if location components are consistent
   - Suggest corrections for misspellings or inconsistencies
   - is_valid: true if address seems correct

6. SCORE QUALITY (quality_score):
   - Rate 0-100 based on: has description (20), has coordinates (20), has complete address (20), has all features (20), has images mentioned (20)

7. IDENTIFY ISSUES (quality_issues):
   - List specific problems found (be concise, in English)
   - E.g., "Missing coordinates", "Price seems unusually low for area"

8. SUGGEST TYPE (suggested_property_type):
   - Only suggest if current type seems wrong based on description
   - Use: house, apartment, land, commercial, office, warehouse
   - Return null if current type is correct

Always use the submit_enrichment_analysis tool to return your analysis.
PROMPT;
    }

    /**
     * Get the tool schema for enrichment analysis.
     *
     * @return array<string, mixed>
     */
    protected function getEnrichmentToolSchema(): array
    {
        return [
            'name' => 'submit_enrichment_analysis',
            'description' => 'Submit the enrichment analysis results for a listing. All improvements will be automatically applied.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'validated_data' => [
                        'type' => 'object',
                        'description' => 'Corrected field values. Only include fields that need correction.',
                        'properties' => [
                            'bedrooms' => ['type' => ['integer', 'null']],
                            'bathrooms' => ['type' => ['integer', 'null']],
                            'half_bathrooms' => ['type' => ['integer', 'null']],
                            'parking_spots' => ['type' => ['integer', 'null']],
                            'lot_size_m2' => ['type' => ['number', 'null']],
                            'built_size_m2' => ['type' => ['number', 'null']],
                            'age_years' => ['type' => ['integer', 'null']],
                        ],
                    ],
                    'extracted_tags' => [
                        'type' => 'array',
                        'description' => 'NEW amenities/features found in description. MUST be English, lowercase, snake_case (e.g., swimming_pool, gym, security, covered_parking)',
                        'items' => ['type' => 'string'],
                    ],
                    'cleaned_description' => [
                        'type' => ['string', 'null'],
                        'description' => 'Description with prices, promotional text, and contact info REMOVED. Same language as original. Null only if no cleaning needed.',
                    ],
                    'geocoding_address' => [
                        'type' => ['string', 'null'],
                        'description' => 'Clean address string for Google Maps geocoding. Format: "Street Number, Colonia, City, State, Mexico". Null if address too incomplete.',
                    ],
                    'address_verification' => [
                        'type' => 'object',
                        'description' => 'Address verification results',
                        'properties' => [
                            'is_valid' => ['type' => 'boolean'],
                            'issues' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'suggested_corrections' => [
                                'type' => 'object',
                                'description' => 'Field name to corrected value mapping',
                                'additionalProperties' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'quality_score' => [
                        'type' => 'integer',
                        'description' => 'Data completeness score from 0-100',
                        'minimum' => 0,
                        'maximum' => 100,
                    ],
                    'quality_issues' => [
                        'type' => 'array',
                        'description' => 'Specific data quality issues found',
                        'items' => ['type' => 'string'],
                    ],
                    'suggested_property_type' => [
                        'type' => ['string', 'null'],
                        'description' => 'Suggested property type only if current type is incorrect',
                        'enum' => ['house', 'apartment', 'land', 'commercial', 'office', 'warehouse', null],
                    ],
                    'confidence_scores' => [
                        'type' => 'object',
                        'description' => 'Confidence scores for key fields (0-1)',
                        'properties' => [
                            'property_type' => ['type' => 'number'],
                            'location' => ['type' => 'number'],
                            'price' => ['type' => 'number'],
                            'features' => ['type' => 'number'],
                        ],
                    ],
                ],
                'required' => ['quality_score', 'quality_issues', 'confidence_scores', 'extracted_tags'],
            ],
        ];
    }
}
