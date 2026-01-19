<div class="space-y-6" wire:poll.5s="refreshStats">
    {{-- Header --}}
    <div class="flex justify-between items-start">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('platforms.index')" wire:navigate />
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
            <flux:button variant="ghost" icon="document-text" :href="route('listings.index', ['platform' => $platform->id])" wire:navigate>
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
                <div class="space-y-3">
                    @foreach ($this->searchQueries as $query)
                        <div wire:key="query-{{ $query->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                            <div class="flex justify-between items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="sm">{{ $query->name }}</flux:heading>
                                        @if ($query->auto_enabled)
                                            <flux:badge size="sm" color="blue">{{ $query->getScheduleDescription() }}</flux:badge>
                                        @endif
                                    </div>
                                    <flux:subheading class="truncate">{{ $query->search_url }}</flux:subheading>
                                </div>
                                <div class="flex items-center gap-1">
                                    {{-- Schedule Button --}}
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="calendar"
                                        wire:click="openScheduleModal({{ $query->id }})"
                                    />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteSearchQuery({{ $query->id }})" wire:confirm="{{ __('Are you sure?') }}" />
                                </div>
                            </div>

                            {{-- Next Run Info --}}
                            @if ($query->auto_enabled && $query->next_run_at)
                                <div class="mt-2 flex items-center gap-2 text-sm text-zinc-500">
                                    <flux:icon name="clock" class="size-4" />
                                    <span>{{ __('Next run') }}: {{ $query->next_run_at->diffForHumans() }}</span>
                                </div>
                            @endif

                            @if ($query->activeRun)
                                <a href="{{ route('runs.show', $query->activeRun) }}" wire:navigate class="mt-3 block rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3 hover:bg-blue-100 dark:hover:bg-blue-900/30">
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
                                        <div class="h-1.5 rounded-full bg-blue-500" style="width: {{ $query->activeRun->progress }}%"></div>
                                    </div>
                                </a>
                            @else
                                <div class="mt-3 flex justify-between items-center">
                                    @if ($query->latestRun)
                                        <a href="{{ route('runs.show', $query->latestRun) }}" wire:navigate class="group flex items-center gap-1">
                                            <flux:subheading class="group-hover:text-zinc-700 dark:group-hover:text-zinc-300">
                                                {{ __('Last run') }}: {{ $query->latestRun->created_at->diffForHumans() }}
                                                @if ($query->latestRun->status->value === 'completed')
                                                    <span class="text-green-600">({{ $query->latestRun->stats['listings_scraped'] ?? 0 }} {{ __('scraped') }})</span>
                                                @elseif ($query->latestRun->status->value === 'failed')
                                                    <span class="text-red-500">({{ __('failed') }})</span>
                                                @endif
                                            </flux:subheading>
                                            <flux:icon name="arrow-top-right-on-square" variant="micro" class="text-zinc-400 group-hover:text-zinc-600" />
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
                <div class="space-y-1">
                    @foreach ($this->recentRuns as $run)
                        <a wire:key="run-{{ $run->id }}" href="{{ route('runs.show', $run) }}" wire:navigate class="flex items-center gap-3 rounded-lg p-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <flux:icon :name="$run->status->icon()" class="size-5 {{ $run->status->iconClass() }}" />
                            <div class="flex-1 min-w-0">
                                <flux:heading size="sm" class="truncate">{{ $run->searchQuery?->name ?? __('Unknown') }}</flux:heading>
                                <flux:subheading>{{ $run->created_at->diffForHumans() }}</flux:subheading>
                            </div>
                            <flux:badge size="sm" :color="$run->status->color()">{{ ucfirst($run->status->value) }}</flux:badge>
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
    <flux:modal wire:model="showScheduleModal" class="md:w-96">
        <flux:heading size="lg">{{ __('Schedule') }}</flux:heading>
        <flux:subheading class="mb-6">{{ $this->scheduleQuery?->name }}</flux:subheading>

        <div class="space-y-4">
            {{-- Schedule Type --}}
            <flux:select wire:model.live="scheduleType" label="{{ __('Frequency') }}">
                <option value="none">{{ __('Manual Only') }}</option>
                <option value="interval">{{ __('Run every...') }}</option>
                <option value="daily">{{ __('Daily at specific time') }}</option>
                <option value="weekly">{{ __('Weekly') }}</option>
            </flux:select>

            {{-- Interval Options --}}
            @if ($scheduleType === 'interval')
                <div class="flex gap-2">
                    <flux:input
                        type="number"
                        wire:model.live="intervalValue"
                        min="1"
                        step="1"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        class="w-24"
                    />
                    <flux:select wire:model.live="intervalUnit" class="flex-1">
                        <option value="minutes">{{ __('Minutes') }}</option>
                        <option value="hours">{{ __('Hours') }}</option>
                        <option value="days">{{ __('Days') }}</option>
                    </flux:select>
                </div>
            @endif

            {{-- Daily Options --}}
            @if ($scheduleType === 'daily')
                <flux:input
                    type="time"
                    wire:model.live="scheduledTime"
                    label="{{ __('Run at') }}"
                />
            @endif

            {{-- Weekly Options --}}
            @if ($scheduleType === 'weekly')
                <flux:select wire:model.live="scheduledDay" label="{{ __('Day') }}">
                    <option value="0">{{ __('Sunday') }}</option>
                    <option value="1">{{ __('Monday') }}</option>
                    <option value="2">{{ __('Tuesday') }}</option>
                    <option value="3">{{ __('Wednesday') }}</option>
                    <option value="4">{{ __('Thursday') }}</option>
                    <option value="5">{{ __('Friday') }}</option>
                    <option value="6">{{ __('Saturday') }}</option>
                </flux:select>
                <flux:input
                    type="time"
                    wire:model.live="scheduledTime"
                    label="{{ __('At') }}"
                />
            @endif

            {{-- Next Run Preview --}}
            @if ($scheduleType !== 'none' && $this->nextRunPreview)
                <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-3">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Next run') }}</flux:text>
                    <flux:text class="font-medium">{{ $this->nextRunPreview }}</flux:text>
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
