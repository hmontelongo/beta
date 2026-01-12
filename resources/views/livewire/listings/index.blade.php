<div class="space-y-6">
    {{-- Page Header --}}
    <div>
        <flux:heading size="xl" level="1">{{ __('Scraped Listings') }}</flux:heading>
        <flux:subheading>{{ __('Browse all scraped property listings from all platforms.') }}</flux:subheading>
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
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="platform" placeholder="{{ __('All Platforms') }}">
                <flux:select.option value="">{{ __('All Platforms') }}</flux:select.option>
                @foreach ($platforms as $p)
                    <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Stats --}}
    <div class="flex gap-6">
        <div>
            <flux:text size="sm">{{ __('Total Listings') }}</flux:text>
            <flux:heading size="lg">{{ $listings->total() }}</flux:heading>
        </div>
    </div>

    {{-- Listings Table --}}
    @if ($listings->isEmpty())
        <flux:callout icon="document-text">
            <flux:callout.heading>{{ __('No listings found') }}</flux:callout.heading>
            <flux:callout.text>
                @if ($search || $platform)
                    {{ __('Try adjusting your search filters.') }}
                @else
                    {{ __('Start a scrape from a platform to collect listings.') }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Platform') }}</flux:table.column>
                <flux:table.column>{{ __('Price') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Scraped') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($listings as $listing)
                    <flux:table.row wire:key="listing-{{ $listing->id }}">
                        <flux:table.cell class="max-w-xs">
                            <flux:heading size="sm" class="truncate">
                                {{ $listing->raw_data['title'] ?? 'Untitled' }}
                            </flux:heading>
                            <flux:text size="sm" class="truncate text-zinc-500">
                                {{ $listing->external_id }}
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
                        <flux:table.cell class="max-w-xs">
                            <flux:text size="sm" class="truncate">
                                {{ $listing->raw_data['colonia'] ?? $listing->raw_data['city'] ?? '—' }}
                            </flux:text>
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
