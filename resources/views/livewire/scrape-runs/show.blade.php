<div class="space-y-6" wire:poll.3s="refresh">
    {{-- Header --}}
    <div class="flex items-start gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('platforms.show', $run->platform)" wire:navigate />
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ $run->searchQuery?->name ?? __('Unknown Query') }}</flux:heading>
                @switch($run->status->value)
                    @case('pending')
                        <flux:badge color="zinc">{{ __('Pending') }}</flux:badge>
                        @break
                    @case('discovering')
                        <flux:badge color="blue">
                            <span class="mr-1 inline-block size-2 animate-pulse rounded-full bg-blue-400"></span>
                            {{ __('Discovering') }}
                        </flux:badge>
                        @break
                    @case('scraping')
                        <flux:badge color="amber">
                            <span class="mr-1 inline-block size-2 animate-pulse rounded-full bg-amber-400"></span>
                            {{ __('Scraping') }}
                        </flux:badge>
                        @break
                    @case('completed')
                        <flux:badge color="green">{{ __('Completed') }}</flux:badge>
                        @break
                    @case('failed')
                        <flux:badge color="red">{{ __('Failed') }}</flux:badge>
                        @break
                @endswitch
            </div>
            <flux:text class="mt-1">
                {{ $run->platform->name }} · {{ __('Started') }} {{ $run->started_at?->diffForHumans() ?? __('Not started') }}
                · {{ __('Duration') }}: {{ $this->duration }}
            </flux:text>
        </div>
        <div class="flex items-center gap-2">
            @if ($this->isActive)
                <flux:button variant="danger" icon="stop" wire:click="stopRun" wire:confirm="{{ __('Are you sure you want to stop this run?') }}">
                    {{ __('Stop') }}
                </flux:button>
            @elseif ($run->status === \App\Enums\ScrapeRunStatus::Failed || $run->status === \App\Enums\ScrapeRunStatus::Completed)
                <flux:button variant="primary" icon="arrow-path" wire:click="restartRun">
                    {{ __('Restart') }}
                </flux:button>
            @endif
            @if ($run->status === \App\Enums\ScrapeRunStatus::Completed)
                <flux:button variant="primary" icon="document-text" :href="route('listings.index', ['platform' => $run->platform_id])" wire:navigate>
                    {{ __('View Listings') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Overall Progress Card --}}
    <flux:card>
        <div class="flex items-center justify-between">
            <flux:text class="font-medium text-zinc-600 dark:text-zinc-400">{{ __('Overall Progress') }}</flux:text>
            <flux:heading size="xl">{{ $this->overallProgress }}%</flux:heading>
        </div>
        <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
            <div
                class="h-3 rounded-full bg-gradient-to-r from-blue-500 to-green-500 transition-all duration-700"
                style="width: {{ $this->overallProgress }}%"
            ></div>
        </div>
        <div class="mt-4 grid gap-6 sm:grid-cols-2">
            <div>
                <flux:text size="xs" class="uppercase tracking-wide text-zinc-500">{{ __('Phase 1: Discovery') }}</flux:text>
                <div class="mt-2 flex items-center gap-3">
                    <div class="h-2 flex-1 rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <div class="h-2 rounded-full bg-blue-500 transition-all" style="width: {{ $this->discoveryProgress }}%"></div>
                    </div>
                    <flux:text size="sm" class="w-12 text-right tabular-nums">{{ $this->discoveryProgress }}%</flux:text>
                </div>
                <flux:text size="sm" class="mt-1 text-zinc-500">
                    {{ $this->stats['pages_done'] ?? 0 }}/{{ $this->stats['pages_total'] ?: '?' }} {{ __('pages') }}
                    @if (($this->stats['pages_failed'] ?? 0) > 0)
                        · <span class="text-red-500">{{ $this->stats['pages_failed'] }} {{ __('failed') }}</span>
                    @endif
                </flux:text>
            </div>
            <div>
                <flux:text size="xs" class="uppercase tracking-wide text-zinc-500">{{ __('Phase 2: Scraping') }}</flux:text>
                <div class="mt-2 flex items-center gap-3">
                    <div class="h-2 flex-1 rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <div class="h-2 rounded-full bg-amber-500 transition-all" style="width: {{ $this->scrapingProgress }}%"></div>
                    </div>
                    <flux:text size="sm" class="w-12 text-right tabular-nums">{{ $this->scrapingProgress }}%</flux:text>
                </div>
                <flux:text size="sm" class="mt-1 text-zinc-500">
                    {{ $this->stats['listings_scraped'] ?? 0 }}/{{ $this->stats['listings_found'] ?: '?' }} {{ __('listings') }}
                    @if (($this->stats['listings_failed'] ?? 0) > 0)
                        · <span class="text-red-500">{{ $this->stats['listings_failed'] }} {{ __('failed') }}</span>
                    @endif
                </flux:text>
            </div>
        </div>
    </flux:card>

    {{-- Stats Cards Row --}}
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <flux:icon name="document-magnifying-glass" class="size-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Pages') }}</flux:text>
                    <div class="flex items-baseline gap-1">
                        <flux:heading size="lg">{{ $this->stats['pages_done'] ?? 0 }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-400">/ {{ $this->stats['pages_total'] ?: '?' }}</flux:text>
                    </div>
                </div>
            </div>
        </flux:card>
        <flux:card>
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon name="magnifying-glass" class="size-5 text-zinc-600 dark:text-zinc-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Found') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($this->stats['listings_found'] ?? 0) }}</flux:heading>
                </div>
            </div>
        </flux:card>
        <flux:card>
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                    <flux:icon name="check-circle" class="size-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Scraped') }}</flux:text>
                    <div class="flex items-baseline gap-1">
                        <flux:heading size="lg">{{ $this->stats['listings_scraped'] ?? 0 }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-400">/ {{ $this->stats['listings_found'] ?: '?' }}</flux:text>
                    </div>
                </div>
            </div>
        </flux:card>
        <flux:card>
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg {{ ($this->stats['listings_failed'] ?? 0) + ($this->stats['pages_failed'] ?? 0) > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-zinc-100 dark:bg-zinc-700' }}">
                    <flux:icon name="exclamation-triangle" class="size-5 {{ ($this->stats['listings_failed'] ?? 0) + ($this->stats['pages_failed'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-400' }}" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Failed') }}</flux:text>
                    <flux:heading size="lg" class="{{ ($this->stats['listings_failed'] ?? 0) + ($this->stats['pages_failed'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                        {{ ($this->stats['pages_failed'] ?? 0) + ($this->stats['listings_failed'] ?? 0) }}
                    </flux:heading>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Error Message --}}
    @if ($run->error_message)
        <flux:callout variant="danger" icon="exclamation-circle">
            <flux:callout.heading>{{ __('Run Failed') }}</flux:callout.heading>
            <flux:callout.text>{{ $run->error_message }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Main Grid --}}
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Activity Feed (2 cols) --}}
        <flux:card class="lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Activity Feed') }}</flux:heading>
                @if ($this->isActive)
                    <div class="flex items-center gap-2">
                        <span class="size-2 animate-pulse rounded-full bg-green-500"></span>
                        <flux:text size="sm" class="text-green-600 dark:text-green-400">{{ __('Live') }}</flux:text>
                    </div>
                @endif
            </div>

            @if ($this->recentJobs->isEmpty())
                <div class="mt-6 flex flex-col items-center justify-center py-12">
                    <div class="rounded-full bg-zinc-100 p-4 dark:bg-zinc-700">
                        <flux:icon name="clock" class="size-8 text-zinc-400" />
                    </div>
                    <flux:text class="mt-4 text-zinc-500">{{ __('Waiting for jobs to start...') }}</flux:text>
                </div>
            @else
                <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-700">
                    @foreach ($this->recentJobs as $job)
                        <div wire:key="job-{{ $job->id }}" class="flex items-center gap-4 py-3">
                            @switch($job->status->value)
                                @case('completed')
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                        <flux:icon name="check" class="size-4 text-green-600 dark:text-green-400" />
                                    </div>
                                    @break
                                @case('failed')
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                                        <flux:icon name="x-mark" class="size-4 text-red-600 dark:text-red-400" />
                                    </div>
                                    @break
                                @case('running')
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                        <flux:icon name="arrow-path" class="size-4 animate-spin text-blue-600 dark:text-blue-400" />
                                    </div>
                                    @break
                                @default
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                                        <flux:icon name="clock" class="size-4 text-zinc-400" />
                                    </div>
                            @endswitch
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <flux:text class="font-medium">
                                        @if ($job->job_type->value === 'discovery')
                                            {{ __('Page') }} {{ $job->current_page ?? '?' }}
                                        @else
                                            {{ __('Listing') }} #{{ $job->discovered_listing_id }}
                                        @endif
                                    </flux:text>
                                    @if ($job->job_type->value === 'discovery')
                                        <flux:badge color="blue" size="sm">{{ __('Discovery') }}</flux:badge>
                                    @else
                                        <flux:badge color="amber" size="sm">{{ __('Scrape') }}</flux:badge>
                                    @endif
                                </div>
                                <flux:text size="sm" class="text-zinc-500">
                                    @if ($job->status->value === 'completed' && $job->result)
                                        @if (isset($job->result['listings_found']))
                                            {{ __('Found') }} {{ $job->result['listings_found'] }} {{ __('listings') }}
                                        @elseif (isset($job->result['listing_id']))
                                            {{ __('Created listing') }} #{{ $job->result['listing_id'] }}
                                        @endif
                                    @elseif ($job->status->value === 'failed')
                                        <span class="text-red-500">{{ Str::limit($job->error_message, 50) }}</span>
                                    @elseif ($job->status->value === 'running')
                                        {{ __('Processing...') }}
                                    @endif
                                </flux:text>
                            </div>
                            <flux:text size="sm" class="shrink-0 text-zinc-400">
                                {{ $job->completed_at?->diffForHumans(short: true) ?? $job->started_at?->diffForHumans(short: true) ?? '-' }}
                            </flux:text>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        {{-- Failed Jobs (1 col) --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="size-5 {{ $this->failedJobsCount > 0 ? 'text-red-500' : 'text-zinc-400' }}" />
                    <flux:heading size="lg">{{ __('Failed Jobs') }}</flux:heading>
                </div>
                @if ($this->failedJobsCount > 0)
                    <flux:button size="sm" variant="ghost" wire:click="retryAllFailed">
                        {{ __('Retry All') }}
                    </flux:button>
                @endif
            </div>

            @if ($this->failedJobsCount === 0)
                <div class="mt-6 flex flex-col items-center justify-center py-8">
                    <div class="rounded-full bg-green-100 p-3 dark:bg-green-900/30">
                        <flux:icon name="check" class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <flux:text class="mt-3 text-zinc-500">{{ __('No failures') }}</flux:text>
                </div>
            @else
                <div class="mt-4 space-y-2">
                    @foreach ($this->failedJobs as $job)
                        <div wire:key="failed-{{ $job->id }}" class="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                            <div class="min-w-0 flex-1">
                                <flux:text size="sm" class="font-medium text-red-700 dark:text-red-400">
                                    {{ $job->job_type->value === 'discovery' ? __('Page') . ' ' . ($job->current_page ?? '?') : __('Listing') . ' #' . $job->discovered_listing_id }}
                                </flux:text>
                                <flux:text size="xs" class="mt-1 truncate text-red-600 dark:text-red-500">{{ Str::limit($job->error_message, 40) }}</flux:text>
                            </div>
                            <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="retryJob({{ $job->id }})" />
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</div>
