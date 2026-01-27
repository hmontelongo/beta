<?php

namespace App\Livewire\Admin;

use App\Enums\ApiService;
use App\Models\ApiUsageLog;
use App\Models\Listing;
use App\Models\Property;
use App\Models\Publisher;
use App\Models\ScrapeRun;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Url]
    public string $period = '30d';

    /**
     * Get the period in days.
     */
    protected function getPeriodDays(): int
    {
        return match ($this->period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };
    }

    /**
     * Estimated cost per ZenRows request in cents.
     * Based on observed data: ~$0.00323 per request (0.323 cents).
     */
    private const ZENROWS_COST_PER_REQUEST_CENTS = 0.323;

    /**
     * @return array{total_cost_cents: int, claude_cost_cents: int, zenrows_credits: int, claude_requests: int, claude_failed: int, zenrows_requests: int, zenrows_failed: int, total_tokens: int}
     */
    #[Computed]
    public function costStats(): array
    {
        $days = $this->getPeriodDays();
        $since = now()->subDays($days);

        // Use DB aggregation instead of loading all records into memory
        // Note: Use 'tokens_sum' alias to avoid conflict with model's getTotalTokensAttribute accessor
        $claudeStats = ApiUsageLog::where('created_at', '>=', $since)
            ->where('service', ApiService::Claude)
            ->selectRaw('COALESCE(SUM(cost_cents), 0) as cost_cents')
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed')
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens + cache_creation_tokens + cache_read_tokens), 0) as tokens_sum')
            ->first();

        $zenrowsStats = ApiUsageLog::where('created_at', '>=', $since)
            ->where('service', ApiService::ZenRows)
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed')
            ->selectRaw('COALESCE(SUM(credits_used), 0) as credits')
            ->first();

        $claudeCost = (int) $claudeStats->cost_cents;
        $zenrowsRequests = (int) $zenrowsStats->requests;
        $zenrowsCostCents = (int) round($zenrowsRequests * self::ZENROWS_COST_PER_REQUEST_CENTS);

        return [
            'total_cost_cents' => $claudeCost,
            'claude_cost_cents' => $claudeCost,
            'zenrows_credits' => (int) $zenrowsStats->credits,
            'zenrows_cost_cents' => $zenrowsCostCents,
            'claude_requests' => (int) $claudeStats->requests,
            'claude_failed' => (int) $claudeStats->failed,
            'zenrows_requests' => $zenrowsRequests,
            'zenrows_failed' => (int) $zenrowsStats->failed,
            'total_tokens' => (int) $claudeStats->tokens_sum,
        ];
    }

    /**
     * @return array{properties_total: int, properties_new: int, listings_total: int, publishers_total: int, pending_jobs: int, failed_jobs: int}
     */
    #[Computed]
    public function pipelineStats(): array
    {
        $days = $this->getPeriodDays();
        $since = now()->subDays($days);

        return [
            'properties_total' => Property::count(),
            'properties_new' => Property::where('created_at', '>=', $since)->count(),
            'listings_total' => Listing::count(),
            'publishers_total' => Publisher::count(),
            'pending_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
        ];
    }

    /**
     * @return Collection<int, ScrapeRun>
     */
    #[Computed]
    public function recentRuns(): Collection
    {
        return ScrapeRun::with('searchQuery', 'platform')
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * @return Collection<int, Property>
     */
    #[Computed]
    public function recentProperties(): Collection
    {
        return Property::with('listings.platform')
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * Format cost in USD from cents.
     */
    public function formatCost(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }

    public function render(): View
    {
        return view('livewire.admin.dashboard');
    }
}
