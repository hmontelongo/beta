<?php

use App\Enums\ApiOperation;
use App\Enums\ApiService;
use App\Models\ApiUsageLog;
use App\Services\ApiUsageTracker;

describe('logClaudeUsage', function () {
    it('logs claude usage with tokens and calculates cost', function () {
        $tracker = new ApiUsageTracker;

        $log = $tracker->logClaudeUsage(
            ApiOperation::PropertyCreation,
            ['input_tokens' => 10000, 'output_tokens' => 2000]
        );

        expect($log)->toBeInstanceOf(ApiUsageLog::class)
            ->and($log->service)->toBe(ApiService::Claude)
            ->and($log->operation)->toBe(ApiOperation::PropertyCreation)
            ->and($log->input_tokens)->toBe(10000)
            ->and($log->output_tokens)->toBe(2000)
            ->and($log->cost_cents)->toBe(6); // (10000 * $3 / 1M) + (2000 * $15 / 1M) = 6 cents
    });

    it('logs claude usage with cache tokens', function () {
        $tracker = new ApiUsageTracker;

        $log = $tracker->logClaudeUsage(
            ApiOperation::PropertyCreation,
            [
                'input_tokens' => 10000,
                'output_tokens' => 2000,
                'cache_creation_input_tokens' => 5000,
                'cache_read_input_tokens' => 3000,
            ]
        );

        expect($log->cache_creation_tokens)->toBe(5000)
            ->and($log->cache_read_tokens)->toBe(3000)
            ->and($log->cost_cents)->toBe(8);
    });

    it('uses default model from config', function () {
        $tracker = new ApiUsageTracker;

        $log = $tracker->logClaudeUsage(
            ApiOperation::PropertyCreation,
            ['input_tokens' => 100, 'output_tokens' => 50]
        );

        expect($log->model)->toBe(config('services.anthropic.model', 'claude-sonnet-4-20250514'));
    });

    it('allows custom model to be specified', function () {
        $tracker = new ApiUsageTracker;

        $log = $tracker->logClaudeUsage(
            ApiOperation::PropertyCreation,
            ['input_tokens' => 100, 'output_tokens' => 50],
            'claude-3-haiku'
        );

        expect($log->model)->toBe('claude-3-haiku');
    });
});

describe('logZenRowsUsage', function () {
    it('logs zenrows usage with credits', function () {
        $tracker = new ApiUsageTracker;

        $log = $tracker->logZenRowsUsage(
            ApiOperation::SearchScrape,
            15,
            'https://example.com/search'
        );

        expect($log)->toBeInstanceOf(ApiUsageLog::class)
            ->and($log->service)->toBe(ApiService::ZenRows)
            ->and($log->operation)->toBe(ApiOperation::SearchScrape)
            ->and($log->credits_used)->toBe(15)
            ->and($log->cost_cents)->toBe(0)
            ->and($log->metadata)->toBe(['url' => 'https://example.com/search']);
    });

    it('stores url in metadata', function () {
        $tracker = new ApiUsageTracker;
        $url = 'https://inmuebles24.com/properties/abc123';

        $log = $tracker->logZenRowsUsage(ApiOperation::ListingScrape, 10, $url);

        expect($log->metadata['url'])->toBe($url);
    });
});

describe('cost calculation', function () {
    it('calculates cost correctly for various token amounts', function (int $input, int $output, int $expectedCents) {
        $costCents = ApiUsageLog::calculateClaudeCostCents($input, $output);

        expect($costCents)->toBe($expectedCents);
    })->with([
        'small request' => [1000, 500, 1],
        'medium request' => [10000, 2000, 6],
        'large request' => [100000, 10000, 45],
        'zero tokens' => [0, 0, 0],
    ]);

    it('includes cache tokens in cost calculation', function () {
        $costCents = ApiUsageLog::calculateClaudeCostCents(10000, 2000, 50000, 100000);

        expect($costCents)->toBe(28);
    });
});
