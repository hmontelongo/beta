<?php

namespace App\Services\AI;

use App\Enums\ApiOperation;
use App\Services\ApiUsageTracker;
use Illuminate\Support\Facades\Log;

class PropertyDescriptionExtractionService
{
    public function __construct(
        protected ClaudeClient $claude,
        protected ApiUsageTracker $usageTracker,
    ) {}

    /**
     * Extract structured property data from a natural language description.
     *
     * @return array{
     *     property: array<string, mixed>,
     *     pricing: array<string, mixed>,
     *     terms: array<string, mixed>,
     *     amenities: array<string, mixed>,
     *     location: array<string, mixed>,
     *     inferred: array<string, mixed>,
     *     description: string,
     *     quality_score: int
     * }
     */
    public function extract(string $description): array
    {
        $response = $this->claude->message(
            messages: [
                ['role' => 'user', 'content' => $this->buildUserMessage($description)],
            ],
            tools: [$this->getExtractionToolSchema()],
            system: $this->getSystemPrompt()
        );

        $toolResult = $this->claude->extractToolUse($response, 'extract_property_data');
        $usage = $this->claude->getUsage($response);

        $this->usageTracker->logClaudeUsage(ApiOperation::PropertyCreation, $usage);

        if (! $toolResult) {
            Log::warning('AI did not return structured property data', [
                'description_length' => strlen($description),
            ]);

            // Return empty structure so the form still works
            return $this->getEmptyStructure();
        }

        Log::info('Property description extracted', [
            'quality_score' => $toolResult['quality_score'] ?? 0,
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
        ]);

        return $this->normalizeResult($toolResult);
    }

