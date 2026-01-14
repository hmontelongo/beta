<?php

namespace App\Livewire\Listings;

use App\Enums\AiEnrichmentStatus;
use App\Enums\DedupCandidateStatus;
use App\Enums\DedupStatus;
use App\Jobs\DeduplicateListingJob;
use App\Jobs\EnrichListingJob;
use App\Models\DedupCandidate;
use App\Models\Listing;
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
    public string $aiStatus = '';

    #[Url]
    public string $dedupStatus = '';

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

    public function updatedAiStatus(): void
    {
        $this->resetPage();
    }

    public function updatedDedupStatus(): void
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

    public function runBulkEnrichment(): void
    {
        if (empty($this->selected)) {
            Flux::toast(text: 'No listings selected', variant: 'warning');

            return;
        }

        $listings = Listing::whereIn('id', $this->selected)
            ->whereNotNull('raw_data')
            ->where('ai_status', '!=', AiEnrichmentStatus::Processing)
            ->get();

        $count = $this->dispatchEnrichmentJobs($listings, 'No eligible listings in selection');

        if ($count > 0) {
            $this->selected = [];
            $this->selectAll = false;
            Flux::toast(heading: 'Enrichment Queued', text: "{$count} listings queued for processing", variant: 'info');
        }
    }

    public function runBulkDeduplication(): void
    {
        if (empty($this->selected)) {
            Flux::toast(text: 'No listings selected', variant: 'warning');

            return;
        }

        $listings = Listing::whereIn('id', $this->selected)
            ->whereNotNull('raw_data')
            ->where('ai_status', AiEnrichmentStatus::Completed)
            ->where('dedup_status', '!=', DedupStatus::Processing)
            ->get();

        $count = $this->dispatchDeduplicationJobs($listings, 'No enriched listings eligible for dedup');

        if ($count > 0) {
            $this->selected = [];
            $this->selectAll = false;
            Flux::toast(heading: 'Deduplication Queued', text: "{$count} listings queued for processing", variant: 'info');
        }
    }

    public function runBatchEnrichment(): void
    {
        $listings = Listing::pendingAiEnrichment()->whereNotNull('raw_data')->get();
        $count = $this->dispatchEnrichmentJobs($listings, 'No listings pending enrichment');

        if ($count > 0) {
            Flux::toast(heading: 'Batch Enrichment Queued', text: "{$count} listings queued for processing", variant: 'info');
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
     * Dispatch enrichment jobs for the given listings.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Listing>  $listings
     */
    protected function dispatchEnrichmentJobs($listings, string $emptyMessage): int
    {
        if ($listings->isEmpty()) {
            Flux::toast(text: $emptyMessage, variant: 'warning');

            return 0;
        }

        foreach ($listings as $listing) {
            // Job owns state transition - dispatches with Pending status
            EnrichListingJob::dispatch($listing->id);
        }

        return $listings->count();
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

    public function cancelEnrichment(): void
    {
        $result = app(JobCancellationService::class)->cancelEnrichmentJobs();

        Flux::toast(
            heading: 'Enrichment Cancelled',
            text: "{$result['reset']} listings reset to pending, queue cleared",
            variant: 'warning',
        );
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

    /**
     * Retry all failed enrichment jobs.
     */
    public function retryFailedEnrichment(): void
    {
        $listings = Listing::where('ai_status', AiEnrichmentStatus::Failed)
            ->whereNotNull('raw_data')
            ->get();

        if ($listings->isEmpty()) {
            Flux::toast(text: 'No failed enrichment jobs to retry', variant: 'warning');

            return;
        }

        // Reset to pending and dispatch
        Listing::whereIn('id', $listings->pluck('id'))
            ->update(['ai_status' => AiEnrichmentStatus::Pending]);

        foreach ($listings as $listing) {
            EnrichListingJob::dispatch($listing->id);
        }

        Flux::toast(
            heading: 'Retry Queued',
            text: "{$listings->count()} failed listings queued for retry",
            variant: 'info',
        );
    }

    /**
     * Retry all failed deduplication jobs.
     */
    public function retryFailedDedup(): void
    {
        $listings = Listing::where('dedup_status', DedupStatus::Failed)
            ->whereNotNull('raw_data')
            ->where('ai_status', AiEnrichmentStatus::Completed)
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
        $staleThreshold = now()->subMinutes(5);

        $aiCount = Listing::where('ai_status', AiEnrichmentStatus::Processing)
            ->where('updated_at', '<', $staleThreshold)
            ->update(['ai_status' => AiEnrichmentStatus::Pending]);

        $dedupCount = Listing::where('dedup_status', DedupStatus::Processing)
            ->where('updated_at', '<', $staleThreshold)
            ->update(['dedup_status' => DedupStatus::Pending]);

        if ($aiCount === 0 && $dedupCount === 0) {
            Flux::toast(text: 'No stuck jobs to reset', variant: 'info');

            return;
        }

        Flux::toast(
            heading: 'Stuck Jobs Reset',
            text: "{$aiCount} enrichment, {$dedupCount} dedup jobs reset to pending",
            variant: 'info',
        );
    }

    /**
     * @return array{ai_pending: int, ai_processing: int, ai_completed: int, ai_failed: int, dedup_pending: int, dedup_processing: int, dedup_matched: int, dedup_needs_review: int, dedup_failed: int, candidates_pending_review: int, ai_queued: int, dedup_queued: int}
     */
    #[Computed]
    public function stats(): array
    {
        // Get queue stats for real-time job counts
        $queueStats = app(JobCancellationService::class)->getQueueStats();

        return [
            'ai_pending' => Listing::where('ai_status', AiEnrichmentStatus::Pending)->count(),
            'ai_processing' => Listing::where('ai_status', AiEnrichmentStatus::Processing)->count(),
            'ai_completed' => Listing::where('ai_status', AiEnrichmentStatus::Completed)->count(),
            'ai_failed' => Listing::where('ai_status', AiEnrichmentStatus::Failed)->count(),
            'dedup_pending' => Listing::pendingDedup()->count(),
            'dedup_processing' => Listing::where('dedup_status', DedupStatus::Processing)->count(),
            'dedup_matched' => Listing::where('dedup_status', DedupStatus::Matched)->count(),
            'dedup_needs_review' => Listing::where('dedup_status', DedupStatus::NeedsReview)->count(),
            'dedup_failed' => Listing::where('dedup_status', DedupStatus::Failed)->count(),
            'candidates_pending_review' => DedupCandidate::where('status', DedupCandidateStatus::NeedsReview)->count(),
            // Queue depth: pending + reserved (currently executing) + delayed
            'ai_queued' => ($queueStats['ai-enrichment']['pending'] ?? 0)
                + ($queueStats['ai-enrichment']['reserved'] ?? 0)
                + ($queueStats['ai-enrichment']['delayed'] ?? 0),
            'dedup_queued' => ($queueStats['dedup']['pending'] ?? 0)
                + ($queueStats['dedup']['reserved'] ?? 0)
                + ($queueStats['dedup']['delayed'] ?? 0),
        ];
    }

    #[Computed]
    public function isProcessing(): bool
    {
        // Check both database state AND queue depth for accurate processing detection
        return $this->stats['ai_processing'] > 0
            || $this->stats['dedup_processing'] > 0
            || $this->stats['ai_queued'] > 0
            || $this->stats['dedup_queued'] > 0;
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
    public function isEnrichmentProcessing(): bool
    {
        // Check both database state AND queue depth
        return $this->stats['ai_processing'] > 0 || $this->stats['ai_queued'] > 0;
    }

    #[Computed]
    public function isDeduplicationProcessing(): bool
    {
        // Check both database state AND queue depth
        return $this->stats['dedup_processing'] > 0 || $this->stats['dedup_queued'] > 0;
    }

    /**
     * Reset listings stuck in processing status for more than 5 minutes.
     */
    protected function resetStaleProcessingJobs(): void
    {
        $staleThreshold = now()->subMinutes(5);

        Listing::where('ai_status', AiEnrichmentStatus::Processing)
            ->where('updated_at', '<', $staleThreshold)
            ->update(['ai_status' => AiEnrichmentStatus::Pending]);

        Listing::where('dedup_status', DedupStatus::Processing)
            ->where('updated_at', '<', $staleThreshold)
            ->update(['dedup_status' => DedupStatus::Pending]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Listing>
     */
    protected function buildQuery()
    {
        return Listing::query()
            ->with(['platform', 'discoveredListing'])
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
            ->when($this->aiStatus, function ($query) {
                $query->where('ai_status', $this->aiStatus);
            })
            ->when($this->dedupStatus, function ($query) {
                $query->where('dedup_status', $this->dedupStatus);
            })
            ->latest('scraped_at');
    }

    public function render(): View
    {
        $listings = $this->buildQuery()->paginate(20);

        return view('livewire.listings.index', [
            'listings' => $listings,
            'platforms' => Platform::orderBy('name')->get(),
            'aiStatuses' => AiEnrichmentStatus::cases(),
            'dedupStatuses' => DedupStatus::cases(),
        ]);
    }
}
