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
            $listing->update(['ai_status' => AiEnrichmentStatus::Processing]);
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
            $listing->update(['dedup_status' => DedupStatus::Processing]);
            DeduplicateListingJob::dispatch($listing->id);
        }

        return $listings->count();
    }

    public function cancelEnrichment(): void
    {
        $count = Listing::where('ai_status', AiEnrichmentStatus::Processing)
            ->update(['ai_status' => AiEnrichmentStatus::Pending]);

        Flux::toast(
            heading: 'Enrichment Cancelled',
            text: "{$count} listings reset to pending",
            variant: 'warning',
        );
    }

    public function cancelDeduplication(): void
    {
        $count = Listing::where('dedup_status', DedupStatus::Processing)
            ->update(['dedup_status' => DedupStatus::Pending]);

        Flux::toast(
            heading: 'Deduplication Cancelled',
            text: "{$count} listings reset to pending",
            variant: 'warning',
        );
    }

    /**
     * @return array{ai_pending: int, ai_processing: int, ai_completed: int, dedup_pending: int, dedup_processing: int, dedup_matched: int, dedup_needs_review: int, candidates_pending_review: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'ai_pending' => Listing::where('ai_status', AiEnrichmentStatus::Pending)->count(),
            'ai_processing' => Listing::where('ai_status', AiEnrichmentStatus::Processing)->count(),
            'ai_completed' => Listing::where('ai_status', AiEnrichmentStatus::Completed)->count(),
            'dedup_pending' => Listing::pendingDedup()->count(),
            'dedup_processing' => Listing::where('dedup_status', DedupStatus::Processing)->count(),
            'dedup_matched' => Listing::where('dedup_status', DedupStatus::Matched)->count(),
            'dedup_needs_review' => Listing::where('dedup_status', DedupStatus::NeedsReview)->count(),
            'candidates_pending_review' => DedupCandidate::where('status', DedupCandidateStatus::NeedsReview)->count(),
        ];
    }

    #[Computed]
    public function isProcessing(): bool
    {
        // Auto-reset stale processing jobs (stuck for more than 5 minutes)
        $this->resetStaleProcessingJobs();

        return $this->stats['ai_processing'] > 0 || $this->stats['dedup_processing'] > 0;
    }

    #[Computed]
    public function isEnrichmentProcessing(): bool
    {
        return $this->stats['ai_processing'] > 0;
    }

    #[Computed]
    public function isDeduplicationProcessing(): bool
    {
        return $this->stats['dedup_processing'] > 0;
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
