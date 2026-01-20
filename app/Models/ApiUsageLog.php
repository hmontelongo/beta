<?php

namespace App\Models;

use App\Enums\ApiOperation;
use App\Enums\ApiService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    /** @use HasFactory<\Database\Factories\ApiUsageLogFactory> */
    use HasFactory;

    protected $guarded = [];

    private const CLAUDE_PRICING = [
        'input' => 3.00,
        'output' => 15.00,
        'cache_creation' => 3.75,
        'cache_read' => 0.30,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service' => ApiService::class,
            'operation' => ApiOperation::class,
            'metadata' => 'array',
        ];
    }

    public static function calculateClaudeCostCents(
        int $inputTokens,
        int $outputTokens,
        int $cacheCreationTokens = 0,
        int $cacheReadTokens = 0
    ): int {
        $cost = ($inputTokens / 1_000_000) * self::CLAUDE_PRICING['input']
            + ($outputTokens / 1_000_000) * self::CLAUDE_PRICING['output']
            + ($cacheCreationTokens / 1_000_000) * self::CLAUDE_PRICING['cache_creation']
            + ($cacheReadTokens / 1_000_000) * self::CLAUDE_PRICING['cache_read'];

        return (int) round($cost * 100);
    }

    public function getFormattedCostAttribute(): string
    {
        return '$'.number_format($this->cost_cents / 100, 4);
    }

    public function getTotalTokensAttribute(): int
    {
        return $this->input_tokens + $this->output_tokens + $this->cache_creation_tokens + $this->cache_read_tokens;
    }
}
