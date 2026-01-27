<?php

use App\Models\ApiUsageLog;
use App\Services\AI\ClaudeClient;
use App\Services\AI\PropertyDescriptionExtractionService;
use App\Services\ApiUsageTracker;

beforeEach(function () {
    $this->claudeClient = Mockery::mock(ClaudeClient::class);
    $this->usageTracker = Mockery::mock(ApiUsageTracker::class);
    $this->usageTracker->shouldReceive('logClaudeUsage')
        ->andReturn(Mockery::mock(ApiUsageLog::class));

    $this->service = new PropertyDescriptionExtractionService(
        $this->claudeClient,
        $this->usageTracker
    );
});

describe('normalizeResult flattening', function () {
    it('flattens included_services from objects to strings with details', function () {
        // This is the actual format Claude returns based on the tool schema
        $aiResponse = createAiResponse([
            'pricing' => [
                'included_services' => [
                    ['service' => 'internet', 'details' => '50mb'],
                    ['service' => 'electricity', 'details' => 'up to $500 monthly'],
                    ['service' => 'water', 'details' => null],
                ],
            ],
        ]);

        mockClaudeResponse($this->claudeClient, $aiResponse);

        $result = $this->service->extract('Test description');

        expect($result['pricing']['included_services'])->toBe([
            'internet: 50mb',
            'electricity: up to $500 monthly',
            'water',
        ]);
    });

    it('flattens extra_costs from objects to strings', function () {
        $aiResponse = createAiResponse([
            'pricing' => [
                'extra_costs' => [
                    ['item' => 'parking', 'price' => 1500, 'period' => 'monthly'],
                    ['item' => 'storage', 'price' => 500, 'period' => 'monthly'],
                ],
            ],
        ]);

        mockClaudeResponse($this->claudeClient, $aiResponse);

        $result = $this->service->extract('Test description');

        expect($result['pricing']['extra_costs'])->toBe([
            'parking',
            'storage',
        ]);
    });

    it('flattens nearby locations from objects to strings', function () {
        $aiResponse = createAiResponse([
            'location' => [
                'nearby' => [
                    ['name' => 'ITESO', 'type' => 'university', 'distance' => '5 min'],
                    ['name' => 'Plaza Andares', 'type' => 'mall', 'distance' => '10 min'],
                    ['name' => 'Estación Periférico', 'type' => 'metro', 'distance' => null],
                ],
            ],
        ]);

        mockClaudeResponse($this->claudeClient, $aiResponse);

        $result = $this->service->extract('Test description');

        expect($result['location']['nearby'])->toBe([
            'ITESO',
            'Plaza Andares',
            'Estación Periférico',
        ]);
    });

    it('handles mixed formats (strings and objects) gracefully', function () {
        // Edge case: some items are already strings, some are objects
        $aiResponse = createAiResponse([
            'pricing' => [
                'included_services' => [
                    'gas',  // Already a string
                    ['service' => 'internet', 'details' => '100mb'],
                    'cleaning',  // Already a string
                ],
            ],
        ]);

        mockClaudeResponse($this->claudeClient, $aiResponse);

        $result = $this->service->extract('Test description');

        expect($result['pricing']['included_services'])->toBe([
            'gas',
            'internet: 100mb',
            'cleaning',
        ]);
    });

    it('filters out empty values', function () {
        $aiResponse = createAiResponse([
            'pricing' => [
                'included_services' => [
                    ['service' => null, 'details' => null],
                    ['service' => 'internet', 'details' => '50mb'],
                    ['service' => '', 'details' => ''],
                ],
                'extra_costs' => [
                    ['item' => null],
                    ['item' => 'parking'],
                ],
            ],
            'location' => [
                'nearby' => [
                    ['name' => '', 'type' => 'mall'],
                    ['name' => 'ITESO', 'type' => 'university'],
                ],
            ],
        ]);

        mockClaudeResponse($this->claudeClient, $aiResponse);

        $result = $this->service->extract('Test description');

        expect($result['pricing']['included_services'])->toBe(['internet: 50mb']);
        expect($result['pricing']['extra_costs'])->toBe(['parking']);
        expect($result['location']['nearby'])->toBe(['ITESO']);
    });

    it('handles empty arrays', function () {
        $aiResponse = createAiResponse([
            'pricing' => [
                'included_services' => [],
                'extra_costs' => [],
            ],
            'location' => [
                'nearby' => [],
            ],
        ]);

        mockClaudeResponse($this->claudeClient, $aiResponse);

        $result = $this->service->extract('Test description');

        expect($result['pricing']['included_services'])->toBe([]);
        expect($result['pricing']['extra_costs'])->toBe([]);
        expect($result['location']['nearby'])->toBe([]);
    });
});

