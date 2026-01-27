<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
            <flux:subheading>{{ __('API costs and pipeline overview.') }}</flux:subheading>
        </div>
        <flux:select wire:model.live="period" class="w-32">
            <flux:select.option value="7d">{{ __('Last 7 days') }}</flux:select.option>
            <flux:select.option value="30d">{{ __('Last 30 days') }}</flux:select.option>
            <flux:select.option value="90d">{{ __('Last 90 days') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- Cost Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {{-- Claude AI --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30">
                        <flux:icon name="sparkles" class="size-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Claude AI') }}</flux:text>
                </div>
            </div>
            <flux:heading size="xl" class="mt-3 text-purple-600 dark:text-purple-400">
                {{ $this->formatCost($this->costStats['claude_cost_cents']) }}
            </flux:heading>
            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500">
                <span>{{ number_format($this->costStats['claude_requests']) }} {{ __('requests') }}</span>
                <span class="text-zinc-300 dark:text-zinc-600">|</span>
                <span>{{ number_format($this->costStats['total_tokens']) }} {{ __('tokens') }}</span>
                @if ($this->costStats['claude_failed'] > 0)
                    <span class="text-zinc-300 dark:text-zinc-600">|</span>
                    <span class="text-red-500">{{ number_format($this->costStats['claude_failed']) }} {{ __('failed') }}</span>
                @endif
            </div>
        </flux:card>

        {{-- ZenRows --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                        <flux:icon name="globe-alt" class="size-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('ZenRows') }}</flux:text>
                </div>
            </div>
            <flux:heading size="xl" class="mt-3 text-blue-600 dark:text-blue-400">
                ~{{ $this->formatCost($this->costStats['zenrows_cost_cents']) }}
            </flux:heading>
            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500">
                <span>{{ number_format($this->costStats['zenrows_requests']) }} {{ __('requests') }}</span>
                @if ($this->costStats['zenrows_failed'] > 0)
                    <span class="text-zinc-300 dark:text-zinc-600">|</span>
                    <span class="text-red-500">{{ number_format($this->costStats['zenrows_failed']) }} {{ __('failed') }}</span>
                @endif
            </div>
        </flux:card>
    </div>

    {{-- Pipeline Stats --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <flux:card class="p-4">
            <div class="flex items-center gap-2">
                <flux:icon name="home" class="size-4 text-zinc-400" />
                <flux:text size="sm" class="text-zinc-500">{{ __('Properties') }}</flux:text>
            </div>
            <flux:heading size="lg" class="mt-1">{{ number_format($this->pipelineStats['properties_total']) }}</flux:heading>
            @if ($this->pipelineStats['properties_new'] > 0)
                <flux:badge size="sm" color="green" class="mt-1">+{{ $this->pipelineStats['properties_new'] }} {{ __('new') }}</flux:badge>
            @endif
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center gap-2">
                <flux:icon name="document-text" class="size-4 text-zinc-400" />
                <flux:text size="sm" class="text-zinc-500">{{ __('Listings') }}</flux:text>
            </div>
            <flux:heading size="lg" class="mt-1">{{ number_format($this->pipelineStats['listings_total']) }}</flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center gap-2">
                <flux:icon name="users" class="size-4 text-zinc-400" />
                <flux:text size="sm" class="text-zinc-500">{{ __('Publishers') }}</flux:text>
            </div>
            <flux:heading size="lg" class="mt-1">{{ number_format($this->pipelineStats['publishers_total']) }}</flux:heading>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center gap-2">
                <flux:icon name="queue-list" class="size-4 text-zinc-400" />
                <flux:text size="sm" class="text-zinc-500">{{ __('Queue') }}</flux:text>
            </div>
            <div class="mt-1 flex items-center gap-2">
                <flux:heading size="lg">{{ number_format($this->pipelineStats['pending_jobs']) }}</flux:heading>
                @if ($this->pipelineStats['failed_jobs'] > 0)
                    <flux:badge size="sm" color="red">{{ $this->pipelineStats['failed_jobs'] }} {{ __('failed') }}</flux:badge>
                @endif
            </div>
        </flux:card>
    </div>

    {{-- Recent Activity --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Recent Scrape Runs --}}
        <flux:card class="p-0">
            <div class="flex items-center gap-2 p-4 border-b border-zinc-100 dark:border-zinc-800">
                <flux:icon name="play-circle" class="size-5 text-zinc-400" />
                <flux:heading size="sm">{{ __('Recent Scrape Runs') }}</flux:heading>
            </div>
            @if ($this->recentRuns->isEmpty())
                <div class="p-4 text-center">
                    <flux:text class="text-zinc-500">{{ __('No scrape runs yet.') }}</flux:text>
                </div>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->recentRuns as $run)
                        <a
                            href="{{ route('admin.runs.show', $run) }}"
                            wire:navigate
                            class="flex items-center gap-3 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors"
                        >
                            <flux:icon :name="$run->status->icon()" class="size-5 {{ $run->status->iconClass() }}" />
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="font-medium truncate">{{ $run->searchQuery?->name ?? __('Unknown') }}</flux:text>
                                <flux:text size="xs" class="text-zinc-500">{{ $run->created_at->diffForHumans() }}</flux:text>
                            </div>
                            <flux:badge size="sm" :color="$run->status->color()">{{ ucfirst($run->status->value) }}</flux:badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </flux:card>

        {{-- Recent Properties --}}
        <flux:card class="p-0">
            <div class="flex items-center gap-2 p-4 border-b border-zinc-100 dark:border-zinc-800">
                <flux:icon name="home" class="size-5 text-zinc-400" />
                <flux:heading size="sm">{{ __('Recent Properties') }}</flux:heading>
            </div>
            @if ($this->recentProperties->isEmpty())
                <div class="p-4 text-center">
                    <flux:text class="text-zinc-500">{{ __('No properties created yet.') }}</flux:text>
                </div>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->recentProperties as $property)
                        <a
                            href="{{ route('admin.properties.show', $property) }}"
                            wire:navigate
                            class="flex items-center gap-3 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors"
                        >
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="font-medium truncate">{{ $property->address ?? __('Unknown Address') }}</flux:text>
                                <flux:text size="xs" class="text-zinc-500">{{ $property->colonia }}, {{ $property->city }}</flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($property->confidence_score)
                                    <flux:badge size="sm" :color="$property->confidence_score >= 80 ? 'green' : ($property->confidence_score >= 60 ? 'amber' : 'red')">
                                        {{ $property->confidence_score }}%
                                    </flux:badge>
                                @endif
                                <flux:badge size="sm" color="zinc">
                                    {{ $property->listings_count }} {{ __('listings') }}
                                </flux:badge>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</div>
