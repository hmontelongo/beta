<?php

namespace App\Livewire\Listings;

use App\Enums\AiEnrichmentStatus;
use App\Models\Listing;
use App\Services\AI\ListingEnrichmentService;
use App\Services\Dedup\DeduplicationService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Listing $listing;

    public bool $isProcessing = false;

    public function mount(Listing $listing): void
    {
        $this->listing = $listing->load(['platform', 'discoveredListing', 'aiEnrichment', 'property']);
    }

    public function runEnrichment(ListingEnrichmentService $enrichmentService): void
    {
        $this->isProcessing = true;

        try {
            $enrichment = $enrichmentService->enrichListing($this->listing);
            $this->listing->refresh();

            if ($enrichment->status->isCompleted()) {
                Flux::toast(
                    heading: 'Enrichment Complete',
                    text: "Quality score: {$enrichment->quality_score}",
                    variant: 'success',
                );
            } else {
                Flux::toast(
                    heading: 'Enrichment Failed',
                    text: $enrichment->error_message ?? 'Unknown error',
                    variant: 'danger',
                );
            }
        } catch (\Throwable $e) {
            Flux::toast(
                heading: 'Error',
                text: $e->getMessage(),
                variant: 'danger',
            );
        } finally {
            $this->isProcessing = false;
        }
    }

    public function runDeduplication(DeduplicationService $dedupService): void
    {
        $this->isProcessing = true;

        try {
            $dedupService->processListing($this->listing);
            $this->listing->refresh();

            $status = $this->listing->dedup_status->value;
            $message = match ($status) {
                'matched' => "Linked to property #{$this->listing->property_id}",
                'new' => 'Created new property',
                'needs_review' => 'Found candidates needing review',
                default => "Status: {$status}",
            };

            Flux::toast(
                heading: 'Deduplication Complete',
                text: $message,
                variant: 'success',
            );
        } catch (\Throwable $e) {
            Flux::toast(
                heading: 'Error',
                text: $e->getMessage(),
                variant: 'danger',
            );
        } finally {
            $this->isProcessing = false;
        }
    }

    #[Computed]
    public function canEnrich(): bool
    {
        return $this->listing->raw_data !== null
            && $this->listing->ai_status !== AiEnrichmentStatus::Processing;
    }

    #[Computed]
    public function canDedup(): bool
    {
        return $this->listing->raw_data !== null
            && $this->listing->ai_status === AiEnrichmentStatus::Completed;
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function images(): array
    {
        return collect($this->listing->raw_data['images'] ?? [])
            ->map(fn (array|string $img): string => is_array($img) ? $img['url'] : $img)
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

    public function render(): View
    {
        return view('livewire.listings.show');
    }
}
