<?php

namespace App\Livewire\Listings;

use App\Enums\AiEnrichmentStatus;
use App\Enums\DedupStatus;
use App\Models\Listing;
use App\Models\Platform;
use App\Services\AI\ListingEnrichmentService;
use App\Services\Dedup\DeduplicationService;
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

    public bool $isProcessing = false;

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

    public function runBulkEnrichment(ListingEnrichmentService $enrichmentService): void
    {
        if (empty($this->selected)) {
            Flux::toast(text: 'No listings selected', variant: 'warning');

            return;
        }

        $this->isProcessing = true;
        $processed = 0;
        $failed = 0;

        $listings = Listing::whereIn('id', $this->selected)->get();

        foreach ($listings as $listing) {
            if (! $listing->raw_data) {
                $failed++;

                continue;
            }

            try {
                $enrichment = $enrichmentService->enrichListing($listing);
                if ($enrichment->status->isCompleted()) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->isProcessing = false;
        $this->selected = [];
        $this->selectAll = false;

        Flux::toast(
            heading: 'Enrichment Complete',
            text: "{$processed} processed, {$failed} failed",
            variant: $failed > 0 ? 'warning' : 'success',
        );
    }

    public function runBulkDeduplication(DeduplicationService $dedupService): void
    {
        if (empty($this->selected)) {
            Flux::toast(text: 'No listings selected', variant: 'warning');

            return;
        }

        $this->isProcessing = true;
        $processed = 0;
        $failed = 0;

        $listings = Listing::whereIn('id', $this->selected)
            ->where('ai_status', AiEnrichmentStatus::Completed)
            ->get();

        if ($listings->isEmpty()) {
            $this->isProcessing = false;
            Flux::toast(text: 'No enriched listings in selection', variant: 'warning');

            return;
        }

        foreach ($listings as $listing) {
            try {
                $dedupService->processListing($listing);
                $processed++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->isProcessing = false;
        $this->selected = [];
        $this->selectAll = false;

        Flux::toast(
            heading: 'Deduplication Complete',
            text: "{$processed} processed, {$failed} failed",
            variant: $failed > 0 ? 'warning' : 'success',
        );
    }

    public function runBatchEnrichment(ListingEnrichmentService $enrichmentService): void
    {
        $this->isProcessing = true;

        $listings = Listing::pendingAiEnrichment()
            ->whereNotNull('raw_data')
            ->limit(10)
            ->get();

        if ($listings->isEmpty()) {
            $this->isProcessing = false;
            Flux::toast(text: 'No listings pending enrichment', variant: 'warning');

            return;
        }

        $processed = 0;
        $failed = 0;

        foreach ($listings as $listing) {
            try {
                $enrichment = $enrichmentService->enrichListing($listing);
                if ($enrichment->status->isCompleted()) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->isProcessing = false;

        Flux::toast(
            heading: 'Batch Enrichment Complete',
            text: "{$processed} processed, {$failed} failed",
            variant: $failed > 0 ? 'warning' : 'success',
        );
    }

    public function runBatchDeduplication(DeduplicationService $dedupService): void
    {
        $this->isProcessing = true;

        $listings = Listing::pendingDedup()
            ->whereNotNull('raw_data')
            ->limit(10)
            ->get();

        if ($listings->isEmpty()) {
            $this->isProcessing = false;
            Flux::toast(text: 'No listings pending deduplication', variant: 'warning');

            return;
        }

        $processed = 0;
        $failed = 0;

        foreach ($listings as $listing) {
            try {
                $dedupService->processListing($listing);
                $processed++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $this->isProcessing = false;

        Flux::toast(
            heading: 'Batch Deduplication Complete',
            text: "{$processed} processed, {$failed} failed",
            variant: $failed > 0 ? 'warning' : 'success',
        );
    }

    /**
     * @return array{ai_pending: int, ai_completed: int, dedup_pending: int, dedup_matched: int, dedup_needs_review: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'ai_pending' => Listing::where('ai_status', AiEnrichmentStatus::Pending)->count(),
            'ai_completed' => Listing::where('ai_status', AiEnrichmentStatus::Completed)->count(),
            'dedup_pending' => Listing::where('dedup_status', DedupStatus::Pending)->count(),
            'dedup_matched' => Listing::where('dedup_status', DedupStatus::Matched)->count(),
            'dedup_needs_review' => Listing::where('dedup_status', DedupStatus::NeedsReview)->count(),
        ];
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
