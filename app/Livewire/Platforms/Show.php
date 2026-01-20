<?php

namespace App\Livewire\Platforms;

use App\Enums\DiscoveredListingStatus;
use App\Enums\ScrapeRunStatus;
use App\Models\DiscoveredListing;
use App\Models\Listing;
use App\Models\Platform;
use App\Models\SearchQuery;
use App\Services\ScrapeOrchestrator;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Platform $platform;

    // Add Query Modal
    public bool $showAddQueryModal = false;

    public string $queryName = '';

    public string $queryUrl = '';

    // Schedule Modal
    public bool $showScheduleModal = false;

    public ?int $scheduleQueryId = null;

    public string $scheduleType = 'none';

    public int $intervalValue = 1;

    public string $intervalUnit = 'hours';

    public ?string $scheduledTime = null;

    public ?int $scheduledDay = null;

    public function mount(Platform $platform): void
    {
        $this->platform = $platform;
    }

    #[Computed]
    public function searchQueries()
    {
        return $this->platform->searchQueries()
            ->with(['activeRun', 'latestRun'])
            ->withCount('scrapeRuns')
            ->withSum('scrapeRuns as total_listings_scraped', 'stats->listings_scraped')
            ->orderBy('name')
            ->get();
    }

    /**
     * Common time options for scheduling.
     *
     * @return array<string, string>
     */
    public function getTimeOptions(): array
    {
        $options = [];
        for ($hour = 0; $hour < 24; $hour++) {
            foreach (['00', '30'] as $minute) {
                $time = sprintf('%02d:%s', $hour, $minute);
                $label = date('g:i A', strtotime($time));
                $options[$time] = $label;
            }
        }

        return $options;
    }

    /**
     * Days of the week for scheduling.
     *
     * @return array<int, string>
     */
    public function getDayOptions(): array
    {
        return [
            0 => __('Sun'),
            1 => __('Mon'),
            2 => __('Tue'),
            3 => __('Wed'),
            4 => __('Thu'),
            5 => __('Fri'),
            6 => __('Sat'),
        ];
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

    #[Computed]
    public function scheduleQuery(): ?SearchQuery
    {
        return $this->scheduleQueryId ? SearchQuery::find($this->scheduleQueryId) : null;
    }

    #[Computed]
    public function nextRunPreview(): ?string
    {
        if ($this->scheduleType === 'none') {
            return null;
        }

        $nextRun = $this->calculatePreviewNextRun();

        if (! $nextRun) {
            return null;
        }

        return $nextRun->diffForHumans().' ('.$nextRun->format('M j, Y g:i A').')';
    }

    protected function calculatePreviewNextRun(): ?Carbon
    {
        return match ($this->scheduleType) {
            'interval' => $this->calculateIntervalPreview(),
            'daily' => $this->calculateDailyPreview(),
            'weekly' => $this->calculateWeeklyPreview(),
            default => null,
        };
    }

    protected function calculateIntervalPreview(): Carbon
    {
        $value = max(1, (int) $this->intervalValue);

        $minutes = match ($this->intervalUnit) {
            'minutes' => $value,
            'hours' => $value * 60,
            'days' => $value * 60 * 24,
            default => 60,
        };

        return now()->addMinutes($minutes);
    }

    protected function calculateDailyPreview(): Carbon
    {
        $time = $this->scheduledTime ?? '03:00';
        $next = now()->setTimeFromTimeString($time.':00');

        if ($next->isPast()) {
            $next->addDay();
        }

        return $next;
    }

    protected function calculateWeeklyPreview(): Carbon
    {
        $time = $this->scheduledTime ?? '06:00';
        $dayOfWeek = $this->scheduledDay ?? 1;

        $thisWeek = now()
            ->startOfWeek(Carbon::SUNDAY)
            ->addDays($dayOfWeek)
            ->setTimeFromTimeString($time.':00');

        if ($thisWeek->isFuture()) {
            return $thisWeek;
        }

        return $thisWeek->addWeek();
    }

    public function openScheduleModal(int $queryId): void
    {
        $query = SearchQuery::findOrFail($queryId);

        $this->scheduleQueryId = $queryId;
        $this->scheduleType = $query->auto_enabled ? ($query->schedule_type ?? 'interval') : 'none';
        $this->intervalValue = $query->interval_value ?? 1;
        $this->intervalUnit = $query->interval_unit ?? 'hours';
        $this->scheduledTime = $query->scheduled_time ? substr($query->scheduled_time, 0, 5) : '03:00';
        $this->scheduledDay = $query->scheduled_day ?? 1;

        $this->showScheduleModal = true;

        // Clear computed properties to ensure they refresh
        unset($this->scheduleQuery, $this->nextRunPreview);
    }

    /**
     * Sanitize interval value when it changes to ensure it's a positive integer.
     */
    public function updatedIntervalValue(mixed $value): void
    {
        // Convert to integer and ensure it's at least 1
        $this->intervalValue = max(1, (int) abs($value));

        // Clear computed to refresh preview
        unset($this->nextRunPreview);
    }

    public function saveSchedule(): void
    {
        $query = SearchQuery::findOrFail($this->scheduleQueryId);

        if ($this->scheduleType === 'none') {
            $query->disableScheduling();
            Flux::toast(__('Schedule disabled'), variant: 'info');
        } else {
            // Ensure interval value is a valid positive integer
            $intervalValue = max(1, (int) abs($this->intervalValue));

            $query->update([
                'schedule_type' => $this->scheduleType,
                'interval_value' => $intervalValue,
                'interval_unit' => $this->intervalUnit,
                'scheduled_time' => $this->scheduledTime ? $this->scheduledTime.':00' : null,
                'scheduled_day' => $this->scheduledDay,
                'auto_enabled' => true,
            ]);

            // Calculate and set next_run_at after updating schedule fields
            $query->refresh();
            $query->update(['next_run_at' => $query->calculateNextRunAt()]);

            Flux::toast(__('Schedule updated'), variant: 'success');
        }

        $this->showScheduleModal = false;
        unset($this->searchQueries);
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

        // Reset schedule if auto-enabled (manual run counts as the scheduled run)
        if ($query->auto_enabled) {
            $query->scheduleNextRun();
        }

        Flux::toast('Scrape started!', variant: 'success');

        return $this->redirect(route('runs.show', $run), navigate: true);
    }

    public function runScheduledNow(): void
    {
        $exitCode = Artisan::call('scrape:run-scheduled', ['--force' => true]);

        if ($exitCode === 0) {
            Flux::toast('Scheduled scrapes triggered!', variant: 'success');
        } else {
            Flux::toast('Failed to run scheduled scrapes', variant: 'danger');
        }

        unset($this->searchQueries, $this->recentRuns);
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