    protected function buildUserMessage(string $description): string
    {
        return <<<MESSAGE
## Property Description to Analyze

The following is a natural language description of a property written by a real estate agent in Mexico. Extract all structured data and write a comprehensive HTML description.

```
{$description}
```

Use the extract_property_data tool to return the structured data and formatted description.
MESSAGE;
    }

    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a real estate data extraction specialist for the Mexican market. Your task is to extract structured property data from natural language descriptions written by agents and create a well-formatted HTML description.

LANGUAGE RULES - CRITICAL:
- Description: MUST stay in SPANISH (the same language as input)
- NEVER translate descriptions. Translating is a DATA QUALITY ERROR.
- Tags/Amenities: ALWAYS use ENGLISH, lowercase, snake_case format
- All extracted values: Use appropriate types (numbers, booleans, arrays)

CONTEXT:
- These are property descriptions from Mexican real estate agents
- Descriptions are typically in Spanish
- Prices are in MXN (Mexican Pesos) unless USD is explicitly mentioned
- Location is typically in Jalisco, Mexico (Guadalajara metro area)
- Common colonias: Providencia, Americana, Chapalita, Country Club, Colomos, Lafayette, Arcos Vallarta, Puerta de Hierro

=== YOUR 6 EXTRACTION TASKS ===

TASK 1: EXTRACT PROPERTY BASICS
- property_type: house, apartment, land, commercial, office, warehouse, room
- operation_type: sale, rent (look for "venta", "vendo" → sale; "renta", "rento" → rent)
- colonia: The neighborhood name (e.g., "Providencia", "Puerta de Hierro")
- city: Default to "Guadalajara" if in Jalisco and not specified
- state: Default to "Jalisco" for this region
- address: Street address if mentioned
- bedrooms, bathrooms, half_bathrooms, built_size_m2, lot_size_m2, parking_spots, age_years

TASK 2: EXTRACT PRICING DATA
Into the pricing object:
- price: The asking price in numeric format
- price_currency: MXN or USD
- price_range: {min, max, currency} if a range is mentioned (e.g., "desde $12,500 a $13,500")
- For "millones" multiply by 1,000,000; for "mil" multiply by 1,000
- extra_costs: [{item, price, period, note}] - parking fees, maintenance, utilities that cost extra
- included_services: [{service, details}] - internet, cleaning, utilities included in price
- price_per_m2: calculate if possible

Examples:
- "PRECIOS DESDE $12,500 a $13,500" → price_range: {min: 12500, max: 13500, currency: "MXN"}
- "Estacionamiento $1,500 mensuales" → extra_costs: [{item: "parking", price: 1500, period: "monthly"}]
- "Internet (50 mb) incluido" → included_services: [{service: "internet", details: "50mb"}]

TASK 3: EXTRACT RENTAL/PURCHASE TERMS
Into the terms object:
- deposit_months: deposit required (e.g., "1 mes de depósito" → 1)
- advance_months: rent in advance (e.g., "mes adelantado" → 1)
- income_proof_months: months of income statements required (e.g., "últimos 3 recibos de nómina" → 3)
- guarantor_required: look for "aval", "fiador", "NO SE PIDE AVAL" (true/false/null)
- legal_policy: any mention of "póliza jurídica" or legal requirements
- pets_allowed: look for "mascotas", "pet friendly", "no se aceptan mascotas" (true/false/null)
- max_occupants: if specified (e.g., "PARA 1 PERSONA" → 1)
- restrictions: array of any other restrictions mentioned

Examples:
- "1 mes de depósito y 1 mes de renta por adelantado" → deposit_months: 1, advance_months: 1
- "NO SE PIDE AVAL" → guarantor_required: false
- "No se aceptan mascotas" → pets_allowed: false

TASK 4: CATEGORIZE AMENITIES
Into the amenities object with 4 categories:
- unit: amenities inside the unit (murphy_bed, closet, kitchenette, ac, tv, desk, washing_machine, furnished)
- building: shared building amenities (gym, rooftop, coworking, pool, lobby, recording_studio, terrace)
- services: included services (internet, maintenance, security, cleaning, gas, water, electricity)
- optional: amenities that cost extra or vary by unit

All amenities in English snake_case format.

TASK 5: EXTRACT LOCATION & INFERRED DATA
Into the location object:
- building_name: official building/development name if mentioned
- building_type: coliving, residential, mixed_use, etc.
- nearby: [{name, type, distance}] - universities, malls, metro stations

Into the inferred object:
- target_audience: who this property is marketed to (students, families, professionals, couples)
- occupancy_type: single_person, couple, family, roommates
- property_condition: new, excellent, good, needs_work

TASK 6: WRITE A STRUCTURED HTML DESCRIPTION

Write a well-organized property description that is ACCURATE and COMPLETE. Do not exaggerate.

OUTPUT FORMAT: Clean HTML (rendered directly on the page).

STRUCTURE (use this consistent format):

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
- Only include information actually in the source description
- Do not invent or assume features
- If unclear, omit rather than guess
- No exaggeration - describe what IS there
- Use null for unknown values

LENGTH: As comprehensive as needed. Include all relevant details from the original description.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getExtractionToolSchema(): array
    {
        return [
            'name' => 'extract_property_data',
            'description' => 'Extract structured property data from a description and create an HTML description.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'property' => [
                        'type' => 'object',
                        'description' => 'Basic property information',
                        'properties' => [
                            'property_type' => [
                                'type' => ['string', 'null'],
                                'enum' => ['house', 'apartment', 'land', 'commercial', 'office', 'warehouse', 'room', null],
                            ],
                            'operation_type' => [
                                'type' => ['string', 'null'],
                                'enum' => ['sale', 'rent', null],
                            ],
                            'colonia' => ['type' => ['string', 'null']],
                            'city' => ['type' => ['string', 'null']],
                            'state' => ['type' => ['string', 'null']],
                            'address' => ['type' => ['string', 'null']],
                            'bedrooms' => ['type' => ['integer', 'null']],
                            'bathrooms' => ['type' => ['integer', 'null']],
                            'half_bathrooms' => ['type' => ['integer', 'null']],
                            'built_size_m2' => ['type' => ['number', 'null']],
                            'lot_size_m2' => ['type' => ['number', 'null']],
                            'parking_spots' => ['type' => ['integer', 'null']],
                            'age_years' => ['type' => ['integer', 'null']],
                        ],
                    ],
                    'pricing' => [
                        'type' => 'object',
                        'description' => 'Pricing information extracted from description',
                        'properties' => [
                            'price' => ['type' => ['number', 'null']],
                            'price_currency' => [
                                'type' => 'string',
                                'enum' => ['MXN', 'USD'],
                                'default' => 'MXN',
                            ],
                            'price_range' => [
                                'type' => ['object', 'null'],
                                'description' => 'Price range if mentioned (e.g., "desde $12,500 a $13,500")',
                                'properties' => [
                                    'min' => ['type' => ['number', 'null']],
                                    'max' => ['type' => ['number', 'null']],
                                    'currency' => ['type' => 'string'],
                                ],
                            ],
                            'price_per_m2' => ['type' => ['number', 'null']],
                            'maintenance_fee' => ['type' => ['number', 'null']],
                            'extra_costs' => [
                                'type' => 'array',
                                'description' => 'Costs that are extra (parking, utilities, etc.)',
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
                                'description' => 'Services included in the price',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'service' => ['type' => 'string'],
                                        'details' => ['type' => ['string', 'null']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'terms' => [
                        'type' => 'object',
                        'description' => 'Rental/purchase terms extracted from description',
                        'properties' => [
                            'deposit_months' => ['type' => ['integer', 'null']],
                            'advance_months' => ['type' => ['integer', 'null']],
                            'income_proof_months' => ['type' => ['integer', 'null']],
                            'guarantor_required' => ['type' => ['boolean', 'null']],
                            'legal_policy' => [
                                'type' => ['string', 'null'],
                                'description' => 'Legal policy mentioned (póliza jurídica, etc.)',
                            ],
                            'pets_allowed' => ['type' => ['boolean', 'null']],
                            'max_occupants' => ['type' => ['integer', 'null']],
                            'restrictions' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'amenities' => [
                        'type' => 'object',
                        'description' => 'Amenities categorized by type (all in English snake_case)',
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
                        'description' => 'Location and building details',
                        'properties' => [
                            'building_name' => ['type' => ['string', 'null']],
                            'building_type' => [
                                'type' => ['string', 'null'],
                                'description' => 'Type of building (coliving, residential, mixed_use, etc.)',
                            ],
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
                        'description' => 'Inferred information about the property',
                        'properties' => [
                            'target_audience' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'description' => 'Who this property targets (students, families, professionals)',
                            ],
                            'occupancy_type' => [
                                'type' => ['string', 'null'],
                                'description' => 'Type of occupancy (single_person, couple, family, roommates)',
                            ],
                            'property_condition' => [
                                'type' => ['string', 'null'],
                                'description' => 'Condition (new, excellent, good, needs_work)',
                            ],
                        ],
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Clean HTML description in Spanish. Use <h3> for headers (Introducción, El Espacio, Amenidades del Edificio, Servicios Incluidos, Requisitos, Ubicación), <p> for paragraphs, <ul>/<li> for lists. Omit empty sections.',
                    ],
                    'quality_score' => [
                        'type' => 'integer',
                        'description' => 'Data completeness score 0-100',
                        'minimum' => 0,
                        'maximum' => 100,
                    ],
                ],
                'required' => ['property', 'pricing', 'terms', 'amenities', 'location', 'inferred', 'description', 'quality_score'],
            ],
        ];
    }

    /**
     * Get the empty/default structure for extracted property data.
     * Used by both the service and Livewire components.
     *
     * @return array<string, mixed>
     */
    public static function getEmptyStructure(): array
    {
        return [
            'property' => [
                'property_type' => null,
                'operation_type' => null,
                'colonia' => null,
                'city' => 'Guadalajara',
                'state' => 'Jalisco',
                'address' => null,
                'bedrooms' => null,
                'bathrooms' => null,
                'half_bathrooms' => null,
                'built_size_m2' => null,
                'lot_size_m2' => null,
                'parking_spots' => null,
                'age_years' => null,
            ],
            'pricing' => [
                'price' => null,
                'price_currency' => 'MXN',
                'price_range' => null,
                'price_per_m2' => null,
                'maintenance_fee' => null,
                'extra_costs' => [],
                'included_services' => [],
            ],
            'terms' => [
                'deposit_months' => null,
                'advance_months' => null,
                'income_proof_months' => null,
                'guarantor_required' => null,
                'legal_policy' => null,
                'pets_allowed' => null,
                'max_occupants' => null,
                'restrictions' => [],
            ],
            'amenities' => [
                'unit' => [],
                'building' => [],
                'services' => [],
                'optional' => [],
            ],
            'location' => [
                'building_name' => null,
                'building_type' => null,
                'nearby' => [],
            ],
            'inferred' => [
                'target_audience' => [],
                'occupancy_type' => null,
                'property_condition' => null,
            ],
            'description' => '',
            'quality_score' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function normalizeResult(array $result): array
    {
        $empty = $this->getEmptyStructure();

        // Flatten included_services from objects [{service, details}] to strings
        $includedServices = collect($result['pricing']['included_services'] ?? [])
            ->map(fn ($item) => is_array($item)
                ? (! empty($item['details']) ? "{$item['service']}: {$item['details']}" : ($item['service'] ?? ''))
                : $item
            )
            ->filter()
            ->values()
            ->all();

        // Flatten extra_costs from objects [{item, price, period}] to strings
        $extraCosts = collect($result['pricing']['extra_costs'] ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['item'] ?? '') : $item)
            ->filter()
            ->values()
            ->all();

        // Flatten nearby locations from objects [{name, type}] to strings
        $nearby = collect($result['location']['nearby'] ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['name'] ?? '') : $item)
            ->filter()
            ->values()
            ->all();

        $pricing = array_merge($empty['pricing'], $result['pricing'] ?? []);
        $pricing['included_services'] = $includedServices;
        $pricing['extra_costs'] = $extraCosts;

        $location = array_merge($empty['location'], $result['location'] ?? []);
        $location['nearby'] = $nearby;

        return [
            'property' => array_merge($empty['property'], $result['property'] ?? []),
            'pricing' => $pricing,
            'terms' => array_merge($empty['terms'], $result['terms'] ?? []),
            'amenities' => array_merge($empty['amenities'], $result['amenities'] ?? []),
            'location' => $location,
            'inferred' => array_merge($empty['inferred'], $result['inferred'] ?? []),
            'description' => $result['description'] ?? '',
            'quality_score' => $result['quality_score'] ?? 0,
        ];
    }
}
