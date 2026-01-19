<?php

namespace App\Livewire\Dedup;

use App\Enums\ListingGroupStatus;
use App\Models\ListingGroup;
use App\Services\Dedup\DeduplicationService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Review Duplicates')]
class ReviewCandidates extends Component
{
    public ?int $currentGroupId = null;

    public string $rejectionReason = '';

    public function mount(): void
    {
        // Load first group if available
        $first = ListingGroup::where('status', ListingGroupStatus::PendingReview)
            ->orderByDesc('match_score')
            ->first();

        $this->currentGroupId = $first?->id;
    }

    #[Computed]
    public function group(): ?ListingGroup
    {
        if (! $this->currentGroupId) {
            return null;
        }

        return ListingGroup::with(['listings.platform'])
            ->find($this->currentGroupId);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return ListingGroup::where('status', ListingGroupStatus::PendingReview)->count();
    }

    /**
     * @return Collection<int, ListingGroup>
     */
    #[Computed]
    public function groups(): Collection
    {
        return ListingGroup::where('status', ListingGroupStatus::PendingReview)
            ->with(['listings'])
            ->orderByDesc('match_score')
            ->get();
    }

    public function selectGroup(int $id): void
    {
        $this->currentGroupId = $id;
        $this->rejectionReason = '';
        unset($this->group);
    }

    public function approveGroup(): void
    {
        if (! $this->group) {
            return;
        }

        $dedupService = app(DeduplicationService::class);
        $dedupService->approveGroup($this->group);

        Flux::toast(
            heading: 'Group Approved',
            text: 'Listings will be processed by AI to create a property.',
            variant: 'success',
        );

        $this->loadNextGroup();
    }

    public function rejectGroup(): void
    {
        if (! $this->group) {
            return;
        }

        $dedupService = app(DeduplicationService::class);
        $dedupService->rejectGroup($this->group, $this->rejectionReason ?: null);

        Flux::toast(
            heading: 'Group Rejected',
            text: 'Listings will be re-processed as separate entities.',
            variant: 'info',
        );

        $this->loadNextGroup();
    }

    public function retryAiProcessing(): void
    {
        if (! $this->group) {
            return;
        }

        // Clear rejection reason and set back to pending_ai
        $this->group->update([
            'status' => ListingGroupStatus::PendingAi,
            'rejection_reason' => null,
        ]);

        Flux::toast(
            heading: 'Retrying AI Processing',
            text: 'Group will be re-processed by AI shortly.',
            variant: 'success',
        );

        $this->loadNextGroup();
    }

    public function removeListingFromGroup(int $listingId): void
    {
        if (! $this->group) {
            return;
        }

        $listing = $this->group->listings()->find($listingId);
        if (! $listing) {
            Flux::toast(
                heading: 'Error',
                text: 'Listing not found in this group.',
                variant: 'danger',
            );

            return;
        }

        $dedupService = app(DeduplicationService::class);
        $dedupService->removeListingFromGroup($this->group, $listing);

        // Refresh the group data
        unset($this->group, $this->groups, $this->pendingCount);

        // Check if group still exists and has listings
        $refreshedGroup = ListingGroup::find($this->currentGroupId);
        if (! $refreshedGroup || $refreshedGroup->status !== ListingGroupStatus::PendingReview) {
            Flux::toast(
                heading: 'Listing Removed',
                text: 'Group has been resolved. Moving to next.',
                variant: 'success',
            );
            $this->loadNextGroup();
        } else {
            Flux::toast(
                heading: 'Listing Removed',
                text: 'Listing will be re-processed separately.',
                variant: 'success',
            );
        }
    }

    protected function loadNextGroup(): void
    {
        $previousId = $this->currentGroupId;

        // Clear computed property cache so they refresh
        unset($this->group, $this->groups, $this->pendingCount);
        $this->rejectionReason = '';

        $next = ListingGroup::where('status', ListingGroupStatus::PendingReview)
            ->when($previousId, fn ($q) => $q->where('id', '!=', $previousId))
            ->orderByDesc('match_score')
            ->first();

        $this->currentGroupId = $next?->id;
    }

    public function render(): View
    {
        return view('livewire.dedup.review-candidates');
    }
}
