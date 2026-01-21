<?php

namespace App\Jobs\Concerns;

use App\Enums\DiscoveredListingStatus;
use App\Models\DiscoveredListing;

trait StoresDiscoveredListings
{
    /**
     * Store discovered listings from a search page.
     *
     * Uses updateOrCreate to associate existing listings with the new run,
     * ensuring re-runs properly scrape previously discovered listings.
     *
     * @param  array<array{url: string, external_id: string|null, preview?: array}>  $listings
     */
    protected function storeListings(int $platformId, array $listings, int $batchId): void
    {
        foreach ($listings as $listing) {
            $preview = $listing['preview'] ?? [];

            DiscoveredListing::updateOrCreate(
                [
                    'platform_id' => $platformId,
                    'url' => $listing['url'],
                ],
                [
                    'external_id' => $listing['external_id'] ?? null,
                    'batch_id' => (string) $batchId,
                    'scrape_run_id' => $this->scrapeRunId,
                    'status' => DiscoveredListingStatus::Pending,
                    'priority' => 0,
                    'preview_title' => $preview['title'] ?? null,
                    'preview_price' => $preview['price'] ?? null,
                    'preview_location' => $preview['location'] ?? null,
                    'preview_image' => $preview['image'] ?? null,
                ]
            );
        }
    }
}
