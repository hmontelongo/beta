<?php

namespace App\Services\AI;

use App\Enums\AiEnrichmentStatus;
use App\Models\AiEnrichment;
use App\Models\Listing;
use Illuminate\Support\Facades\Log;

class ListingEnrichmentService
{
    protected ClaudeClient $claude;

    public function __construct(ClaudeClient $claude)
    {
        $this->claude = $claude;
    }

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

        // 2. Merge extracted tags into amenities
        if (! empty($toolResult['extracted_tags'])) {
            $existingAmenities = $rawData['amenities'] ?? [];
            $newTags = $toolResult['extracted_tags'];

            // Normalize for comparison (lowercase)
            $normalizedExisting = array_map('strtolower', $existingAmenities);

            foreach ($newTags as $tag) {
                $normalizedTag = strtolower($tag);
                if (! in_array($normalizedTag, $normalizedExisting)) {
                    $existingAmenities[] = $tag;
                    $changes['amenities_added'][] = $tag;
                }
            }

            $rawData['amenities'] = array_values(array_unique($existingAmenities));
        }

        // 3. Apply improved description if provided
        if (! empty($toolResult['improved_description'])) {
            $oldDesc = $rawData['description'] ?? '';
            $newDesc = $toolResult['improved_description'];

            // Only apply if meaningfully different (not just whitespace)
            if (trim($oldDesc) !== trim($newDesc)) {
                $rawData['original_description'] = $oldDesc; // Keep original
                $rawData['description'] = $newDesc;
                $changes['description'] = 'updated';
            }
        }

        // 4. Apply address corrections if suggested
        if (! empty($toolResult['address_verification']['suggested_corrections'])) {
            foreach ($toolResult['address_verification']['suggested_corrections'] as $field => $correctedValue) {
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

        // 6. Store enrichment metadata
        $rawData['ai_enriched'] = true;
        $rawData['ai_enriched_at'] = now()->toIso8601String();
        $rawData['ai_quality_score'] = $toolResult['quality_score'] ?? null;

        // Update the listing
        if (! empty($changes)) {
            $listing->update(['raw_data' => $rawData]);

            Log::info('Enrichment applied to listing', [
                'listing_id' => $listing->id,
                'changes' => $changes,
            ]);
        }
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

LANGUAGE RULES:
- Description: Keep in the SAME LANGUAGE as the original (Spanish stays Spanish, English stays English)
- Tags/Amenities: ALWAYS use ENGLISH, lowercase, snake_case format (e.g., "swimming_pool", "gym", "24_hour_security")
- Quality issues: Always in English

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

3. IMPROVE DESCRIPTION (improved_description):
   - Clean up formatting (remove excessive caps, fix spacing)
   - Remove duplicate information
   - Fix obvious typos
   - Keep the SAME LANGUAGE as original
   - Return null if no improvements needed

4. VERIFY ADDRESS (address_verification):
   - Check if location components are consistent
   - Suggest corrections for misspellings or inconsistencies
   - is_valid: true if address seems correct

5. SCORE QUALITY (quality_score):
   - Rate 0-100 based on: has description (20), has coordinates (20), has complete address (20), has all features (20), has images mentioned (20)

6. IDENTIFY ISSUES (quality_issues):
   - List specific problems found (be concise, in English)
   - E.g., "Missing coordinates", "Price seems unusually low for area"

7. SUGGEST TYPE (suggested_property_type):
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
                    'improved_description' => [
                        'type' => ['string', 'null'],
                        'description' => 'Cleaned up description text in same language, or null if no improvements needed',
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
