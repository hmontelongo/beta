<div class="space-y-6" @if($this->isProcessing) wire:poll.2s @endif>
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Scraped Listings') }}</flux:heading>
            <flux:subheading>{{ __('Browse all scraped property listings from all platforms.') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button
                wire:click="runBatchEnrichment"
                wire:loading.attr="disabled"
                :disabled="$this->isProcessing || $this->stats['ai_pending'] === 0"
                variant="primary"
                size="sm"
                icon="sparkles"
            >
                <span wire:loading.remove wire:target="runBatchEnrichment">
                    {{ __('Enrich Batch') }} ({{ $this->stats['ai_pending'] }})
                </span>
                <span wire:loading wire:target="runBatchEnrichment">{{ __('Processing...') }}</span>
            </flux:button>
            <flux:button
                wire:click="runBatchDeduplication"
                wire:loading.attr="disabled"
                :disabled="$this->isProcessing || $this->stats['dedup_pending'] === 0"
                variant="primary"
                size="sm"
                icon="document-duplicate"
            >
                <span wire:loading.remove wire:target="runBatchDeduplication">
                    {{ __('Dedup Batch') }} ({{ $this->stats['dedup_pending'] }})
                </span>
                <span wire:loading wire:target="runBatchDeduplication">{{ __('Processing...') }}</span>
            </flux:button>
        </div>
    </div>

    {{-- Stats Bar --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-6">
        <flux:card class="p-4">
            <flux:text size="sm" class="text-zinc-500">{{ __('Total') }}</flux:text>
            <flux:heading size="lg">{{ $listings->total() }}</flux:heading>
        </flux:card>
        <flux:card class="p-4 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:click="$set('aiStatus', 'pending')">
            <flux:text size="sm" class="text-zinc-500">{{ __('AI Pending') }}</flux:text>
            <flux:heading size="lg" class="text-zinc-600">{{ $this->stats['ai_pending'] }}</flux:heading>
        </flux:card>
        @if ($this->stats['ai_processing'] > 0 || $this->stats['dedup_processing'] > 0)
            <flux:card class="p-4 bg-blue-50 dark:bg-blue-900/20">
                <flux:text size="sm" class="text-blue-600 dark:text-blue-400">{{ __('Processing') }}</flux:text>
                <flux:heading size="lg" class="text-blue-600">
                    {{ $this->stats['ai_processing'] + $this->stats['dedup_processing'] }}
                </flux:heading>
            </flux:card>
        @endif
        <flux:card class="p-4 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:click="$set('aiStatus', 'completed')">
            <flux:text size="sm" class="text-zinc-500">{{ __('AI Done') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->stats['ai_completed'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:click="$set('dedupStatus', 'matched')">
            <flux:text size="sm" class="text-zinc-500">{{ __('Matched') }}</flux:text>
            <flux:heading size="lg" class="text-purple-600">{{ $this->stats['dedup_matched'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:click="$set('dedupStatus', 'needs_review')">
            <flux:text size="sm" class="text-zinc-500">{{ __('Needs Review') }}</flux:text>
            <flux:heading size="lg" class="text-amber-600">{{ $this->stats['dedup_needs_review'] }}</flux:heading>
        </flux:card>
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
            <flux:select wire:model.live="aiStatus" class="w-36">
                <flux:select.option value="">{{ __('All AI Status') }}</flux:select.option>
                @foreach ($aiStatuses as $status)
                    <flux:select.option value="{{ $status->value }}">{{ ucfirst($status->value) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="dedupStatus" class="w-40">
                <flux:select.option value="">{{ __('All Dedup') }}</flux:select.option>
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
                    wire:click="runBulkEnrichment"
                    wire:loading.attr="disabled"
                    :disabled="$this->isProcessing"
                    size="sm"
                    icon="sparkles"
                >
                    <span wire:loading.remove wire:target="runBulkEnrichment">{{ __('Enrich Selected') }}</span>
                    <span wire:loading wire:target="runBulkEnrichment">{{ __('Processing...') }}</span>
                </flux:button>
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
                @if ($search || $platform || $aiStatus || $dedupStatus)
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
                            <div class="flex flex-col gap-1">
                                <flux:badge size="sm" :color="$listing->ai_status->color()" :icon="$listing->ai_status->icon()">
                                    {{ ucfirst($listing->ai_status->value) }}
                                </flux:badge>
                                <flux:badge size="sm" :color="$listing->dedup_status->color()" :icon="$listing->dedup_status->icon()">
                                    {{ ucfirst(str_replace('_', ' ', $listing->dedup_status->value)) }}
                                </flux:badge>
                            </div>
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
                                    :href="route('listings.show', $listing)"
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
