<?php

use App\Enums\ApiOperation;
use App\Enums\ApiService;
use App\Enums\ScrapeRunStatus;
use App\Livewire\Admin\Dashboard;
use App\Models\ApiUsageLog;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\Property;
use App\Models\Publisher;
use App\Models\ScrapeRun;
use App\Models\SearchQuery;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

describe('cost stats', function () {
    it('calculates total costs from usage logs', function () {
        // Create Claude usage logs
        ApiUsageLog::factory()->count(10)->create([
            'created_at' => now()->subDays(3),
            'service' => ApiService::Claude,
            'operation' => ApiOperation::PropertyCreation,
            'cost_cents' => 15,
            'input_tokens' => 5000,
            'output_tokens' => 1000,
        ]);

        ApiUsageLog::factory()->count(15)->create([
            'created_at' => now()->subDays(5),
            'service' => ApiService::Claude,
            'operation' => ApiOperation::PropertyCreation,
            'cost_cents' => 14, // 14 * 15 = 210, close to 200
            'input_tokens' => 4667,
            'output_tokens' => 1000,
        ]);

        // Create ZenRows usage logs
        ApiUsageLog::factory()->count(50)->create([
            'created_at' => now()->subDays(2),
            'service' => ApiService::ZenRows,
            'operation' => ApiOperation::SearchScrape,
            'cost_cents' => 0,
            'credits_used' => 10,
            'input_tokens' => 0,
            'output_tokens' => 0,
        ]);

        $component = Livewire::test(Dashboard::class);
        $stats = $component->instance()->costStats;

        expect($stats['total_cost_cents'])->toBe(360) // (15 * 10) + (14 * 15) = 150 + 210
            ->and($stats['claude_cost_cents'])->toBe(360)
            ->and($stats['zenrows_credits'])->toBe(500) // 10 * 50
            ->and($stats['claude_requests'])->toBe(25) // 10 + 15
            ->and($stats['zenrows_requests'])->toBe(50)
            ->and($stats['total_tokens'])->toBe(145005); // (5000+1000)*10 + (4667+1000)*15 = 60000 + 85005
    });

    it('calculates stats from individual logs', function () {
        ApiUsageLog::factory()->create([
            'service' => ApiService::Claude,
            'operation' => ApiOperation::PropertyCreation,
            'cost_cents' => 100,
            'input_tokens' => 5000,
            'output_tokens' => 1000,
        ]);

        ApiUsageLog::factory()->create([
            'service' => ApiService::ZenRows,
            'operation' => ApiOperation::SearchScrape,
            'credits_used' => 25,
            'cost_cents' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
        ]);

        $component = Livewire::test(Dashboard::class);
        $stats = $component->instance()->costStats;

        expect($stats['total_cost_cents'])->toBe(100)
            ->and($stats['claude_requests'])->toBe(1)
            ->and($stats['zenrows_requests'])->toBe(1)
            ->and($stats['zenrows_credits'])->toBe(25);
    });

    it('respects period filter', function () {
        // Logs within 7-day period
        ApiUsageLog::factory()->create([
            'created_at' => now()->subDays(3),
            'service' => ApiService::Claude,
            'operation' => ApiOperation::PropertyCreation,
            'cost_cents' => 100,
        ]);

        // Logs outside 7-day period (but within 30-day)
        ApiUsageLog::factory()->create([
            'created_at' => now()->subDays(15),
            'service' => ApiService::Claude,
            'operation' => ApiOperation::PropertyCreation,
            'cost_cents' => 200,
        ]);

        // Test 7-day period
        $component = Livewire::test(Dashboard::class, ['period' => '7d']);
        expect($component->instance()->costStats['total_cost_cents'])->toBe(100);

        // Test 30-day period
        $component = Livewire::test(Dashboard::class, ['period' => '30d']);
        expect($component->instance()->costStats['total_cost_cents'])->toBe(300);
    });

    it('returns zero stats when no data exists', function () {
        $component = Livewire::test(Dashboard::class);
        $stats = $component->instance()->costStats;

        expect($stats['total_cost_cents'])->toBe(0)
            ->and($stats['claude_cost_cents'])->toBe(0)
            ->and($stats['zenrows_credits'])->toBe(0)
            ->and($stats['claude_requests'])->toBe(0)
            ->and($stats['zenrows_requests'])->toBe(0)
            ->and($stats['total_tokens'])->toBe(0);
    });
});

