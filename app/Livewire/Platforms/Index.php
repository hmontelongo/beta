<?php

namespace App\Livewire\Platforms;

use App\Enums\ScrapeRunStatus;
use App\Models\Platform;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Platforms')]
class Index extends Component
{
    public function render(): View
    {
        return view('livewire.platforms.index', [
            'platforms' => Platform::query()
                ->withCount(['searchQueries', 'scrapeRuns', 'listings'])
                ->withCount(['scrapeRuns as active_runs_count' => function ($query) {
                    $query->whereIn('status', [
                        ScrapeRunStatus::Pending,
                        ScrapeRunStatus::Discovering,
                        ScrapeRunStatus::Scraping,
                    ]);
                }])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
