<?php

namespace App\Livewire\Admin\ScrapeRuns;

use App\Enums\ScrapeJobStatus;
use App\Enums\ScrapeRunStatus;
use App\Jobs\DiscoverPageJob;
use App\Jobs\ScrapeListingJob;
use App\Models\ScrapeJob;
use App\Models\ScrapeRun;
use App\Services\JobCancellationService;
use App\Services\ScrapeOrchestrator;
use Carbon\CarbonInterval;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public ScrapeRun $run;

    public string $jobTypeFilter = 'all';

    public function mount(ScrapeRun $run): void
    {
        $this->run = $run->load(['searchQuery', 'platform']);
    }

    public function setJobTypeFilter(string $filter): void
    {
        $this->jobTypeFilter = $filter;
        unset($this->recentJobs);
    }

    #[Computed]
    public function recentJobs()
    {
        $query = $this->run->scrapeJobs()->latest();

        if ($this->jobTypeFilter === 'discovery') {
            $query->where('job_type', 'discovery');
        } elseif ($this->jobTypeFilter === 'scraping') {
            $query->where('job_type', 'listing');
        }

        return $query->limit(15)->get();
    }

    #[Computed]
    public function failedJobs()
    {
        return $this->run->scrapeJobs()
            ->where('status', ScrapeJobStatus::Failed)
            ->latest()
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function failedJobsCount(): int
    {
        return $this->run->scrapeJobs()
            ->where('status', ScrapeJobStatus::Failed)
            ->count();
    }

    #[Computed]
    public function stats(): array
    {
        // Compute stats from actual records - single source of truth
        return $this->run->computeStats();
    }

    #[Computed]
    public function discoveryProgress(): int
    {
        $total = $this->stats['pages_total'] ?? 0;
        $done = $this->stats['pages_done'] ?? 0;

        return $total > 0 ? min(100, round(($done / $total) * 100)) : 0;
    }

    #[Computed]
    public function scrapingProgress(): int
    {
        $total = $this->stats['listings_found'] ?? 0;
        $done = $this->stats['listings_scraped'] ?? 0;

        return $total > 0 ? min(100, round(($done / $total) * 100)) : 0;
    }

    #[Computed]
    public function overallProgress(): int
    {
        // Weight: 20% discovery, 80% scraping
        $discoveryWeight = 20;
        $scrapingWeight = 80;

        $discoveryContribution = ($this->discoveryProgress / 100) * $discoveryWeight;
        $scrapingContribution = ($this->scrapingProgress / 100) * $scrapingWeight;

        return min(100, round($discoveryContribution + $scrapingContribution));
    }

    #[Computed]
    public function isActive(): bool
    {
        return in_array($this->run->status, [
            ScrapeRunStatus::Pending,
            ScrapeRunStatus::Discovering,
            ScrapeRunStatus::Scraping,
        ]);
    }

    #[Computed]
    public function duration(): string
    {
        $start = $this->run->started_at ?? $this->run->created_at;
        $end = $this->run->completed_at ?? now();

        $seconds = $start->diffInSeconds($end);

        if ($seconds < 60) {
            return $seconds.'s';
        }

        return CarbonInterval::seconds($seconds)->cascade()->forHumans(short: true);
    }

    public function refresh(): void
    {
        $this->run = $this->run->fresh();
        unset(
            $this->recentJobs,
            $this->failedJobs,
            $this->failedJobsCount,
            $this->stats,
            $this->discoveryProgress,
            $this->scrapingProgress,
            $this->overallProgress,
            $this->isActive,
            $this->duration,
            $this->canResume
        );
    }

    public function retryJob(int $jobId): void
    {
        $job = ScrapeJob::findOrFail($jobId);

        if ($job->status !== ScrapeJobStatus::Failed) {
            Flux::toast('Job is not in failed state.', variant: 'warning');

            return;
        }

        if ($job->job_type->value === 'discovery') {
            DiscoverPageJob::dispatch(
                $job->parent_id ?? $job->id,
                $job->target_url,
                $job->current_page ?? 1,
                $this->run->id
            );
        } else {
            ScrapeListingJob::dispatch($job->discovered_listing_id, $this->run->id);
        }

        $job->delete();

        Flux::toast('Job retry dispatched!', variant: 'success');
        unset($this->recentJobs, $this->failedJobs, $this->failedJobsCount);
    }

    public function retryAllFailed(): void
    {
        $failedJobs = $this->run->scrapeJobs()
            ->where('status', ScrapeJobStatus::Failed)
            ->get();

        $count = 0;
        foreach ($failedJobs as $job) {
            if ($job->job_type->value === 'discovery') {
                DiscoverPageJob::dispatch(
                    $job->parent_id ?? $job->id,
                    $job->target_url,
                    $job->current_page ?? 1,
                    $this->run->id
                );
            } else {
                ScrapeListingJob::dispatch($job->discovered_listing_id, $this->run->id);
            }
            $job->delete();
            $count++;
        }

        Flux::toast("{$count} jobs queued for retry!", variant: 'success');
        unset($this->recentJobs, $this->failedJobs, $this->failedJobsCount);
    }

    public function stopRun(): void
    {
        // Use JobCancellationService for proper queue cleanup
        $cancellationService = app(JobCancellationService::class);

        // Cancel discovery and scraping jobs for this run
        $cancellationService->cancelDiscoveryJobs($this->run->id);
        $result = $cancellationService->cancelScrapingJobs($this->run->id);

        Flux::toast(
            text: 'Run stopped. Pending listings preserved for resume.',
            variant: 'warning'
        );

        $this->refresh();
    }

    public function restartRun(): void
    {
        $searchQuery = $this->run->searchQuery;

        if (! $searchQuery) {
            Flux::toast('Cannot restart - search query not found.', variant: 'danger');

            return;
        }

        $orchestrator = app(ScrapeOrchestrator::class);
        $newRun = $orchestrator->startRun($searchQuery);

        Flux::toast('New run started!', variant: 'success');

        $this->redirect(route('runs.show', $newRun), navigate: true);
    }

    public function resumeRun(): void
    {
        $orchestrator = app(ScrapeOrchestrator::class);
        $count = $orchestrator->resumeRun($this->run);

        if ($count === 0) {
            Flux::toast('No pending listings to resume.', variant: 'warning');
        } else {
            Flux::toast("Resumed {$count} listings!", variant: 'success');
        }

        $this->refresh();
    }

    #[Computed]
    public function canResume(): bool
    {
        return in_array($this->run->status, [
            ScrapeRunStatus::Stopped,
            ScrapeRunStatus::Failed,
        ]);
    }

    public function render(): View
    {
        return view('livewire.admin.scrape-runs.show');
    }
}