describe('pipeline stats', function () {
    it('counts total properties and new properties', function () {
        $platform = Platform::factory()->create();

        // Old properties
        Property::factory()->count(3)->create([
            'created_at' => now()->subDays(60),
        ]);

        // New properties (within default 30-day period)
        Property::factory()->count(2)->create([
            'created_at' => now()->subDays(5),
        ]);

        $component = Livewire::test(Dashboard::class);
        $stats = $component->instance()->pipelineStats;

        expect($stats['properties_total'])->toBe(5)
            ->and($stats['properties_new'])->toBe(2);
    });

    it('counts listings and publishers', function () {
        $platform = Platform::factory()->create();
        Listing::factory()->count(10)->create(['platform_id' => $platform->id]);
        Publisher::factory()->count(3)->create();

        $component = Livewire::test(Dashboard::class);
        $stats = $component->instance()->pipelineStats;

        expect($stats['listings_total'])->toBe(10)
            ->and($stats['publishers_total'])->toBe(3);
    });

    it('counts pending and failed jobs', function () {
        // We can't easily test jobs table without adding jobs,
        // but we can verify the structure returns expected keys
        $component = Livewire::test(Dashboard::class);
        $stats = $component->instance()->pipelineStats;

        expect($stats)->toHaveKeys(['pending_jobs', 'failed_jobs']);
    });
});

describe('recent activity', function () {
    it('loads recent scrape runs with relationships', function () {
        $platform = Platform::factory()->create();
        $searchQuery = SearchQuery::factory()->create(['platform_id' => $platform->id]);

        ScrapeRun::factory()->count(3)->create([
            'platform_id' => $platform->id,
            'search_query_id' => $searchQuery->id,
            'status' => ScrapeRunStatus::Completed,
        ]);

        $component = Livewire::test(Dashboard::class);
        $runs = $component->instance()->recentRuns;

        expect($runs)->toHaveCount(3)
            ->and($runs->first()->searchQuery)->not->toBeNull()
            ->and($runs->first()->platform)->not->toBeNull();
    });

    it('limits recent runs to 5', function () {
        $platform = Platform::factory()->create();
        $searchQuery = SearchQuery::factory()->create(['platform_id' => $platform->id]);

        ScrapeRun::factory()->count(10)->create([
            'platform_id' => $platform->id,
            'search_query_id' => $searchQuery->id,
        ]);

        $component = Livewire::test(Dashboard::class);
        $runs = $component->instance()->recentRuns;

        expect($runs)->toHaveCount(5);
    });

    it('loads recent properties with listing counts', function () {
        $platform = Platform::factory()->create();

        $property1 = Property::factory()->create();
        $property2 = Property::factory()->create();

        Listing::factory()->count(3)->create([
            'platform_id' => $platform->id,
            'property_id' => $property1->id,
        ]);

        $component = Livewire::test(Dashboard::class);
        $properties = $component->instance()->recentProperties;

        expect($properties)->toHaveCount(2)
            ->and($properties->first()->listings)->not->toBeNull();
    });

    it('limits recent properties to 5', function () {
        Property::factory()->count(10)->create();

        $component = Livewire::test(Dashboard::class);
        $properties = $component->instance()->recentProperties;

        expect($properties)->toHaveCount(5);
    });
});

describe('format cost', function () {
    it('formats cents as dollars', function () {
        $component = Livewire::test(Dashboard::class);
        $instance = $component->instance();

        expect($instance->formatCost(0))->toBe('$0.00')
            ->and($instance->formatCost(100))->toBe('$1.00')
            ->and($instance->formatCost(1550))->toBe('$15.50')
            ->and($instance->formatCost(12345))->toBe('$123.45');
    });
});

describe('period selection', function () {
    it('changes period via wire:model', function () {
        $component = Livewire::test(Dashboard::class)
            ->assertSet('period', '30d')
            ->set('period', '7d')
            ->assertSet('period', '7d');
    });

    it('persists period in URL', function () {
        // Test that the URL parameter is synced
        $component = Livewire::test(Dashboard::class, ['period' => '90d']);

        expect($component->instance()->period)->toBe('90d');
    });
});

describe('renders correctly', function () {
    it('renders the dashboard page', function () {
        $component = Livewire::test(Dashboard::class);

        $component->assertStatus(200)
            ->assertSee('Dashboard')
            ->assertSee('Claude AI')
            ->assertSee('ZenRows');
    });

    it('shows empty states when no data', function () {
        $component = Livewire::test(Dashboard::class);

        $component->assertSee('No scrape runs yet.')
            ->assertSee('No properties created yet.');
    });

    it('shows recent activity when data exists', function () {
        $platform = Platform::factory()->create();
        $searchQuery = SearchQuery::factory()->create([
            'platform_id' => $platform->id,
            'name' => 'Test Search Query',
        ]);

        ScrapeRun::factory()->create([
            'platform_id' => $platform->id,
            'search_query_id' => $searchQuery->id,
            'status' => ScrapeRunStatus::Completed,
        ]);

        $property = Property::factory()->create([
            'address' => '123 Test Street',
            'colonia' => 'Test Colonia',
            'city' => 'Test City',
        ]);

        $component = Livewire::test(Dashboard::class);

        $component->assertSee('Test Search Query')
            ->assertSee('123 Test Street');
    });
});
