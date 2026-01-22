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
     * @return array{total_cost_cents: int, claude_cost_cents: int, zenrows_credits: int, claude_requests: int, zenrows_requests: int, total_tokens: int}
     */
    #[Computed]
    public function costStats(): array
    {
        $days = $this->getPeriodDays();
        $since = now()->subDays($days);

        $logs = ApiUsageLog::where('created_at', '>=', $since)->get();
        $claudeLogs = $logs->where('service', ApiService::Claude);
        $zenrowsLogs = $logs->where('service', ApiService::ZenRows);

        return [
            'total_cost_cents' => (int) $logs->sum('cost_cents'),
            'claude_cost_cents' => (int) $claudeLogs->sum('cost_cents'),
            'zenrows_credits' => (int) $zenrowsLogs->sum('credits_used'),
            'claude_requests' => $claudeLogs->count(),
            'zenrows_requests' => $zenrowsLogs->count(),
            'total_tokens' => (int) $claudeLogs->sum('total_tokens'),
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
