<?php

namespace App\Services;

use App\Enums\ApiOperation;
use App\Enums\ApiService;
use App\Models\ApiUsageLog;
use App\Services\AI\ClaudeCallContext;

class ApiUsageTracker
{
    /**
     * @param  array{input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int}  $usage
     */
    public function logClaudeUsage(
        ApiOperation $operation,
        array $usage,
        ?string $model = null,
        ?ClaudeCallContext $context = null,
        ?string $errorType = null,
        ?int $durationMs = null,
    ): ApiUsageLog {
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        $cacheCreationTokens = $usage['cache_creation_input_tokens'] ?? 0;
        $cacheReadTokens = $usage['cache_read_input_tokens'] ?? 0;

        return ApiUsageLog::create([
            'service' => ApiService::Claude,
            'operation' => $operation,
            'model' => $model ?? config('services.anthropic.model', 'claude-sonnet-4-20250514'),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_creation_tokens' => $cacheCreationTokens,
            'cache_read_tokens' => $cacheReadTokens,
            'cost_cents' => ApiUsageLog::calculateClaudeCostCents(
                $inputTokens,
                $outputTokens,
                $cacheCreationTokens,
                $cacheReadTokens
            ),
            'entity_type' => $context?->entityType,
            'entity_id' => $context?->entityId,
            'job_class' => $context?->jobClass,
            'error_type' => $errorType,
            'duration_ms' => $durationMs,
            'success' => $errorType === null,
        ]);
    }

    public function logZenRowsUsage(ApiOperation $operation, int $credits, string $url): ApiUsageLog
    {
        return ApiUsageLog::create([
            'service' => ApiService::ZenRows,
            'operation' => $operation,
            'credits_used' => $credits,
            'cost_cents' => 0,
            'metadata' => ['url' => $url],
        ]);
    }
}
