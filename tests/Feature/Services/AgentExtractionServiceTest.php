<?php

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Listing;
use App\Models\Platform;
use App\Services\AgentExtractionService;

beforeEach(function () {
    $this->service = new AgentExtractionService;
    $this->platform = Platform::factory()->create(['slug' => 'inmuebles24', 'name' => 'Inmuebles24']);
});

it('creates a new agent from listing publisher data', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'agent_id' => null,
        'agency_id' => null,
        'raw_data' => [
            'title' => 'Departamento en renta',
            'publisher_name' => 'Juan Perez',
            'publisher_id' => '12345',
            'publisher_url' => 'https://inmuebles24.com/agente/juan-perez',
            'whatsapp' => '+523312345678',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();

    expect($listing->agent_id)->not->toBeNull()
        ->and($listing->agency_id)->toBeNull();

    $agent = Agent::find($listing->agent_id);

    expect($agent->name)->toBe('Juan Perez')
        ->and($agent->phone)->toBe('+523312345678')
        ->and($agent->platform_profiles)->toHaveKey('inmuebles24')
        ->and($agent->platform_profiles['inmuebles24']['id'])->toBe('12345');
});

it('creates a new agency when publisher name indicates company', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'agent_id' => null,
        'agency_id' => null,
        'raw_data' => [
            'title' => 'Departamento en renta',
            'publisher_name' => 'Inmobiliaria Los Pinos',
            'publisher_id' => '99999',
            'publisher_url' => 'https://inmuebles24.com/inmobiliaria/los-pinos',
            'whatsapp' => '+523398765432',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();

    expect($listing->agent_id)->toBeNull()
        ->and($listing->agency_id)->not->toBeNull();

    $agency = Agency::find($listing->agency_id);

    expect($agency->name)->toBe('Inmobiliaria Los Pinos')
        ->and($agency->phone)->toBe('+523398765432');
});

it('matches existing agent by platform profile id', function () {
    $existingAgent = Agent::factory()->create([
        'name' => 'Juan Perez',
        'platform_profiles' => [
            'inmuebles24' => [
                'id' => '12345',
                'url' => 'https://inmuebles24.com/agente/juan-perez',
            ],
        ],
    ]);

    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'agent_id' => null,
        'raw_data' => [
            'title' => 'Another property',
            'publisher_name' => 'Juan Perez',
            'publisher_id' => '12345',
            'whatsapp' => '+523312345678',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();

    expect($listing->agent_id)->toBe($existingAgent->id)
        ->and(Agent::count())->toBe(1); // No new agent created
});

it('matches existing agent by phone number', function () {
    $existingAgent = Agent::factory()->create([
        'name' => 'Juan Perez',
        'phone' => '+523312345678',
    ]);

    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'agent_id' => null,
        'raw_data' => [
            'title' => 'Another property',
            'publisher_name' => 'Juan P.',
            'publisher_id' => '99999', // Different ID
            'whatsapp' => '+52 33 1234 5678', // Same phone, different format
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();

    expect($listing->agent_id)->toBe($existingAgent->id)
        ->and(Agent::count())->toBe(1);
});

it('updates platform profile when matching existing agent', function () {
    $existingAgent = Agent::factory()->create([
        'name' => 'Juan Perez',
        'phone' => '+523312345678',
        'platform_profiles' => [
            'vivanuncios' => ['id' => '111', 'url' => 'https://vivanuncios.com/agente/111'],
        ],
    ]);

    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'agent_id' => null,
        'raw_data' => [
            'title' => 'Another property',
            'publisher_name' => 'Juan Perez',
            'publisher_id' => '12345',
            'publisher_url' => 'https://inmuebles24.com/agente/juan-perez',
            'whatsapp' => '+523312345678',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $existingAgent->refresh();

    expect($existingAgent->platform_profiles)
        ->toHaveKey('vivanuncios')
        ->toHaveKey('inmuebles24')
        ->and($existingAgent->platform_profiles['inmuebles24']['id'])->toBe('12345');
});

it('skips listing without publisher name', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'agent_id' => null,
        'raw_data' => [
            'title' => 'Departamento en renta',
            // No publisher_name
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();

    expect($listing->agent_id)->toBeNull()
        ->and($listing->agency_id)->toBeNull()
        ->and(Agent::count())->toBe(0)
        ->and(Agency::count())->toBe(0);
});

it('normalizes phone numbers correctly', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'agent_id' => null,
        'raw_data' => [
            'publisher_name' => 'Test Agent',
            'whatsapp' => '  +52 (33) 1234-5678  ',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $agent = Agent::first();

    expect($agent->phone)->toBe('+523312345678');
});

it('detects agency from explicit publisher type', function () {
    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'publisher_name' => 'ABC Properties',
            'publisher_type' => 'realEstate',
            'publisher_id' => '555',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();

    expect($listing->agency_id)->not->toBeNull()
        ->and($listing->agent_id)->toBeNull();
});

it('processes unlinked listings in batches', function () {
    // Create 5 listings with publisher data but no agent linked
    for ($i = 0; $i < 5; $i++) {
        Listing::factory()->create([
            'platform_id' => $this->platform->id,
            'agent_id' => null,
            'agency_id' => null,
            'raw_data' => [
                'publisher_name' => "Agent {$i}",
                'publisher_id' => "agent-{$i}",
                'whatsapp' => '+5233123456'.sprintf('%02d', $i),
            ],
        ]);
    }

    $processed = $this->service->processUnlinkedListings(10);

    expect($processed)->toBe(5)
        ->and(Agent::count())->toBe(5);
});

it('matches agency by name case-insensitively', function () {
    $existingAgency = Agency::factory()->create([
        'name' => 'Inmobiliaria Premium',
    ]);

    $listing = Listing::factory()->create([
        'platform_id' => $this->platform->id,
        'raw_data' => [
            'publisher_name' => 'INMOBILIARIA PREMIUM',
            'publisher_id' => '123',
        ],
    ]);

    $this->service->extractFromListing($listing);

    $listing->refresh();

    expect($listing->agency_id)->toBe($existingAgency->id)
        ->and(Agency::count())->toBe(1);
});
