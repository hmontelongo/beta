<?php

namespace App\Jobs;

use App\Enums\ListingGroupStatus;
use App\Models\ListingGroup;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPropertyCreationBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('property-creation');
    }

    public function handle(): void
    {
        if (! config('services.property_creation.enabled', true)) {
            Log::info('Property creation is disabled, skipping batch job');

            return;
        }

        $dispatched = 0;

        // 1. Dispatch jobs for listing groups pending AI processing (no limit)
        $groups = ListingGroup::where('status', ListingGroupStatus::PendingAi)
            ->orderBy('created_at')
            ->get();

        foreach ($groups as $group) {
            CreatePropertyFromListingsJob::dispatch($group->id);
            $dispatched++;
        }

        // 2. Handle properties that need re-analysis (no limit)
        $propertiesToReanalyze = Property::needsReanalysis()
            ->whereHas('listings')
            ->get();

        foreach ($propertiesToReanalyze as $property) {
            $this->queuePropertyReanalysis($property);
            $dispatched++;
        }

        if ($dispatched > 0) {
            Log::info('Property creation batch dispatched', [
                'groups_dispatched' => $groups->count(),
                'properties_reanalyzed' => $propertiesToReanalyze->count(),
                'total_dispatched' => $dispatched,
            ]);
        }
    }

    /**
     * Queue a property for re-analysis by creating/updating its listing group.
     */
    protected function queuePropertyReanalysis(Property $property): void
    {
        // Find or create a listing group for this property
        $group = $property->listingGroups()->first();

        if (! $group) {
            // Create a new group from the property's listings
            $group = ListingGroup::create([
                'status' => ListingGroupStatus::PendingAi,
                'property_id' => $property->id,
                'match_score' => 100.0, // Existing property
            ]);

            // Link all listings to the group via column
            $listings = $property->listings;
            $firstListing = $listings->first();
            foreach ($listings as $listing) {
                $listing->update([
                    'listing_group_id' => $group->id,
                    'is_primary_in_group' => $listing->id === $firstListing?->id,
                ]);
            }
        } else {
            // Update existing group status
            $group->update(['status' => ListingGroupStatus::PendingAi]);
        }

        // Dispatch the job
        CreatePropertyFromListingsJob::dispatch($group->id);

        Log::info('Queued property for re-analysis', [
            'property_id' => $property->id,
            'listing_group_id' => $group->id,
            'listings_count' => $property->listings()->count(),
        ]);
    }
}
