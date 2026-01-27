<?php

namespace App\Livewire\Admin\Listings;

use App\Enums\DedupStatus;
use App\Enums\ListingGroupStatus;
use App\Enums\ListingPipelineStatus;
use App\Jobs\DeduplicateListingJob;
use App\Models\Listing;
use App\Models\ListingGroup;
use App\Models\Platform;
use App\Services\JobCancellationService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Listings')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $platform = '';

    #[Url]
    public string $dedupStatus = '';

    #[Url]
    public string $pipelineStatus = '';

    /** @var array<int> */
    public array $selected = [];

    public bool $selectAll = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPlatform(): void
    {
        $this->resetPage();
    }

    public function updatedDedupStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPipelineStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selected = $this->currentPageIds();
        } else {
            $this->selected = [];
        }
    }

    /**
     * @return array<int>
     */
    protected function currentPageIds(): array
    {
        return $this->buildQuery()->pluck('id')->toArray();
    }

    public function runBulkDeduplication(): void
    {
        if (empty($this->selected)) {
            Flux::toast(text: 'No listings selected', variant: 'warning');

            return;
        }

        $listings = Listing::whereIn('id', $this->selected)
            ->whereNotNull('raw_data')
            ->where('dedup_status', '!=', DedupStatus::Processing)
            ->whereNull('property_id')
            ->get();

        $count = $this->dispatchDeduplicationJobs($listings, 'No eligible listings in selection');

        if ($count > 0) {
            $this->selected = [];
            $this->selectAll = false;
            Flux::toast(heading: 'Deduplication Queued', text: "{$count} listings queued for processing", variant: 'info');
        }
    }

    public function runBatchDeduplication(): void
    {
        $listings = Listing::pendingDedup()->whereNotNull('raw_data')->get();
        $count = $this->dispatchDeduplicationJobs($listings, 'No listings pending deduplication');

        if ($count > 0) {
            Flux::toast(heading: 'Batch Deduplication Queued', text: "{$count} listings queued for processing", variant: 'info');
        }
    }

    /**
     * Dispatch deduplication jobs for the given listings.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Listing>  $listings
     */
    protected function dispatchDeduplicationJobs($listings, string $emptyMessage): int
    {
        if ($listings->isEmpty()) {
            Flux::toast(text: $emptyMessage, variant: 'warning');

            return 0;
        }

        foreach ($listings as $listing) {
            // Job owns state transition - dispatches with Pending status
            DeduplicateListingJob::dispatch($listing->id);
        }

        return $listings->count();
    }

    public function cancelDeduplication(): void
    {
        $result = app(JobCancellationService::class)->cancelDeduplicationJobs();

        Flux::toast(
            heading: 'Deduplication Cancelled',
            text: "{$result['reset']} listings reset to pending, queue cleared",
            variant: 'warning',
        );
    }

    public function cancelPropertyCreation(): void
    {
        $result = app(JobCancellationService::class)->cancelPropertyCreationJobs();

        $parts = [];
        if ($result['reset'] > 0) {
            $parts[] = "{$result['reset']} groups rejected";
        }
        if ($result['unique_listings'] > 0) {
            $parts[] = "{$result['unique_listings']} unique listings failed";
        }
        $text = implode(', ', $parts) ?: 'Queue cleared';

        Flux::toast(
            heading: 'Property Creation Cancelled',
            text: $text,
            variant: 'warning',
        );

        // Force stats refresh
        unset($this->stats, $this->pipelineStats);
    }

    /**
     * Retry all failed deduplication jobs.
     */
    public function retryFailedDedup(): void
    {
        $listings = Listing::where('dedup_status', DedupStatus::Failed)
            ->whereNotNull('raw_data')
            ->get();

        if ($listings->isEmpty()) {
            Flux::toast(text: 'No failed deduplication jobs to retry', variant: 'warning');

            return;
        }

        // Reset to pending and dispatch
        Listing::whereIn('id', $listings->pluck('id'))
            ->update(['dedup_status' => DedupStatus::Pending]);

        foreach ($listings as $listing) {
            DeduplicateListingJob::dispatch($listing->id);
        }

        Flux::toast(
            heading: 'Retry Queued',
            text: "{$listings->count()} failed listings queued for retry",
            variant: 'info',
        );
    }

    /**
     * Manually reset stuck processing jobs.
     */
    public function resetStuckJobs(): void
    {
        // Get counts before reset for feedback
        $staleThreshold = now()->subMinutes(5);

        $dedupCount = Listing::where('dedup_status', DedupStatus::Processing)
            ->where('updated_at', '<', $staleThreshold)
            ->count();

        $aiCount = ListingGroup::where('status', ListingGroupStatus::ProcessingAi)
            ->where('updated_at', '<', $staleThreshold)
            ->count();

        if ($dedupCount === 0 && $aiCount === 0) {
            Flux::toast(text: 'No stuck jobs to reset', variant: 'info');

            return;
        }

        // Perform the actual reset
        $this->resetStaleProcessingJobs();

        Flux::toast(
            heading: 'Stuck Jobs Reset',
            text: "{$dedupCount} dedup, {$aiCount} property creation jobs reset to pending",
            variant: 'info',
        );
    }

    /**
     * @return array{dedup_pending: int, dedup_processing: int, dedup_grouped: int, dedup_completed: int, dedup_failed: int, groups_pending_review: int, groups_pending_ai: int, groups_processing_ai: int, dedup_queued: int, property_creation_queued: int}
     */
    #[Computed]
    public function stats(): array
    {
        // Get queue stats for real-time job counts
        $queueStats = app(JobCancellationService::class)->getQueueStats();

        return [
            'dedup_pending' => Listing::pendingDedup()->count(),
            'dedup_processing' => Listing::where('dedup_status', DedupStatus::Processing)->count(),
            'dedup_grouped' => Listing::where('dedup_status', DedupStatus::Grouped)->count(),
            'dedup_completed' => Listing::where('dedup_status', DedupStatus::Completed)->count(),
            'dedup_failed' => Listing::where('dedup_status', DedupStatus::Failed)->count(),
            'groups_pending_review' => ListingGroup::where('status', ListingGroupStatus::PendingReview)->count(),
            'groups_pending_ai' => ListingGroup::where('status', ListingGroupStatus::PendingAi)->count(),
            'groups_processing_ai' => ListingGroup::where('status', ListingGroupStatus::ProcessingAi)->count(),
            // Queue depth: pending + reserved (currently executing) + delayed
            'dedup_queued' => ($queueStats['dedup']['pending'] ?? 0)
                + ($queueStats['dedup']['reserved'] ?? 0)
                + ($queueStats['dedup']['delayed'] ?? 0),
            'property_creation_queued' => ($queueStats['property-creation']['pending'] ?? 0)
                + ($queueStats['property-creation']['reserved'] ?? 0)
                + ($queueStats['property-creation']['delayed'] ?? 0),
        ];
    }

    /**
     * Get pipeline status counts for the stats dashboard.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function pipelineStats(): array
    {
        $baseQuery = fn () => Listing::query()
            ->when($this->platform, fn ($q) => $q->where('platform_id', $this->platform));

        return [
            'awaiting_geocoding' => $baseQuery()
                ->where(fn ($q) => $q->whereNull('geocode_status')->orWhere('geocode_status', '!=', 'success'))
                ->where('dedup_status', '!=', DedupStatus::Failed)
                ->where('dedup_status', '!=', DedupStatus::Completed)
                ->count(),
            'awaiting_dedup' => $baseQuery()
                ->where('geocode_status', 'success')
                ->where('dedup_status', DedupStatus::Pending)
                ->count(),
            'processing_dedup' => $baseQuery()
                ->where('dedup_status', DedupStatus::Processing)
                ->count(),
            'needs_review' => $baseQuery()
                ->where('dedup_status', DedupStatus::Grouped)
                ->whereHas('listingGroup', fn ($q) => $q->where('status', ListingGroupStatus::PendingReview))
                ->count(),
            'queued_for_ai' => $baseQuery()
                ->where('dedup_status', DedupStatus::Grouped)
                ->whereHas('listingGroup', fn ($q) => $q->where('status', ListingGroupStatus::PendingAi))
                ->count(),
            'processing_ai' => $baseQuery()
                ->where('dedup_status', DedupStatus::Grouped)
                ->whereHas('listingGroup', fn ($q) => $q->where('status', ListingGroupStatus::ProcessingAi))
                ->count(),
            'completed' => $baseQuery()
                ->where('dedup_status', DedupStatus::Completed)
                ->count(),
            'failed' => $baseQuery()
                ->where('dedup_status', DedupStatus::Failed)
                ->count(),
        ];
    }

    #[Computed]
    public function isProcessing(): bool
    {
        // Check both database state AND queue depth for accurate processing detection
        return $this->stats['dedup_processing'] > 0
            || $this->stats['groups_processing_ai'] > 0
            || $this->stats['dedup_queued'] > 0
            || $this->stats['property_creation_queued'] > 0;
    }

    /**
     * Lightweight check for changes during slow polling.
     * Triggers re-render if processing count changed.
     */
    public function checkForChanges(): void
    {
        // Auto-reset stale processing jobs (stuck for more than 5 minutes)
        $this->resetStaleProcessingJobs();

        // Force computed property refresh
        unset($this->stats);
    }

    #[Computed]
    public function isDeduplicationProcessing(): bool
    {
        // Check both database state AND queue depth
        return $this->stats['dedup_processing'] > 0 || $this->stats['dedup_queued'] > 0;
    }

    #[Computed]
    public function isPropertyCreationProcessing(): bool
    {
        // Check both database state AND queue depth
        return $this->stats['groups_processing_ai'] > 0 || $this->stats['property_creation_queued'] > 0;
    }

    /**
     * Reset listings stuck in processing status for more than 5 minutes.
     */
    protected function resetStaleProcessingJobs(): void
    {
        $staleThreshold = now()->subMinutes(5);

        Listing::where('dedup_status', DedupStatus::Processing)
            ->where('updated_at', '<', $staleThreshold)
            ->update(['dedup_status' => DedupStatus::Pending]);

        ListingGroup::where('status', ListingGroupStatus::ProcessingAi)
            ->where('updated_at', '<', $staleThreshold)
            ->update(['status' => ListingGroupStatus::PendingAi]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Listing>
     */
    protected function buildQuery()
    {
        return Listing::query()
            ->with(['platform', 'discoveredListing', 'listingGroup'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('original_url', 'like', "%{$this->search}%")
                        ->orWhere('external_id', 'like', "%{$this->search}%")
                        ->orWhereRaw("JSON_EXTRACT(raw_data, '$.title') LIKE ?", ["%{$this->search}%"]);
                });
            })
            ->when($this->platform, function ($query) {
                $query->where('platform_id', $this->platform);
            })
            ->when($this->dedupStatus, function ($query) {
                $query->where('dedup_status', $this->dedupStatus);
            })
            ->when($this->pipelineStatus, function ($query) {
                $this->applyPipelineStatusFilter($query, $this->pipelineStatus);
            })
            ->latest('scraped_at');
    }

    /**
     * Apply pipeline status filter to query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Listing>  $query
     */
    protected function applyPipelineStatusFilter($query, string $status): void
    {
        match ($status) {
            'awaiting_geocoding' => $query
                ->where(fn ($q) => $q->whereNull('geocode_status')->orWhere('geocode_status', '!=', 'success'))
                ->where('dedup_status', '!=', DedupStatus::Failed)
                ->where('dedup_status', '!=', DedupStatus::Completed),
            'awaiting_dedup' => $query
                ->where('geocode_status', 'success')
                ->where('dedup_status', DedupStatus::Pending),
            'processing_dedup' => $query
                ->where('dedup_status', DedupStatus::Processing),
            'needs_review' => $query
                ->where('dedup_status', DedupStatus::Grouped)
                ->whereHas('listingGroup', fn ($q) => $q->where('status', ListingGroupStatus::PendingReview)),
            'queued_for_ai' => $query
                ->where('dedup_status', DedupStatus::Grouped)
                ->whereHas('listingGroup', fn ($q) => $q->where('status', ListingGroupStatus::PendingAi)),
            'processing_ai' => $query
                ->where('dedup_status', DedupStatus::Grouped)
                ->whereHas('listingGroup', fn ($q) => $q->where('status', ListingGroupStatus::ProcessingAi)),
            'completed' => $query
                ->where('dedup_status', DedupStatus::Completed),
            'failed' => $query
                ->where('dedup_status', DedupStatus::Failed),
            default => null,
        };
    }

    public function render(): View
    {
        $listings = $this->buildQuery()->paginate(20);

        return view('livewire.admin.listings.index', [
            'listings' => $listings,
            'platforms' => Platform::orderBy('name')->get(),
            'dedupStatuses' => DedupStatus::cases(),
            'pipelineStatuses' => ListingPipelineStatus::cases(),
        ]);
    }
}
