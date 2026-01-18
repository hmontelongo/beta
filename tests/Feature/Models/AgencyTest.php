<?php

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Collection;

it('can create an agency', function () {
    $agency = Agency::factory()->create();

    expect($agency)->toBeInstanceOf(Agency::class)
        ->and($agency->id)->not->toBeNull()
        ->and($agency->name)->not->toBeEmpty();
});

it('has many agents', function () {
    $agency = Agency::factory()->create();
    $agents = Agent::factory()->count(3)->for($agency)->create();

    expect($agency->agents)->toBeInstanceOf(Collection::class)
        ->and($agency->agents)->toHaveCount(3)
        ->and($agency->agents->first())->toBeInstanceOf(Agent::class);
});

it('has many listings', function () {
    $agency = Agency::factory()->create();
    $listings = Listing::factory()->count(2)->for($agency)->create();

    expect($agency->listings)->toBeInstanceOf(Collection::class)
        ->and($agency->listings)->toHaveCount(2)
        ->and($agency->listings->first())->toBeInstanceOf(Listing::class);
});

it('casts platform_profiles to array', function () {
    $platformProfiles = [
        'inmuebles24' => [
            'profile_url' => 'https://inmuebles24.com/inmobiliarias/test-agency',
            'agent_count' => 5,
        ],
        'vivanuncios' => [
            'profile_url' => 'https://vivanuncios.com/agencias/test-agency',
        ],
    ];

    $agency = Agency::factory()->create(['platform_profiles' => $platformProfiles]);

    $agency->refresh();

    expect($agency->platform_profiles)->toBeArray()
        ->and($agency->platform_profiles['inmuebles24']['agent_count'])->toBe(5)
        ->and($agency->platform_profiles)->toHaveKey('vivanuncios');
});

it('allows nullable fields', function () {
    $agency = Agency::factory()->create([
        'email' => null,
        'phone' => null,
        'platform_profiles' => null,
    ]);

    expect($agency->email)->toBeNull()
        ->and($agency->phone)->toBeNull()
        ->and($agency->platform_profiles)->toBeNull();
});

it('can be created with platform profiles', function () {
    $agency = Agency::factory()->withPlatformProfiles()->create();

    expect($agency->platform_profiles)->toBeArray()
        ->and($agency->platform_profiles)->toHaveKey('inmuebles24');
});

it('stores Mexican phone format', function () {
    $agency = Agency::factory()->create();

    if ($agency->phone !== null) {
        expect($agency->phone)->toStartWith('+52 33');
    } else {
        expect($agency->phone)->toBeNull();
    }
});

it('can have multiple agents from different listings', function () {
    $agency = Agency::factory()->create();
    $agent1 = Agent::factory()->for($agency)->create();
    $agent2 = Agent::factory()->for($agency)->create();

    Listing::factory()->for($agency)->create(['agent_id' => $agent1->id]);
    Listing::factory()->for($agency)->create(['agent_id' => $agent2->id]);
    Listing::factory()->for($agency)->create(['agent_id' => null]);

    expect($agency->agents)->toHaveCount(2)
        ->and($agency->listings)->toHaveCount(3);
});