describe('extract with realistic AI response', function () {
    it('processes a complete coliving property response', function () {
        // Realistic response matching what Claude actually returns
        $aiResponse = createAiResponse([
            'property' => [
                'property_type' => 'room',
                'operation_type' => 'rent',
                'colonia' => 'Providencia',
                'city' => 'Guadalajara',
                'state' => 'Jalisco',
                'bedrooms' => 1,
                'bathrooms' => 1,
                'built_size_m2' => 25,
            ],
            'pricing' => [
                'price' => 12500,
                'price_currency' => 'MXN',
                'price_range' => ['min' => 12500, 'max' => 13500, 'currency' => 'MXN'],
                'included_services' => [
                    ['service' => 'internet', 'details' => '50mb fibra óptica'],
                    ['service' => 'electricity', 'details' => 'hasta $500 mensuales'],
                    ['service' => 'water', 'details' => null],
                    ['service' => 'gas', 'details' => null],
                    ['service' => 'cleaning', 'details' => 'áreas comunes'],
                ],
                'extra_costs' => [
                    ['item' => 'parking', 'price' => 1500, 'period' => 'monthly', 'note' => 'opcional'],
                ],
            ],
            'terms' => [
                'deposit_months' => 1,
                'advance_months' => 1,
                'guarantor_required' => false,
                'pets_allowed' => false,
                'max_occupants' => 1,
            ],
            'amenities' => [
                'unit' => ['murphy_bed', 'closet', 'kitchenette', 'ac', 'desk'],
                'building' => ['gym', 'rooftop', 'coworking', 'laundry'],
                'services' => ['internet', 'security', 'maintenance'],
                'optional' => [],
            ],
            'location' => [
                'building_name' => 'Coliving Providencia',
                'building_type' => 'coliving',
                'nearby' => [
                    ['name' => 'ITESO', 'type' => 'university', 'distance' => '10 min'],
                    ['name' => 'Plaza Andares', 'type' => 'mall', 'distance' => '5 min'],
                ],
            ],
            'inferred' => [
                'target_audience' => ['students', 'young_professionals'],
                'occupancy_type' => 'single_person',
                'property_condition' => 'excellent',
            ],
            'description' => '<h3>Introducción</h3><p>Estudio en coliving Providencia.</p>',
            'quality_score' => 85,
        ]);

        mockClaudeResponse($this->claudeClient, $aiResponse);

        $result = $this->service->extract('Rento estudio amueblado en Providencia...');

        // Verify flattened arrays work with blade components (all items are strings)
        expect($result['pricing']['included_services'])->each->toBeString();
        expect($result['pricing']['extra_costs'])->each->toBeString();
        expect($result['location']['nearby'])->each->toBeString();

        // Verify specific flattened values
        expect($result['pricing']['included_services'])->toContain('internet: 50mb fibra óptica');
        expect($result['pricing']['included_services'])->toContain('water');
        expect($result['pricing']['extra_costs'])->toBe(['parking']);
        expect($result['location']['nearby'])->toBe(['ITESO', 'Plaza Andares']);

        // Verify other fields preserved
        expect($result['property']['property_type'])->toBe('room');
        expect($result['pricing']['price'])->toBe(12500);
        expect($result['terms']['guarantor_required'])->toBeFalse();
        expect($result['amenities']['unit'])->toContain('murphy_bed');
        expect($result['quality_score'])->toBe(85);
    });
});

describe('getEmptyStructure', function () {
    it('returns proper default structure', function () {
        $structure = PropertyDescriptionExtractionService::getEmptyStructure();

        expect($structure)->toHaveKeys([
            'property', 'pricing', 'terms', 'amenities', 'location', 'inferred', 'description', 'quality_score',
        ]);

        expect($structure['pricing']['included_services'])->toBe([]);
        expect($structure['pricing']['extra_costs'])->toBe([]);
        expect($structure['location']['nearby'])->toBe([]);
        expect($structure['property']['city'])->toBe('Guadalajara');
        expect($structure['property']['state'])->toBe('Jalisco');
    });
});

describe('extract handles errors', function () {
    it('returns empty structure when AI does not return tool use', function () {
        $this->claudeClient
            ->shouldReceive('withTracking')
            ->andReturnSelf();

        $this->claudeClient
            ->shouldReceive('message')
            ->andReturn(['content' => [['type' => 'text', 'text' => 'No tool use']]]);

        $this->claudeClient
            ->shouldReceive('extractToolUse')
            ->with(Mockery::any(), 'extract_property_data')
            ->andReturn(null);

        $this->claudeClient
            ->shouldReceive('getUsage')
            ->andReturn(['input_tokens' => 100, 'output_tokens' => 50]);

        $result = $this->service->extract('Some description');

        expect($result)->toBe(PropertyDescriptionExtractionService::getEmptyStructure());
    });
});

// Helper functions

function createAiResponse(array $toolInput): array
{
    $defaults = PropertyDescriptionExtractionService::getEmptyStructure();

    return array_replace_recursive($defaults, $toolInput);
}

function mockClaudeResponse(Mockery\MockInterface $mock, array $toolInput): void
{
    $response = [
        'content' => [
            [
                'type' => 'tool_use',
                'name' => 'extract_property_data',
                'input' => $toolInput,
            ],
        ],
    ];

    $mock->shouldReceive('withTracking')->andReturnSelf();
    $mock->shouldReceive('message')->andReturn($response);
    $mock->shouldReceive('extractToolUse')
        ->with($response, 'extract_property_data')
        ->andReturn($toolInput);
    $mock->shouldReceive('getUsage')
        ->andReturn(['input_tokens' => 500, 'output_tokens' => 1000]);
}
