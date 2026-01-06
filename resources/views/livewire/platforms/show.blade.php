<div class="space-y-6" wire:poll.5s="refreshStats">
    {{-- Header --}}
    <div class="flex items-start gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('platforms.index')" wire:navigate />
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ $platform->name }}</flux:heading>
                @if ($platform->is_active)
                    <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                @endif
            </div>
            <flux:text class="mt-1">{{ $platform->base_url }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="document-text" :href="route('listings.index', ['platform' => $platform->id])" wire:navigate>
                {{ __('View Listings') }}
            </flux:button>
            <flux:button icon="plus" wire:click="$set('showAddQueryModal', true)">
                {{ __('Add Query') }}
            </flux:button>
        </div>
    </div>

    {{-- Stats Cards Row --}}
    <div class="flex gap-6">
        <flux:card class="flex-1">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon name="magnifying-glass" class="size-5 text-zinc-600 dark:text-zinc-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Discovered') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($this->stats['discovered']) }}</flux:heading>
                </div>
            </div>
        </flux:card>
        <flux:card class="flex-1">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <flux:icon name="clock" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Pending') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($this->stats['pending']) }}</flux:heading>
                </div>
            </div>
        </flux:card>
        <flux:card class="flex-1">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                    <flux:icon name="check-circle" class="size-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Scraped') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($this->stats['scraped']) }}</flux:heading>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Main Grid --}}
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Search Queries (2 cols) --}}
        <flux:card class="lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Search Queries') }}</flux:heading>
                <flux:text size="sm" class="text-zinc-500">{{ $this->searchQueries->count() }} {{ __('total') }}</flux:text>
            </div>

            @if ($this->searchQueries->isEmpty())
                <div class="mt-6 flex flex-col items-center justify-center py-12">
                    <flux:icon name="magnifying-glass" class="size-12 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading size="sm" class="mt-4">{{ __('No search queries') }}</flux:heading>
                    <flux:text size="sm" class="mt-1 text-center">
                        {{ __('Add a search query to start scraping listings.') }}
                    </flux:text>
                    <flux:button class="mt-4" icon="plus" wire:click="$set('showAddQueryModal', true)">
                        {{ __('Add Query') }}
                    </flux:button>
                </div>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($this->searchQueries as $query)
                        @php
                            $activeRun = $query->scrapeRuns()
                                ->whereIn('status', [
                                    \App\Enums\ScrapeRunStatus::Pending,
                                    \App\Enums\ScrapeRunStatus::Discovering,
                                    \App\Enums\ScrapeRunStatus::Scraping,
                                ])
                                ->first();
                            $lastRun = $query->scrapeRuns()->latest()->first();
                        @endphp
                        <div wire:key="query-{{ $query->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <flux:heading size="sm">{{ $query->name }}</flux:heading>
                                    <flux:text size="xs" class="mt-1 truncate text-zinc-500">{{ $query->search_url }}</flux:text>
                                </div>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteSearchQuery({{ $query->id }})"
                                    wire:confirm="{{ __('Are you sure?') }}"
                                />
                            </div>

                            @if ($activeRun)
                                {{-- Active Run - Show progress and link to details --}}
                                <a href="{{ route('runs.show', $activeRun) }}" wire:navigate class="mt-3 block rounded-lg border border-blue-200 bg-blue-50 p-3 transition-colors hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/20 dark:hover:bg-blue-900/30">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="size-2 animate-pulse rounded-full bg-blue-500"></span>
                                            <flux:text size="sm" class="font-medium text-blue-700 dark:text-blue-400">
                                                {{ $activeRun->status->value === 'discovering' ? __('Discovering') : __('Scraping') }}
                                            </flux:text>
                                        </div>
                                        <flux:text size="xs" class="text-blue-600 dark:text-blue-400">
                                            @if ($activeRun->stats)
                                                {{ $activeRun->stats['pages_done'] ?? 0 }}/{{ $activeRun->stats['pages_total'] ?? '?' }} {{ __('pages') }}
                                            @endif
                                        </flux:text>
                                    </div>
                                    <div class="mt-2 h-1.5 w-full rounded-full bg-blue-200 dark:bg-blue-800">
                                        @php
                                            $progress = 0;
                                            if ($activeRun->stats && ($activeRun->stats['pages_total'] ?? 0) > 0) {
                                                $progress = round(($activeRun->stats['pages_done'] ?? 0) / $activeRun->stats['pages_total'] * 100);
                                            }
                                        @endphp
                                        <div class="h-1.5 rounded-full bg-blue-500 transition-all" style="width: {{ $progress }}%"></div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-center gap-1 text-blue-600 dark:text-blue-400">
                                        <flux:text size="sm">{{ __('View Progress') }}</flux:text>
                                        <flux:icon name="arrow-right" class="size-4" />
                                    </div>
                                </a>
                            @else
                                {{-- No active run - Show last run info and start button --}}
                                <div class="mt-3 flex items-center justify-between">
                                    <div>
                                        @if ($lastRun)
                                            <flux:text size="xs" class="text-zinc-500">
                                                {{ __('Last run') }}: {{ $lastRun->created_at->diffForHumans() }}
                                                @if ($lastRun->status === \App\Enums\ScrapeRunStatus::Completed)
                                                    <span class="text-green-600">({{ $lastRun->stats['listings_scraped'] ?? 0 }} {{ __('scraped') }})</span>
                                                @elseif ($lastRun->status === \App\Enums\ScrapeRunStatus::Failed)
                                                    <span class="text-red-500">({{ __('failed') }})</span>
                                                @endif
                                            </flux:text>
                                        @else
                                            <flux:text size="xs" class="text-zinc-500">{{ __('Never run') }}</flux:text>
                                        @endif
                                    </div>
                                    <flux:button size="sm" variant="primary" icon="play" wire:click="startScrape({{ $query->id }})">
                                        {{ __('Start') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        {{-- Recent Runs (1 col) --}}
        <flux:card>
            <flux:heading size="lg">{{ __('Recent Runs') }}</flux:heading>

            @if ($this->recentRuns->isEmpty())
                <div class="mt-6 flex flex-col items-center justify-center py-8">
                    <div class="rounded-full bg-zinc-100 p-3 dark:bg-zinc-700">
                        <flux:icon name="clock" class="size-6 text-zinc-400" />
                    </div>
                    <flux:text class="mt-3 text-zinc-500">{{ __('No runs yet') }}</flux:text>
                    <flux:text size="sm" class="mt-1 text-zinc-400">{{ __('Start a scrape to see runs here') }}</flux:text>
                </div>
            @else
                <div class="mt-4 space-y-2">
                    @foreach ($this->recentRuns as $run)
                        <a
                            wire:key="run-{{ $run->id }}"
                            href="{{ route('runs.show', $run) }}"
                            wire:navigate
                            class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                        >
                            {{-- Status Icon --}}
                            @switch($run->status->value)
                                @case('pending')
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                                        <flux:icon name="clock" class="size-4 text-zinc-400" />
                                    </div>
                                    @break
                                @case('discovering')
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                                        <flux:icon name="magnifying-glass" class="size-4 animate-pulse text-blue-600 dark:text-blue-400" />
                                    </div>
                                    @break
                                @case('scraping')
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                                        <flux:icon name="arrow-path" class="size-4 animate-spin text-amber-600 dark:text-amber-400" />
                                    </div>
                                    @break
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
                            @endswitch

                            {{-- Run Info --}}
                            <div class="min-w-0 flex-1">
                                <flux:text size="sm" class="truncate font-medium">{{ $run->searchQuery?->name ?? __('Unknown') }}</flux:text>
                                <flux:text size="xs" class="text-zinc-400">{{ $run->created_at->diffForHumans() }}</flux:text>
                            </div>

                            {{-- Status Badge --}}
                            @switch($run->status->value)
                                @case('discovering')
                                    <flux:badge color="blue" size="sm">{{ __('Discovering') }}</flux:badge>
                                    @break
                                @case('scraping')
                                    <flux:badge color="amber" size="sm">{{ __('Scraping') }}</flux:badge>
                                    @break
                                @case('completed')
                                    <flux:badge color="green" size="sm">{{ __('Completed') }}</flux:badge>
                                    @break
                                @case('failed')
                                    <flux:badge color="red" size="sm">{{ __('Failed') }}</flux:badge>
                                    @break
                            @endswitch
                        </a>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Add Query Modal --}}
    <flux:modal wire:model="showAddQueryModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Search Query') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Enter a name and the search URL you want to scrape.') }}</flux:text>
            </div>

            <form wire:submit="addSearchQuery" class="space-y-4">
                <flux:input
                    wire:model="queryName"
                    label="{{ __('Name') }}"
                    description="{{ __('A descriptive name for this search query.') }}"
                    placeholder="Rentals in Jalisco"
                    required
                />
                <flux:input
                    wire:model="queryUrl"
                    label="{{ __('Search URL') }}"
                    description="{{ __('The full URL of the search results page.') }}"
                    type="url"
                    placeholder="https://www.inmuebles24.com/..."
                    required
                />

                <div class="flex justify-end gap-3 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showAddQueryModal', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Add Query') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
