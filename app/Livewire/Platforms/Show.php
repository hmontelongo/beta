<?php

namespace App\Livewire\Platforms;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeRunStatus;
use App\Models\DiscoveredListing;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\SearchQuery;
use App\Services\ScrapeOrchestrator;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Platform $platform;

    public bool $showAddQueryModal = false;

    public string $queryName = '';

    public string $queryUrl = '';

    public function mount(Platform $platform): void
    {
        $this->platform = $platform;
    }

    #[Computed]
    public function searchQueries()
    {
        return $this->platform->searchQueries()
            ->withCount('scrapeRuns')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function recentRuns()
    {
        return $this->platform->scrapeRuns()
            ->with('searchQuery')
            ->latest()
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function stats()
    {
        return [
            'discovered' => DiscoveredListing::where('platform_id', $this->platform->id)->count(),
            'pending' => DiscoveredListing::where('platform_id', $this->platform->id)
                ->where('status', DiscoveredListingStatus::Pending)
                ->count(),
            'scraped' => Listing::where('platform_id', $this->platform->id)->count(),
        ];
    }

    #[Computed]
    public function hasActiveRun()
    {
        return $this->platform->scrapeRuns()
            ->whereIn('status', [
                ScrapeRunStatus::Pending,
                ScrapeRunStatus::Discovering,
                ScrapeRunStatus::Scraping,
            ])
            ->exists();
    }

    public function startScrape(int $queryId)
    {
        $query = SearchQuery::findOrFail($queryId);

        $hasActiveRun = $query->scrapeRuns()
            ->whereIn('status', [
                ScrapeRunStatus::Pending,
                ScrapeRunStatus::Discovering,
                ScrapeRunStatus::Scraping,
            ])
            ->exists();

        if ($hasActiveRun) {
            Flux::toast('A scrape is already in progress for this query.', variant: 'danger');

            return null;
        }

        $orchestrator = app(ScrapeOrchestrator::class);
        $run = $orchestrator->startRun($query);

        Flux::toast('Scrape started!', variant: 'success');

        return $this->redirect(route('runs.show', $run), navigate: true);
    }

    public function refreshStats(): void
    {
        unset($this->stats, $this->recentRuns, $this->searchQueries, $this->hasActiveRun);
    }

    public function addSearchQuery(): void
    {
        $validated = $this->validate([
            'queryName' => ['required', 'string', 'max:255'],
            'queryUrl' => ['required', 'url', 'max:2048'],
        ]);

        $this->platform->searchQueries()->create([
            'name' => $validated['queryName'],
            'search_url' => $validated['queryUrl'],
            'is_active' => true,
        ]);

        $this->reset(['queryName', 'queryUrl', 'showAddQueryModal']);
        unset($this->searchQueries);

        Flux::toast('Search query added successfully!', variant: 'success');
    }

    public function deleteSearchQuery(int $queryId): void
    {
        $query = SearchQuery::findOrFail($queryId);
        $query->delete();

        unset($this->searchQueries);

        Flux::toast('Search query deleted.', variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.platforms.show');
    }
}
