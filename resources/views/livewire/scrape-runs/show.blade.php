<div class="space-y-6" wire:poll.3s="refresh">
    {{-- Header --}}
    <div class="flex justify-between items-start">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('platforms.show', $run->platform)" wire:navigate />
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="xl">{{ $run->searchQuery?->name ?? __('Unknown Query') }}</flux:heading>
                    <flux:badge size="sm" :color="match($run->status->value) {
                        'pending' => 'zinc',
                        'discovering' => 'blue',
                        'scraping' => 'amber',
                        'completed' => 'green',
                        'failed' => 'red',
                        'stopped' => 'orange',
                        default => 'zinc'
                    }">
                        @if (in_array($run->status->value, ['discovering', 'scraping']))
                            <span class="mr-1 size-2 animate-pulse rounded-full bg-current inline-block"></span>
                        @endif
                        {{ ucfirst($run->status->value) }}
                    </flux:badge>
                </div>
                <flux:subheading>
                    {{ $run->platform->name }} · {{ __('Started') }} {{ $run->started_at?->diffForHumans() ?? __('Not started') }} · {{ $this->duration }}
                </flux:subheading>
            </div>
        </div>
        <div class="flex gap-2">
            @if ($this->isActive)
                <flux:button variant="danger" icon="stop" wire:click="stopRun" wire:confirm="{{ __('Stop this run?') }}">{{ __('Stop') }}</flux:button>
            @elseif ($this->canResume)
                <flux:button variant="primary" icon="play" wire:click="resumeRun">{{ __('Resume') }}</flux:button>
                <flux:button variant="ghost" icon="arrow-path" wire:click="restartRun">{{ __('Restart') }}</flux:button>
            @elseif ($run->status->value === 'completed')
                <flux:button variant="ghost" icon="arrow-path" wire:click="restartRun">{{ __('Restart') }}</flux:button>
            @endif
            @if ($run->status->value === 'completed')
                <flux:button variant="primary" icon="document-text" :href="route('listings.index', ['platform' => $run->platform_id])" wire:navigate>{{ __('View Listings') }}</flux:button>
            @endif
        </div>
    </div>

    {{-- Progress Card --}}
    <flux:card>
        <div class="flex justify-between items-center mb-4">
            <flux:heading>{{ __('Overall Progress') }}</flux:heading>
            <flux:heading size="xl">{{ $this->overallProgress }}%</flux:heading>
        </div>

        <div class="h-3 rounded-full bg-zinc-100 dark:bg-zinc-700 mb-6">
            <div class="h-3 rounded-full bg-gradient-to-r from-blue-500 to-green-500 transition-all" style="width: {{ $this->overallProgress }}%"></div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <flux:subheading class="uppercase tracking-wide text-xs">{{ __('Phase 1: Discovery') }}</flux:subheading>
                <div class="mt-2 flex items-center gap-3">
                    <div class="h-2 flex-1 rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <div class="h-2 rounded-full bg-blue-500" style="width: {{ $this->discoveryProgress }}%"></div>
                    </div>
                    <span class="text-sm tabular-nums w-12 text-right">{{ $this->discoveryProgress }}%</span>
                </div>
                <flux:subheading class="mt-1">
                    {{ $this->stats['pages_done'] ?? 0 }}/{{ $this->stats['pages_total'] ?? 0 }} {{ __('pages') }}
                    @if (($this->stats['pages_failed'] ?? 0) > 0)
                        · <span class="text-red-500">{{ $this->stats['pages_failed'] }} {{ __('failed') }}</span>
                    @endif
                </flux:subheading>
            </div>
            <div>
                <flux:subheading class="uppercase tracking-wide text-xs">{{ __('Phase 2: Scraping') }}</flux:subheading>
                <div class="mt-2 flex items-center gap-3">
                    <div class="h-2 flex-1 rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <div class="h-2 rounded-full bg-amber-500" style="width: {{ $this->scrapingProgress }}%"></div>
                    </div>
                    <span class="text-sm tabular-nums w-12 text-right">{{ $this->scrapingProgress }}%</span>
                </div>
                <flux:subheading class="mt-1">
                    {{ $this->stats['listings_scraped'] ?? 0 }}/{{ $this->stats['listings_found'] ?? 0 }} {{ __('listings') }}
                    @if (($this->stats['listings_failed'] ?? 0) > 0)
                        · <span class="text-red-500">{{ $this->stats['listings_failed'] }} {{ __('failed') }}</span>
                    @endif
                </flux:subheading>
            </div>
        </div>
    </flux:card>

    {{-- Stats Row --}}
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['label' => __('Pages'), 'value' => $this->stats['pages_done'] ?? 0, 'total' => $this->stats['pages_total'] ?? 0, 'icon' => 'document-magnifying-glass', 'color' => 'blue'],
            ['label' => __('Found'), 'value' => $this->stats['listings_found'] ?? 0, 'total' => null, 'icon' => 'magnifying-glass', 'color' => 'zinc'],
            ['label' => __('Scraped'), 'value' => $this->stats['listings_scraped'] ?? 0, 'total' => $this->stats['listings_found'] ?? 0, 'icon' => 'check-circle', 'color' => 'green'],
            ['label' => __('Failed'), 'value' => ($this->stats['pages_failed'] ?? 0) + ($this->stats['listings_failed'] ?? 0), 'total' => null, 'icon' => 'exclamation-triangle', 'color' => (($this->stats['pages_failed'] ?? 0) + ($this->stats['listings_failed'] ?? 0)) > 0 ? 'red' : 'zinc'],
        ] as $stat)
            <div class="rounded-lg px-6 py-4 bg-zinc-50 dark:bg-zinc-800">
                <flux:subheading>{{ $stat['label'] }}</flux:subheading>
                <div class="flex items-baseline gap-1">
                    <flux:heading size="xl" class="{{ $stat['color'] === 'red' ? 'text-red-500' : '' }}">{{ number_format($stat['value']) }}</flux:heading>
                    @if ($stat['total'] !== null)
                        <flux:subheading>/ {{ number_format($stat['total']) }}</flux:subheading>
                    @endif
                </div>
            </div>
        @endforeach
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
        {{-- Activity Feed --}}
        <flux:card class="lg:col-span-2">
            <div class="flex justify-between items-center mb-4">
                <flux:heading size="lg">{{ __('Activity Feed') }}</flux:heading>
                @if ($this->isActive)
                    <flux:badge color="green" size="sm">
                        <span class="mr-1 size-2 animate-pulse rounded-full bg-green-400 inline-block"></span>
                        {{ __('Live') }}
                    </flux:badge>
                @endif
            </div>

            @if ($this->recentJobs->isEmpty())
                <div class="flex flex-col items-center py-12">
                    <flux:icon name="clock" class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:subheading class="mt-4">{{ __('Waiting for jobs to start...') }}</flux:subheading>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Job') }}</flux:table.column>
                        <flux:table.column>{{ __('Result') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Time') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->recentJobs as $job)
                            <flux:table.row wire:key="job-{{ $job->id }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:icon :name="match($job->status->value) {
                                            'completed' => 'check',
                                            'failed' => 'x-mark',
                                            'running' => 'arrow-path',
                                            default => 'clock'
                                        }" class="size-4 {{ match($job->status->value) {
                                            'completed' => 'text-green-500',
                                            'failed' => 'text-red-500',
                                            'running' => 'text-blue-500 animate-spin',
                                            default => 'text-zinc-400'
                                        } }}" />
                                        <span>
                                            @if ($job->job_type->value === 'discovery')
                                                {{ __('Page') }} {{ $job->current_page ?? 1 }}
                                            @else
                                                {{ __('Listing') }} #{{ $job->discovered_listing_id }}
                                            @endif
                                        </span>
                                        <flux:badge size="sm" :color="$job->job_type->value === 'discovery' ? 'blue' : 'amber'">
                                            {{ $job->job_type->value === 'discovery' ? __('Discovery') : __('Scrape') }}
                                        </flux:badge>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($job->status->value === 'completed' && $job->result)
                                        @if (isset($job->result['listings_found']))
                                            {{ __('Found') }} {{ $job->result['listings_found'] }} {{ __('listings') }}
                                        @elseif (isset($job->result['listing_id']))
                                            {{ __('Created') }} #{{ $job->result['listing_id'] }}
                                        @endif
                                    @elseif ($job->status->value === 'failed')
                                        <span class="text-red-500">{{ Str::limit($job->error_message, 40) }}</span>
                                    @elseif ($job->status->value === 'running')
                                        {{ __('Processing...') }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right text-zinc-500">
                                    @if ($job->completed_at && $job->started_at)
                                        @php
                                            $seconds = $job->started_at->diffInSeconds($job->completed_at);
                                            $duration = $seconds >= 60 ? floor($seconds / 60) . 'm ' . ($seconds % 60) . 's' : $seconds . 's';
                                        @endphp
                                        {{ $duration }}
                                    @elseif ($job->started_at)
                                        <span class="animate-pulse">{{ __('running') }}</span>
                                    @else
                                        -
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        {{-- Failed Jobs --}}
        <flux:card>
            <div class="flex justify-between items-center mb-4">
                <flux:heading size="lg">{{ __('Failed Jobs') }}</flux:heading>
                @if ($this->failedJobsCount > 0)
                    <flux:button size="sm" variant="ghost" wire:click="retryAllFailed">{{ __('Retry All') }}</flux:button>
                @endif
            </div>

            @if ($this->failedJobsCount === 0)
                <div class="flex flex-col items-center py-8">
                    <flux:icon name="check" class="size-8 text-green-500" />
                    <flux:subheading class="mt-3">{{ __('No failures') }}</flux:subheading>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($this->failedJobs as $job)
                        <div wire:key="failed-{{ $job->id }}" class="flex items-center justify-between rounded-lg bg-red-50 dark:bg-red-900/20 p-3">
                            <div class="min-w-0 flex-1">
                                <flux:heading size="sm" class="text-red-700 dark:text-red-400">
                                    {{ $job->job_type->value === 'discovery' ? __('Page') . ' ' . ($job->current_page ?? 1) : __('Listing') . ' #' . $job->discovered_listing_id }}
                                </flux:heading>
                                <flux:subheading class="truncate text-red-600 dark:text-red-500">{{ Str::limit($job->error_message, 40) }}</flux:subheading>
                            </div>
                            <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="retryJob({{ $job->id }})" />
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</div>
