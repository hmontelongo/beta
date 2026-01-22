<div class="space-y-6" wire:poll.5s="refreshStats">
    {{-- Header --}}
    <div class="flex justify-between items-start">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.platforms.index')" wire:navigate aria-label="{{ __('Back to platforms') }}" />
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="xl">{{ $platform->name }}</flux:heading>
                    <flux:badge :color="$platform->is_active ? 'green' : 'zinc'" size="sm">
                        {{ $platform->is_active ? __('Active') : __('Inactive') }}
                    </flux:badge>
                </div>
                <flux:subheading>{{ $platform->base_url }}</flux:subheading>
            </div>
        </div>
        <div class="flex gap-2">
            <flux:button variant="ghost" icon="clock" wire:click="runScheduledNow" wire:confirm="{{ __('Run all enabled scheduled scrapes now?') }}" wire:loading.attr="disabled" wire:target="runScheduledNow">
                <span wire:loading.remove wire:target="runScheduledNow">{{ __('Run Scheduled') }}</span>
                <span wire:loading wire:target="runScheduledNow">{{ __('Running...') }}</span>
            </flux:button>
            <flux:button variant="ghost" icon="document-text" :href="route('admin.listings.index', ['platform' => $platform->id])" wire:navigate>
                {{ __('View Listings') }}
            </flux:button>
            <flux:button icon="plus" wire:click="$set('showAddQueryModal', true)" wire:loading.attr="disabled">
                {{ __('Add Query') }}
            </flux:button>
        </div>
    </div>

    {{-- Stats --}}
    <div class="flex gap-6">
        @foreach ([
            ['label' => __('Discovered'), 'value' => $this->stats['discovered'], 'icon' => 'magnifying-glass'],
            ['label' => __('Pending'), 'value' => $this->stats['pending'], 'icon' => 'clock'],
            ['label' => __('Scraped'), 'value' => $this->stats['scraped'], 'icon' => 'check-circle'],
        ] as $stat)
            <flux:card class="flex-1">
                <flux:subheading>{{ $stat['label'] }}</flux:subheading>
                <flux:heading size="xl">{{ number_format($stat['value']) }}</flux:heading>
            </flux:card>
        @endforeach
    </div>

    {{-- Main Grid --}}
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Search Queries --}}
        <flux:card class="lg:col-span-2">
            <div class="flex justify-between items-center mb-4">
                <flux:heading size="lg">{{ __('Search Queries') }}</flux:heading>
                <flux:subheading>{{ $this->searchQueries->count() }} {{ __('total') }}</flux:subheading>
            </div>

            @if ($this->searchQueries->isEmpty())
                <div class="flex flex-col items-center py-12">
                    <flux:icon name="magnifying-glass" class="size-12 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading size="sm" class="mt-4">{{ __('No search queries') }}</flux:heading>
                    <flux:subheading class="mt-1">{{ __('Add a search query to start scraping.') }}</flux:subheading>
                    <flux:button class="mt-4" icon="plus" wire:click="$set('showAddQueryModal', true)">
                        {{ __('Add Query') }}
                    </flux:button>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($this->searchQueries as $query)
                        <div wire:key="query-{{ $query->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                            <div class="flex justify-between items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <flux:heading size="sm">{{ $query->name }}</flux:heading>
                                        @if ($query->auto_enabled)
                                            <flux:badge size="sm" color="blue" class="gap-1">
                                                <flux:icon name="arrow-path" variant="micro" />
                                                {{ $query->getScheduleDescription() }}
                                            </flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc">{{ __('Manual') }}</flux:badge>
                                        @endif
                                    </div>
                                    <flux:subheading class="truncate mt-1">{{ $query->search_url }}</flux:subheading>
                                </div>
                                <div class="flex items-center gap-1">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="calendar"
                                        wire:click="openScheduleModal({{ $query->id }})"
                                        aria-label="{{ __('Edit schedule') }}"
                                    />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteSearchQuery({{ $query->id }})" wire:confirm="{{ __('Are you sure?') }}" aria-label="{{ __('Delete search query') }}" />
                                </div>
                            </div>

                            {{-- Stats Row --}}
                            <div class="mt-3 flex items-center gap-4 text-sm">
                                <flux:tooltip content="{{ __('Total runs') }}">
                                    <div class="flex items-center gap-1 text-zinc-500">
                                        <flux:icon name="play-circle" variant="micro" />
                                        <span>{{ $query->scrape_runs_count }}</span>
                                    </div>
                                </flux:tooltip>
                                <flux:tooltip content="{{ __('Total listings scraped') }}">
                                    <div class="flex items-center gap-1 text-zinc-500">
                                        <flux:icon name="document-text" variant="micro" />
                                        <span>{{ number_format($query->total_listings_scraped ?? 0) }}</span>
                                    </div>
                                </flux:tooltip>
                                @if ($query->auto_enabled && $query->next_run_at)
                                    <flux:tooltip content="{{ __('Next scheduled run') }}">
                                        <div class="flex items-center gap-1 text-zinc-500">
                                            <flux:icon name="clock" variant="micro" />
                                            <span>{{ $query->next_run_at->diffForHumans() }}</span>
                                        </div>
                                    </flux:tooltip>
                                @endif
                            </div>

                            @if ($query->activeRun)
                                <a href="{{ route('admin.runs.show', $query->activeRun) }}" wire:navigate class="mt-3 block rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                    <div class="flex justify-between items-center">
                                        <flux:badge :color="$query->activeRun->status->color()" size="sm">
                                            <span class="mr-1 size-2 animate-pulse rounded-full bg-current inline-block"></span>
                                            {{ ucfirst($query->activeRun->status->value) }}
                                        </flux:badge>
                                        <flux:subheading>
                                            {{ $query->activeRun->stats['pages_done'] ?? 0 }}/{{ $query->activeRun->stats['pages_total'] ?? 0 }} {{ __('pages') }}
                                        </flux:subheading>
                                    </div>
                                    <div class="mt-2 h-1.5 rounded-full bg-blue-200 dark:bg-blue-800">
                                        <div class="h-1.5 rounded-full bg-blue-500 transition-all" style="width: {{ $query->activeRun->progress }}%"></div>
                                    </div>
                                </a>
                            @else
                                <div class="mt-3 flex justify-between items-center gap-3">
                                    @if ($query->latestRun)
                                        <a href="{{ route('admin.runs.show', $query->latestRun) }}" wire:navigate class="group flex items-center gap-2 min-w-0">
                                            <flux:icon :name="$query->latestRun->status->icon()" class="size-4 shrink-0 {{ $query->latestRun->status->iconClass() }}" />
                                            <div class="min-w-0">
                                                <flux:text size="sm" class="group-hover:text-zinc-700 dark:group-hover:text-zinc-300 truncate">
                                                    {{ $query->latestRun->created_at->diffForHumans() }}
                                                    @if ($query->latestRun->status->value === 'completed')
                                                        <span class="text-green-600 dark:text-green-400"> - {{ $query->latestRun->stats['listings_scraped'] ?? 0 }} {{ __('scraped') }}</span>
                                                    @elseif ($query->latestRun->status->value === 'failed')
                                                        <span class="text-red-500"> - {{ __('failed') }}</span>
                                                    @endif
                                                </flux:text>
                                            </div>
                                        </a>
                                    @else
                                        <flux:subheading>{{ __('Never run') }}</flux:subheading>
                                    @endif
                                    <flux:button size="sm" variant="primary" icon="play" wire:click="startScrape({{ $query->id }})" wire:loading.attr="disabled" wire:target="startScrape({{ $query->id }})">
                                        <span wire:loading.remove wire:target="startScrape({{ $query->id }})">{{ __('Start') }}</span>
                                        <span wire:loading wire:target="startScrape({{ $query->id }})">{{ __('Starting...') }}</span>
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        {{-- Recent Runs --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Recent Runs') }}</flux:heading>

            @if ($this->recentRuns->isEmpty())
                <div class="flex flex-col items-center py-8">
                    <flux:icon name="clock" class="size-8 text-zinc-300 dark:text-zinc-600" />
                    <flux:subheading class="mt-3">{{ __('No runs yet') }}</flux:subheading>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($this->recentRuns as $run)
                        <a wire:key="run-{{ $run->id }}" href="{{ route('admin.runs.show', $run) }}" wire:navigate class="block rounded-lg border border-zinc-100 dark:border-zinc-700/50 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5">
                                    <flux:icon :name="$run->status->icon()" class="size-5 {{ $run->status->iconClass() }}" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <flux:heading size="sm" class="truncate">{{ $run->searchQuery?->name ?? __('Unknown') }}</flux:heading>
                                        <flux:badge size="sm" :color="$run->status->color()">{{ ucfirst($run->status->value) }}</flux:badge>
                                    </div>
                                    <flux:subheading class="mt-0.5">{{ $run->created_at->diffForHumans() }}</flux:subheading>

                                    {{-- Run Stats --}}
                                    @if ($run->stats && ($run->status->value === 'completed' || $run->status->value === 'failed'))
                                        <div class="mt-2 flex items-center gap-3 text-xs text-zinc-500">
                                            @if (isset($run->stats['listings_found']))
                                                <span class="flex items-center gap-1">
                                                    <flux:icon name="magnifying-glass" variant="micro" class="size-3" />
                                                    {{ $run->stats['listings_found'] }} {{ __('found') }}
                                                </span>
                                            @endif
                                            @if (isset($run->stats['listings_scraped']))
                                                <span class="flex items-center gap-1 text-green-600 dark:text-green-400">
                                                    <flux:icon name="check" variant="micro" class="size-3" />
                                                    {{ $run->stats['listings_scraped'] }} {{ __('scraped') }}
                                                </span>
                                            @endif
                                            @if (isset($run->stats['listings_failed']) && $run->stats['listings_failed'] > 0)
                                                <span class="flex items-center gap-1 text-red-500">
                                                    <flux:icon name="x-mark" variant="micro" class="size-3" />
                                                    {{ $run->stats['listings_failed'] }} {{ __('failed') }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Progress for active runs --}}
                                    @if (in_array($run->status->value, ['pending', 'discovering', 'scraping']))
                                        <div class="mt-2">
                                            <div class="flex items-center justify-between text-xs text-zinc-500 mb-1">
                                                <span>{{ $run->stats['pages_done'] ?? 0 }}/{{ $run->stats['pages_total'] ?? 0 }} {{ __('pages') }}</span>
                                                <span>{{ $run->progress }}%</span>
                                            </div>
                                            <div class="h-1 rounded-full bg-zinc-200 dark:bg-zinc-700">
                                                <div class="h-1 rounded-full bg-blue-500 transition-all" style="width: {{ $run->progress }}%"></div>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Duration for completed runs --}}
                                    @if ($run->completed_at && $run->started_at)
                                        <div class="mt-1 text-xs text-zinc-400">
                                            {{ __('Duration') }}: {{ $run->started_at->diffForHumans($run->completed_at, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]) }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Add Query Modal --}}
    <flux:modal wire:model="showAddQueryModal" class="md:w-96">
        <flux:heading size="lg">{{ __('Add Search Query') }}</flux:heading>
        <flux:subheading class="mb-6">{{ __('Enter a name and the search URL you want to scrape.') }}</flux:subheading>

        <form wire:submit="addSearchQuery" class="space-y-4">
            <flux:input wire:model="queryName" label="{{ __('Name') }}" placeholder="Rentals in Jalisco" required />
            <flux:input wire:model="queryUrl" label="{{ __('Search URL') }}" type="url" placeholder="https://www.inmuebles24.com/..." required />

            <div class="flex items-center justify-end gap-3 pt-4">
                <flux:button variant="ghost" wire:click="$set('showAddQueryModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="addSearchQuery">
                    <span wire:loading.remove wire:target="addSearchQuery">{{ __('Add Query') }}</span>
                    <span wire:loading wire:target="addSearchQuery">{{ __('Adding...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Schedule Modal --}}
    <flux:modal wire:model="showScheduleModal" class="md:w-[420px]">
        <flux:heading size="lg">{{ __('Schedule') }}</flux:heading>
        <flux:subheading class="mb-6">{{ $this->scheduleQuery?->name }}</flux:subheading>

        <div class="space-y-5">
            {{-- Schedule Type --}}
            <flux:select wire:model.live="scheduleType" label="{{ __('Frequency') }}">
                <option value="none">{{ __('Manual Only') }}</option>
                <option value="interval">{{ __('Run every...') }}</option>
                <option value="daily">{{ __('Daily at specific time') }}</option>
                <option value="weekly">{{ __('Weekly') }}</option>
            </flux:select>

            {{-- Interval Options --}}
            @if ($scheduleType === 'interval')
                <div>
                    <flux:label class="mb-2">{{ __('Run every') }}</flux:label>
                    <div class="flex items-center gap-3">
                        <flux:input
                            type="number"
                            wire:model.live="intervalValue"
                            min="1"
                            step="1"
                            pattern="[0-9]*"
                            inputmode="numeric"
                            class="w-20"
                        />
                        <div class="flex gap-1">
                            @foreach (['minutes' => __('Min'), 'hours' => __('Hours'), 'days' => __('Days')] as $unit => $label)
                                <flux:button
                                    size="sm"
                                    :variant="$intervalUnit === $unit ? 'primary' : 'ghost'"
                                    wire:click="$set('intervalUnit', '{{ $unit }}')"
                                >
                                    {{ $label }}
                                </flux:button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Daily Options --}}
            @if ($scheduleType === 'daily')
                <div>
                    <flux:label class="mb-2">{{ __('Run at') }}</flux:label>
                    <flux:select wire:model.live="scheduledTime">
                        @foreach ($this->getTimeOptions() as $time => $label)
                            <option value="{{ $time }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            {{-- Weekly Options --}}
            @if ($scheduleType === 'weekly')
                <div>
                    <flux:label class="mb-2">{{ __('Day of week') }}</flux:label>
                    <div class="flex gap-1">
                        @foreach ($this->getDayOptions() as $day => $label)
                            <flux:button
                                size="sm"
                                :variant="$scheduledDay == $day ? 'primary' : 'ghost'"
                                wire:click="$set('scheduledDay', {{ $day }})"
                                class="flex-1"
                            >
                                {{ $label }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <flux:label class="mb-2">{{ __('Time') }}</flux:label>
                    <flux:select wire:model.live="scheduledTime">
                        @foreach ($this->getTimeOptions() as $time => $label)
                            <option value="{{ $time }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            {{-- Next Run Preview --}}
            @if ($scheduleType !== 'none' && $this->nextRunPreview)
                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-3">
                    <div class="flex items-center gap-2">
                        <flux:icon name="clock" class="size-4 text-blue-500" />
                        <flux:text size="sm" class="text-blue-600 dark:text-blue-400 font-medium">{{ __('Next run') }}</flux:text>
                    </div>
                    <flux:text class="mt-1 text-blue-700 dark:text-blue-300">{{ $this->nextRunPreview }}</flux:text>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-3 pt-6">
            <flux:button variant="ghost" wire:click="$set('showScheduleModal', false)">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button variant="primary" wire:click="saveSchedule" wire:loading.attr="disabled" wire:target="saveSchedule">
                <span wire:loading.remove wire:target="saveSchedule">{{ __('Save') }}</span>
                <span wire:loading wire:target="saveSchedule">{{ __('Saving...') }}</span>
            </flux:button>
        </div>
    </flux:modal>
</div>
