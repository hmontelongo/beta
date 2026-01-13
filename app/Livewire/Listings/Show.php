<?php

namespace App\Livewire\Listings;

use App\Enums\AiEnrichmentStatus;
use App\Enums\DedupCandidateStatus;
use App\Enums\DedupStatus;
use App\Jobs\DeduplicateListingJob;
use App\Jobs\EnrichListingJob;
use App\Jobs\RescrapeListingJob;
use App\Models\DedupCandidate;
use App\Models\Listing;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Listing $listing;

    public bool $isRescraping = false;

    public function mount(Listing $listing): void
    {
        $this->listing = $listing->load(['platform', 'discoveredListing', 'aiEnrichment', 'property']);
    }

    public function rescrape(): void
    {
        $this->isRescraping = true;

        RescrapeListingJob::dispatch($this->listing->id);

        Flux::toast(
            heading: 'Re-scrape Queued',
            text: 'Fetching fresh data from the source...',
            variant: 'info',
        );
    }

    public function runEnrichment(): void
    {
        $this->listing->update(['ai_status' => AiEnrichmentStatus::Processing]);

        EnrichListingJob::dispatch($this->listing->id);

        Flux::toast(
            heading: 'Enrichment Queued',
            text: 'Processing in background...',
            variant: 'info',
        );
    }

    public function runDeduplication(): void
    {
        if ($this->listing->property_id) {
            Flux::toast(heading: 'Already Processed', text: 'This listing is already linked to a property.', variant: 'warning');

            return;
        }

        if ($this->listing->ai_status !== AiEnrichmentStatus::Completed) {
            Flux::toast(heading: 'Enrichment Required', text: 'Run AI enrichment first before deduplication.', variant: 'warning');

            return;
        }

        if (! $this->canDedup) {
            return;
        }

        $this->listing->update(['dedup_status' => DedupStatus::Processing]);
        DeduplicateListingJob::dispatch($this->listing->id);

        Flux::toast(heading: 'Deduplication Queued', text: 'Processing in background...', variant: 'info');
    }

    #[Computed]
    public function canEnrich(): bool
    {
        return $this->listing->raw_data !== null
            && ! $this->listing->ai_status->isActive();
    }

    #[Computed]
    public function canDedup(): bool
    {
        return $this->listing->raw_data !== null
            && $this->listing->ai_status === AiEnrichmentStatus::Completed
            && ! $this->listing->dedup_status->isActive()
            && ! $this->listing->property_id;
    }

    #[Computed]
    public function isProcessing(): bool
    {
        return $this->listing->ai_status->isActive()
            || $this->listing->dedup_status->isActive();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function images(): array
    {
        return collect($this->listing->raw_data['images'] ?? [])
            ->map(fn (array|string $img): string => is_array($img) ? $img['url'] : $img)
            ->filter(fn (string $url): bool => ! str_contains($url, '.svg')
                && ! str_contains($url, 'placeholder')
                && ! str_contains($url, 'icon')
                && preg_match('/\.(jpg|jpeg|png|webp)/i', $url)
            )
            ->values()
            ->toArray();
    }

    #[Computed]
    public function formattedWhatsapp(): ?string
    {
        $whatsapp = $this->listing->raw_data['whatsapp'] ?? null;

        if (! $whatsapp) {
            return null;
        }

        return preg_replace('/[^0-9]/', '', $whatsapp);
    }

    /**
     * Get dedup candidates involving this listing that need review.
     *
     * @return Collection<int, DedupCandidate>
     */
    #[Computed]
    public function dedupCandidates(): Collection
    {
        return DedupCandidate::where('status', DedupCandidateStatus::NeedsReview)
            ->where(function ($query) {
                $query->where('listing_a_id', $this->listing->id)
                    ->orWhere('listing_b_id', $this->listing->id);
            })
            ->with(['listingA.platform', 'listingB.platform'])
            ->get();
    }

    public function render(): View
    {
        // Refresh listing data to pick up job completion
        $this->listing->refresh();
        $this->listing->load(['aiEnrichment', 'property']);

        return view('livewire.listings.show');
    }
}
