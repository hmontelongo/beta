<?php

namespace App\Livewire\Dedup;

use App\Enums\DedupCandidateStatus;
use App\Models\DedupCandidate;
use App\Services\Dedup\DeduplicationService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Review Duplicates')]
class ReviewCandidates extends Component
{
    public ?int $currentCandidateId = null;

    public function mount(): void
    {
        // Load first candidate if available
        $first = DedupCandidate::where('status', DedupCandidateStatus::NeedsReview)
            ->orderBy('overall_score', 'desc')
            ->first();

        $this->currentCandidateId = $first?->id;
    }

    #[Computed]
    public function candidate(): ?DedupCandidate
    {
        if (! $this->currentCandidateId) {
            return null;
        }

        return DedupCandidate::with(['listingA', 'listingB'])
            ->find($this->currentCandidateId);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return DedupCandidate::where('status', DedupCandidateStatus::NeedsReview)->count();
    }

    #[Computed]
    public function candidates(): \Illuminate\Database\Eloquent\Collection
    {
        return DedupCandidate::where('status', DedupCandidateStatus::NeedsReview)
            ->with(['listingA', 'listingB'])
            ->orderBy('overall_score', 'desc')
            ->get();
    }

    public function selectCandidate(int $id): void
    {
        $this->currentCandidateId = $id;
    }

    public function confirmMatch(): void
    {
        if (! $this->candidate) {
            return;
        }

        $dedupService = app(DeduplicationService::class);
        $dedupService->resolveMatch($this->candidate->listingA, $this->candidate);

        Flux::toast(
            heading: 'Match Confirmed',
            text: 'Listings have been linked to the same property.',
            variant: 'success',
        );

        $this->loadNextCandidate();
    }

    public function rejectMatch(): void
    {
        if (! $this->candidate) {
            return;
        }

        $dedupService = app(DeduplicationService::class);
        $dedupService->rejectMatch($this->candidate);

        Flux::toast(
            heading: 'Match Rejected',
            text: 'Listings marked as different properties.',
            variant: 'info',
        );

        $this->loadNextCandidate();
    }

    protected function loadNextCandidate(): void
    {
        $next = DedupCandidate::where('status', DedupCandidateStatus::NeedsReview)
            ->orderBy('overall_score', 'desc')
            ->first();

        $this->currentCandidateId = $next?->id;
    }

    public function render(): View
    {
        return view('livewire.dedup.review-candidates');
    }
}
