<?php

namespace Database\Factories;

use App\Enums\ApiOperation;
use App\Enums\ApiService;
use App\Models\ApiUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiUsageLog>
 */
class ApiUsageLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service' => ApiService::Claude,
            'operation' => ApiOperation::PropertyCreation,
            'model' => 'claude-sonnet-4-20250514',
            'input_tokens' => fake()->numberBetween(1000, 10000),
            'output_tokens' => fake()->numberBetween(500, 2000),
            'cache_creation_tokens' => 0,
            'cache_read_tokens' => 0,
            'credits_used' => 0,
            'cost_cents' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ApiUsageLog $log) {
            if ($log->service === ApiService::Claude && $log->cost_cents === 0) {
                $log->cost_cents = ApiUsageLog::calculateClaudeCostCents(
                    $log->input_tokens,
                    $log->output_tokens,
                    $log->cache_creation_tokens,
                    $log->cache_read_tokens
                );
            }
        });
    }

    public function claude(): static
    {
        return $this->state(fn () => [
            'service' => ApiService::Claude,
            'operation' => ApiOperation::PropertyCreation,
            'model' => 'claude-sonnet-4-20250514',
            'input_tokens' => fake()->numberBetween(5000, 15000),
            'output_tokens' => fake()->numberBetween(1000, 3000),
            'credits_used' => 0,
        ]);
    }

    public function zenrows(): static
    {
        return $this->state(fn () => [
            'service' => ApiService::ZenRows,
            'operation' => fake()->randomElement([ApiOperation::SearchScrape, ApiOperation::ListingScrape]),
            'model' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'credits_used' => fake()->numberBetween(5, 25),
            'cost_cents' => 0,
        ]);
    }
}
