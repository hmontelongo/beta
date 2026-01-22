{{-- Always poll: fast when processing, slow when idle --}}
<div class="space-y-6"
    @if($this->isProcessing)
        wire:poll.2s
    @else
        wire:poll.10s="checkForChanges"
    @endif
>
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Scraped Listings') }}</flux:heading>
            <flux:subheading>{{ __('Browse all scraped property listings from all platforms.') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            {{-- Dedup Button: Normal or Cancel state --}}
            @if ($this->isDeduplicationProcessing)
                <flux:button
                    wire:click="cancelDeduplication"
                    wire:loading.attr="disabled"
                    variant="danger"
                    size="sm"
                    icon="x-mark"
                >
                    <span wire:loading.remove wire:target="cancelDeduplication">
                        {{ __('Cancel Dedup') }} ({{ $this->stats['dedup_queued'] + $this->stats['dedup_processing'] }})
                    </span>
                    <span wire:loading wire:target="cancelDeduplication">{{ __('Cancelling...') }}</span>
                </flux:button>
            @else
                <flux:button
                    wire:click="runBatchDeduplication"
                    wire:loading.attr="disabled"
                    :disabled="$this->stats['dedup_pending'] === 0"
                    variant="primary"
                    size="sm"
                    icon="document-duplicate"
                >
                    <span wire:loading.remove wire:target="runBatchDeduplication">
                        {{ __('Dedup Batch') }} ({{ $this->stats['dedup_pending'] }})
                    </span>
                    <span wire:loading wire:target="runBatchDeduplication">{{ __('Processing...') }}</span>
                </flux:button>
            @endif

            {{-- Property Creation: Cancel state --}}
            @if ($this->isPropertyCreationProcessing)
                <flux:button
                    wire:click="cancelPropertyCreation"
                    wire:loading.attr="disabled"
                    variant="danger"
                    size="sm"
                    icon="x-mark"
                >
                    <span wire:loading.remove wire:target="cancelPropertyCreation">
                        {{ __('Cancel AI') }} ({{ $this->stats['property_creation_queued'] + $this->stats['groups_processing_ai'] }})
                    </span>
                    <span wire:loading wire:target="cancelPropertyCreation">{{ __('Cancelling...') }}</span>
                </flux:button>
            @endif

            {{-- Review Groups link --}}
            @if ($this->stats['groups_pending_review'] > 0)
                <flux:button
                    :href="route('admin.dedup.review')"
                    wire:navigate
                    variant="filled"
                    size="sm"
                    icon="eye"
                >
                    {{ __('Review Groups') }} ({{ $this->stats['groups_pending_review'] }})
                </flux:button>
            @endif

            {{-- Retry Failed Dedup --}}
            @if ($this->stats['dedup_failed'] > 0)
                <flux:button
                    wire:click="retryFailedDedup"
                    wire:loading.attr="disabled"
                    :disabled="$this->isProcessing"
                    variant="filled"
                    size="sm"
                    icon="arrow-path"
                >
                    <span wire:loading.remove wire:target="retryFailedDedup">
                        {{ __('Retry Failed') }} ({{ $this->stats['dedup_failed'] }})
                    </span>
                    <span wire:loading wire:target="retryFailedDedup">{{ __('Queueing...') }}</span>
                </flux:button>
            @endif

            {{-- Reset Stuck Jobs --}}
            <flux:dropdown>
                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" aria-label="{{ __('More actions') }}" />
                <flux:menu>
                    <flux:menu.item wire:click="resetStuckJobs" icon="arrow-path">
                        {{ __('Reset Stuck Jobs') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    {{-- Pipeline Stats - 2 rows of 4 cards --}}
    <div class="space-y-3">
        {{-- Row 1: Early pipeline stages --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <flux:card
                class="p-3 cursor-pointer transition-colors {{ $pipelineStatus === 'awaiting_geocoding' ? 'ring-2 ring-zinc-400' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                wire:click="$set('pipelineStatus', '{{ $pipelineStatus === 'awaiting_geocoding' ? '' : 'awaiting_geocoding' }}')"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="map-pin" class="size-4 text-zinc-400" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('Awaiting Geo') }}</flux:text>
                </div>
                <flux:heading size="lg" class="text-zinc-600">{{ $this->pipelineStats['awaiting_geocoding'] }}</flux:heading>
            </flux:card>
            <flux:card
                class="p-3 cursor-pointer transition-colors {{ $pipelineStatus === 'awaiting_dedup' ? 'ring-2 ring-zinc-400' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                wire:click="$set('pipelineStatus', '{{ $pipelineStatus === 'awaiting_dedup' ? '' : 'awaiting_dedup' }}')"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="clock" class="size-4 text-zinc-400" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('Awaiting Dedup') }}</flux:text>
                </div>
                <flux:heading size="lg" class="text-zinc-600">{{ $this->pipelineStats['awaiting_dedup'] }}</flux:heading>
            </flux:card>
            <flux:card
                class="p-3 cursor-pointer transition-colors {{ $pipelineStatus === 'processing_dedup' ? 'ring-2 ring-blue-400' : '' }} {{ $this->pipelineStats['processing_dedup'] > 0 ? 'bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                wire:click="$set('pipelineStatus', '{{ $pipelineStatus === 'processing_dedup' ? '' : 'processing_dedup' }}')"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="arrow-path" class="size-4 {{ $this->pipelineStats['processing_dedup'] > 0 ? 'text-blue-500 animate-spin' : 'text-zinc-400' }}" />
                    <flux:text size="sm" class="{{ $this->pipelineStats['processing_dedup'] > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500' }}">{{ __('Processing') }}</flux:text>
                </div>
                <flux:heading size="lg" class="{{ $this->pipelineStats['processing_dedup'] > 0 ? 'text-blue-600' : 'text-zinc-600' }}">{{ $this->pipelineStats['processing_dedup'] }}</flux:heading>
            </flux:card>
            <flux:card
                class="p-3 cursor-pointer transition-colors {{ $pipelineStatus === 'needs_review' ? 'ring-2 ring-amber-400' : '' }} {{ $this->pipelineStats['needs_review'] > 0 ? 'bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/30' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                wire:click="$set('pipelineStatus', '{{ $pipelineStatus === 'needs_review' ? '' : 'needs_review' }}')"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="eye" class="size-4 {{ $this->pipelineStats['needs_review'] > 0 ? 'text-amber-500' : 'text-zinc-400' }}" />
                    <flux:text size="sm" class="{{ $this->pipelineStats['needs_review'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500' }}">{{ __('Needs Review') }}</flux:text>
                </div>
                <flux:heading size="lg" class="{{ $this->pipelineStats['needs_review'] > 0 ? 'text-amber-600' : 'text-zinc-600' }}">{{ $this->pipelineStats['needs_review'] }}</flux:heading>
            </flux:card>
        </div>

        {{-- Row 2: Later pipeline stages --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <flux:card
                class="p-3 cursor-pointer transition-colors {{ $pipelineStatus === 'queued_for_ai' ? 'ring-2 ring-purple-400' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                wire:click="$set('pipelineStatus', '{{ $pipelineStatus === 'queued_for_ai' ? '' : 'queued_for_ai' }}')"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="sparkles" class="size-4 text-purple-400" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('Queued for AI') }}</flux:text>
                </div>
                <flux:heading size="lg" class="text-purple-600">{{ $this->pipelineStats['queued_for_ai'] }}</flux:heading>
            </flux:card>
            <flux:card
                class="p-3 cursor-pointer transition-colors {{ $pipelineStatus === 'processing_ai' ? 'ring-2 ring-purple-400' : '' }} {{ $this->pipelineStats['processing_ai'] > 0 ? 'bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/30' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                wire:click="$set('pipelineStatus', '{{ $pipelineStatus === 'processing_ai' ? '' : 'processing_ai' }}')"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="cog-6-tooth" class="size-4 {{ $this->pipelineStats['processing_ai'] > 0 ? 'text-purple-500 animate-spin' : 'text-zinc-400' }}" />
                    <flux:text size="sm" class="{{ $this->pipelineStats['processing_ai'] > 0 ? 'text-purple-600 dark:text-purple-400' : 'text-zinc-500' }}">{{ __('AI Processing') }}</flux:text>
                </div>
                <flux:heading size="lg" class="{{ $this->pipelineStats['processing_ai'] > 0 ? 'text-purple-600' : 'text-zinc-600' }}">{{ $this->pipelineStats['processing_ai'] }}</flux:heading>
            </flux:card>
            <flux:card
                class="p-3 cursor-pointer transition-colors {{ $pipelineStatus === 'completed' ? 'ring-2 ring-green-400' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                wire:click="$set('pipelineStatus', '{{ $pipelineStatus === 'completed' ? '' : 'completed' }}')"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="check-circle" class="size-4 text-green-500" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('Completed') }}</flux:text>
                </div>
                <flux:heading size="lg" class="text-green-600">{{ $this->pipelineStats['completed'] }}</flux:heading>
            </flux:card>
            <flux:card
                class="p-3 cursor-pointer transition-colors {{ $pipelineStatus === 'failed' ? 'ring-2 ring-red-400' : '' }} {{ $this->pipelineStats['failed'] > 0 ? 'bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                wire:click="$set('pipelineStatus', '{{ $pipelineStatus === 'failed' ? '' : 'failed' }}')"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="x-circle" class="size-4 {{ $this->pipelineStats['failed'] > 0 ? 'text-red-500' : 'text-zinc-400' }}" />
                    <flux:text size="sm" class="{{ $this->pipelineStats['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}">{{ __('Failed') }}</flux:text>
                </div>
                <flux:heading size="lg" class="{{ $this->pipelineStats['failed'] > 0 ? 'text-red-600' : 'text-zinc-600' }}">{{ $this->pipelineStats['failed'] }}</flux:heading>
            </flux:card>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search by URL, ID, or title...') }}"
                clearable
            />
        </div>
        <div class="flex gap-2">
            <flux:select wire:model.live="platform" class="w-40">
                <flux:select.option value="">{{ __('All Platforms') }}</flux:select.option>
                @foreach ($platforms as $p)
                    <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="dedupStatus" class="w-40">
                <flux:select.option value="">{{ __('All Status') }}</flux:select.option>
                @foreach ($dedupStatuses as $status)
                    <flux:select.option value="{{ $status->value }}">{{ ucfirst(str_replace('_', ' ', $status->value)) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Bulk Actions Bar --}}
    @if (!empty($selected))
        <div class="flex items-center justify-between rounded-lg bg-blue-50 dark:bg-blue-900/20 px-4 py-3">
            <flux:text>
                <strong>{{ count($selected) }}</strong> {{ __('listings selected') }}
            </flux:text>
            <div class="flex gap-2">
                <flux:button
                    wire:click="runBulkDeduplication"
                    wire:loading.attr="disabled"
                    :disabled="$this->isProcessing"
                    size="sm"
                    icon="document-duplicate"
                >
                    <span wire:loading.remove wire:target="runBulkDeduplication">{{ __('Dedup Selected') }}</span>
                    <span wire:loading wire:target="runBulkDeduplication">{{ __('Processing...') }}</span>
                </flux:button>
                <flux:button
                    wire:click="$set('selected', [])"
                    size="sm"
                    variant="ghost"
                >
                    {{ __('Clear') }}
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Listings Table --}}
    @if ($listings->isEmpty())
        <flux:callout icon="document-text">
            <flux:callout.heading>{{ __('No listings found') }}</flux:callout.heading>
            <flux:callout.text>
                @if ($search || $platform || $dedupStatus)
                    {{ __('Try adjusting your search filters.') }}
                @else
                    {{ __('Start a scrape from a platform to collect listings.') }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column class="w-12">
                    <flux:checkbox wire:model.live="selectAll" />
                </flux:table.column>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Platform') }}</flux:table.column>
                <flux:table.column>{{ __('Price') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Scraped') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($listings as $listing)
                    <flux:table.row wire:key="listing-{{ $listing->id }}">
                        <flux:table.cell>
                            <flux:checkbox wire:model.live="selected" value="{{ $listing->id }}" />
                        </flux:table.cell>
                        <flux:table.cell class="max-w-xs">
                            <flux:heading size="sm" class="truncate">
                                {{ $listing->raw_data['title'] ?? 'Untitled' }}
                            </flux:heading>
                            <flux:text size="sm" class="truncate text-zinc-500">
                                {{ $listing->raw_data['colonia'] ?? $listing->raw_data['city'] ?? $listing->external_id }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $listing->platform->name }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $operations = $listing->raw_data['operations'] ?? [];
                            @endphp
                            @if (!empty($operations))
                                <div class="space-y-1">
                                    @foreach ($operations as $operation)
                                        <div class="flex items-center gap-2">
                                            <flux:badge size="sm" :color="$operation['type'] === 'rent' ? 'blue' : 'green'">
                                                {{ $operation['type'] === 'rent' ? 'R' : 'S' }}
                                            </flux:badge>
                                            <flux:text>
                                                ${{ number_format($operation['price'] ?? 0) }}
                                                <span class="text-zinc-400 text-xs">{{ $operation['currency'] ?? 'MXN' }}</span>
                                            </flux:text>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <flux:text class="text-zinc-400">—</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$listing->pipeline_status->color()" :icon="$listing->pipeline_status->icon()">
                                {{ $listing->pipeline_status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text size="sm">{{ $listing->scraped_at?->diffForHumans() }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="arrow-top-right-on-square"
                                    :href="$listing->original_url"
                                    target="_blank"
                                />
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="eye"
                                    :href="route('admin.listings.show', $listing)"
                                    wire:navigate
                                />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $listings->links() }}
        </div>
    @endif
</div>
