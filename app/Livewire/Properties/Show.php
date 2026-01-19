<?php

namespace App\Livewire\Properties;

use App\Jobs\CreatePropertyFromListingsJob;
use App\Models\Listing;
use App\Models\Property;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Property $property;

    public ?string $selectedImagePlatform = null;

    public ?string $selectedRawDataPlatform = null;

    public function mount(Property $property): void
    {
        $this->property = $property->load([
            'listings.platform',
            'listings.agent',
            'listings.agency',
        ]);

        // Default to first platform for raw data viewer
        $firstListing = $this->property->listings->first();
        $this->selectedRawDataPlatform = $firstListing?->platform->slug;
    }

    /**
     * Get the primary listing (most recently scraped).
     */
    #[Computed]
    public function primaryListing(): ?Listing
    {
        return $this->property->listings
            ->sortByDesc('scraped_at')
            ->first();
    }

    /**
     * Get available platforms for this property.
     *
     * @return array<array{slug: string, name: string, listing_id: int}>
     */
    #[Computed]
    public function platforms(): array
    {
        return $this->property->listings
            ->map(fn ($listing) => [
                'slug' => $listing->platform->slug,
                'name' => $listing->platform->name,
                'listing_id' => $listing->id,
            ])
            ->unique('slug')
            ->values()
            ->toArray();
    }

    /**
     * Get images grouped by platform.
     *
     * @return array<string, array<string>>
     */
    #[Computed]
    public function imagesByPlatform(): array
    {
        $result = [];

        foreach ($this->property->listings as $listing) {
            $platformSlug = $listing->platform->slug;
            $images = collect($listing->raw_data['images'] ?? [])
                ->map(fn (array|string $img): string => is_array($img) ? $img['url'] : $img)
                ->filter(fn (string $url): bool => ! str_contains($url, '.svg')
                    && ! str_contains($url, 'placeholder')
                    && ! str_contains($url, 'icon')
                    && preg_match('/\.(jpg|jpeg|png|webp)/i', $url)
                )
                ->unique()
                ->values()
                ->take(30)
                ->toArray();

            if (! empty($images)) {
                $result[$platformSlug] = $images;
            }
        }

        return $result;
    }

    /**
     * Get images for the selected platform (or all if none selected).
     *
     * @return array<string>
     */
    #[Computed]
    public function images(): array
    {
        $byPlatform = $this->imagesByPlatform;

        if ($this->selectedImagePlatform && isset($byPlatform[$this->selectedImagePlatform])) {
            return $byPlatform[$this->selectedImagePlatform];
        }

        // Return images from the most recently scraped listing (primary)
        $listing = $this->primaryListing;
        if (! $listing) {
            return [];
        }

        return $byPlatform[$listing->platform->slug] ?? [];
    }

    /**
     * Get all operations (prices) from all listings.
     *
     * @return array<array{type: string, price: float, currency: string, platform: string, listing_id: int, maintenance_fee: float|null}>
     */
    #[Computed]
    public function allPrices(): array
    {
        return $this->property->listings
            ->flatMap(function ($listing) {
                return collect($listing->raw_data['operations'] ?? [])
                    ->map(fn ($op) => [
                        'type' => $op['type'] ?? 'unknown',
                        'price' => (float) ($op['price'] ?? 0),
                        'currency' => $op['currency'] ?? 'MXN',
                        'platform' => $listing->platform->name,
                        'listing_id' => $listing->id,
                        'maintenance_fee' => $op['maintenance_fee'] ?? null,
                    ]);
            })
            ->filter(fn ($op) => $op['price'] > 0)
            ->sortBy('price')
            ->values()
            ->toArray();
    }

    /**
     * Get the primary price (first/lowest for display).
     *
     * @return array{type: string, price: float, currency: string, maintenance_fee: float|null}|null
     */
    #[Computed]
    public function primaryPrice(): ?array
    {
        $prices = $this->allPrices;

        return $prices[0] ?? null;
    }

    /**
     * Get publisher info grouped by listing.
     *
     * @return array<array{platform: string, platform_slug: string, listing_id: int, original_url: string, publisher: array, whatsapp: string|null}>
     */
    #[Computed]
    public function publishers(): array
    {
        return $this->property->listings->map(function ($listing) {
            $rawData = $listing->raw_data ?? [];

            return [
                'platform' => $listing->platform->name,
                'platform_slug' => $listing->platform->slug,
                'listing_id' => $listing->id,
                'original_url' => $listing->original_url,
                'publisher' => [
                    'name' => $rawData['publisher_name'] ?? null,
                    'logo' => $rawData['publisher_logo'] ?? null,
                    'type' => $rawData['publisher_type'] ?? null,
                    'url' => $rawData['publisher_url'] ?? null,
                ],
                'whatsapp' => $rawData['whatsapp'] ?? null,
                'agent' => $listing->agent ? [
                    'name' => $listing->agent->name,
                    'phone' => $listing->agent->phone,
                    'email' => $listing->agent->email,
                ] : null,
                'agency' => $listing->agency ? [
                    'name' => $listing->agency->name,
                    'phone' => $listing->agency->phone,
                    'email' => $listing->agency->email,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Get description - prefer AI property description, then raw.
     *
     * @return array{text: string|null, source: string}
     */
    #[Computed]
    public function description(): array
    {
        // First priority: AI-generated property description
        if ($this->property->description) {
            return [
                'text' => $this->property->description,
                'source' => 'ai',
            ];
        }

        $listing = $this->primaryListing;
        if (! $listing) {
            return ['text' => null, 'source' => 'none'];
        }

        // Fallback: raw description from primary listing
        return [
            'text' => $listing->raw_data['description'] ?? null,
            'source' => 'raw',
        ];
    }

    /**
     * Get freshness status based on last scrape date.
     *
     * @return array{label: string, color: string, days_ago: int}
     */
    #[Computed]
    public function freshnessStatus(): array
    {
        $lastScraped = $this->property->last_scraped_at;
        $daysAgo = $lastScraped?->diffInDays(now()) ?? 999;

        $status = match (true) {
            $daysAgo <= 7 => ['label' => 'Fresh', 'color' => 'green'],
            $daysAgo <= 30 => ['label' => 'Recent', 'color' => 'yellow'],
            default => ['label' => 'Stale', 'color' => 'red'],
        };

        return [...$status, 'days_ago' => $daysAgo];
    }

    /**
     * Get data completeness score (percentage of key fields populated).
     */
    #[Computed]
    public function completenessScore(): int
    {
        $fields = ['address', 'bedrooms', 'bathrooms', 'built_size_m2', 'lot_size_m2'];
        $filled = collect($fields)->filter(fn ($f) => ! empty($this->property->$f))->count();
        $hasImages = count($this->images) > 0;
        $hasPrice = count($this->allPrices) > 0;

        $total = count($fields) + 2; // +2 for images and price
        $score = $filled + ($hasImages ? 1 : 0) + ($hasPrice ? 1 : 0);

        return (int) round(($score / $total) * 100);
    }

    /**
     * Check AI unification status.
     *
     * @return array{is_unified: bool, unified_at: \Carbon\Carbon|null, inconsistencies_count: int}
     */
    #[Computed]
    public function unificationStatus(): array
    {
        $aiUnification = $this->property->ai_unification;

        return [
            'is_unified' => $this->property->ai_unified_at !== null,
            'unified_at' => $this->property->ai_unified_at,
            'inconsistencies_count' => count($aiUnification['inconsistencies'] ?? []),
        ];
    }

    /**
     * Get AI-detected inconsistencies.
     *
     * @return array<array{field: string, values: array, resolved_value: mixed, reasoning: string}>
     */
    #[Computed]
    public function inconsistencies(): array
    {
        return $this->property->ai_unification['inconsistencies'] ?? [];
    }

    /**
     * Get raw data grouped by platform for the viewer.
     *
     * @return array<string, array{listing_id: int, platform: string, data: array}>
     */
    #[Computed]
    public function rawDataByPlatform(): array
    {
        $result = [];

        foreach ($this->property->listings as $listing) {
            $platformSlug = $listing->platform->slug;
            $result[$platformSlug] = [
                'listing_id' => $listing->id,
                'platform' => $listing->platform->name,
                'data' => $listing->raw_data ?? [],
            ];
        }

        return $result;
    }

    /**
     * Get the selected raw data for display.
     */
    #[Computed]
    public function selectedRawData(): ?array
    {
        $byPlatform = $this->rawDataByPlatform;

        if ($this->selectedRawDataPlatform && isset($byPlatform[$this->selectedRawDataPlatform])) {
            return $byPlatform[$this->selectedRawDataPlatform];
        }

        return array_values($byPlatform)[0] ?? null;
    }

    /**
     * Check AI analysis status for this property.
     *
     * @return array{is_analyzed: bool, quality_score: int|null, needs_reanalysis: bool}
     */
    #[Computed]
    public function aiStatus(): array
    {
        $analysis = $this->property->ai_analysis;

        return [
            'is_analyzed' => $analysis !== null,
            'quality_score' => $analysis['quality_score'] ?? null,
            'needs_reanalysis' => $this->property->needs_reanalysis ?? false,
        ];
    }

    /**
     * Trigger AI re-analysis for this property.
     */
    public function reanalyzeWithAi(): void
    {
        // Find the listing group for this property
        $group = $this->property->listingGroups()->first();

        if (! $group) {
            Flux::toast(
                heading: 'Cannot Re-analyze',
                text: 'No listing group found for this property.',
                variant: 'warning',
            );

            return;
        }

        $this->property->markForReanalysis();
        CreatePropertyFromListingsJob::dispatch($group->id);

        Flux::toast(
            heading: 'Re-analysis Started',
            text: 'AI is re-analyzing property data from all listings.',
            variant: 'success',
        );
    }

    public function render(): View
    {
        return view('livewire.properties.show')
            ->title($this->property->address ?? 'Property Details');
    }
}
