<?php

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Collection;

it('can create an agent', function () {
    $agent = Agent::factory()->create();

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($agent->id)->not->toBeNull()
        ->and($agent->name)->not->toBeEmpty();
});

it('belongs to an agency (nullable)', function () {
    $agency = Agency::factory()->create();
    $agent = Agent::factory()->for($agency)->create();

    expect($agent->agency)->toBeInstanceOf(Agency::class)
        ->and($agent->agency->id)->toBe($agency->id);
});

it('has many listings', function () {
    $agent = Agent::factory()->create();
    $listing = Listing::factory()->create(['agent_id' => $agent->id]);

    expect($agent->listings)->toBeInstanceOf(Collection::class)
        ->and($agent->listings)->toHaveCount(1)
        ->and($agent->listings->first()->id)->toBe($listing->id);
});

it('can be independent without an agency', function () {
    $agent = Agent::factory()->independent()->create();

    expect($agent->agency_id)->toBeNull()
        ->and($agent->agency)->toBeNull();
});

it('casts platform_profiles to array', function () {
    $profiles = [
        'inmuebles24' => 'https://www.inmuebles24.com/agente/test',
    ];

    $agent = Agent::factory()->create(['platform_profiles' => $profiles]);

    $agent->refresh();

    expect($agent->platform_profiles)->toBeArray()
        ->and($agent->platform_profiles)->toHaveKey('inmuebles24');
});

it('can be created with agency using state', function () {
    $agent = Agent::factory()->withAgency()->create();

    expect($agent->agency_id)->not->toBeNull()
        ->and($agent->agency)->toBeInstanceOf(Agency::class);
});

it('agency relationship returns null when agency_id is null', function () {
    $agent = Agent::factory()->create(['agency_id' => null]);

    expect($agent->agency)->toBeNull();
});
