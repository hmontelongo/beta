<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Publishers') }}</flux:heading>
            <flux:subheading>{{ __('Agents, agencies, and developers listing properties.') }}</flux:subheading>
        </div>
    </div>

    {{-- Filter Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <flux:card class="p-3">
            <div class="flex items-center gap-2">
                <flux:icon name="users" class="size-4 text-zinc-400" />
                <flux:text size="sm" class="text-zinc-500">{{ __('Total') }}</flux:text>
            </div>
            <flux:heading size="lg">{{ $this->filterStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'individual' ? 'ring-2 ring-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'individual' ? '' : 'individual' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="user" class="size-4 {{ $quickFilter === 'individual' ? 'text-blue-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'individual' ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500' }}">{{ __('Individuals') }}</flux:text>
            </div>
            <flux:heading size="lg" class="text-blue-600">{{ $this->filterStats['individual'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'agency' ? 'ring-2 ring-purple-400 bg-purple-50 dark:bg-purple-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'agency' ? '' : 'agency' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="building-office-2" class="size-4 {{ $quickFilter === 'agency' ? 'text-purple-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'agency' ? 'text-purple-600 dark:text-purple-400' : 'text-zinc-500' }}">{{ __('Agencies') }}</flux:text>
            </div>
            <flux:heading size="lg" class="text-purple-600">{{ $this->filterStats['agency'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'developer' ? 'ring-2 ring-green-400 bg-green-50 dark:bg-green-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'developer' ? '' : 'developer' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="building-library" class="size-4 {{ $quickFilter === 'developer' ? 'text-green-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'developer' ? 'text-green-600 dark:text-green-400' : 'text-zinc-500' }}">{{ __('Developers') }}</flux:text>
            </div>
            <flux:heading size="lg" class="text-green-600">{{ $this->filterStats['developer'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'high_volume' ? 'ring-2 ring-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'high_volume' ? '' : 'high_volume' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="chart-bar" class="size-4 {{ $quickFilter === 'high_volume' ? 'text-amber-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'high_volume' ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500' }}">{{ __('High Volume') }}</flux:text>
            </div>
            <flux:heading size="lg" class="{{ $this->filterStats['high_volume'] > 0 ? 'text-amber-600' : '' }}">{{ $this->filterStats['high_volume'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'with_contact' ? 'ring-2 ring-cyan-400 bg-cyan-50 dark:bg-cyan-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'with_contact' ? '' : 'with_contact' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="phone" class="size-4 {{ $quickFilter === 'with_contact' ? 'text-cyan-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'with_contact' ? 'text-cyan-600 dark:text-cyan-400' : 'text-zinc-500' }}">{{ __('With Contact') }}</flux:text>
            </div>
            <flux:heading size="lg" class="{{ $quickFilter === 'with_contact' ? 'text-cyan-600' : '' }}">{{ $this->filterStats['with_contact'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:flex-wrap">
        <div class="flex-1 min-w-[200px]">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search by name, phone, or email...') }}"
                clearable
            />
        </div>
        <flux:select wire:model.live="type" class="w-40">
            <flux:select.option value="">{{ __('All Types') }}</flux:select.option>
            @foreach ($publisherTypes as $publisherType)
                <flux:select.option value="{{ $publisherType->value }}">{{ $publisherType->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <div class="flex items-center gap-1">
            <flux:text size="sm" class="text-zinc-500 whitespace-nowrap">{{ __('Min Listings:') }}</flux:text>
            <flux:input
                wire:model.live.debounce.500ms="listingsMin"
                type="number"
                min="0"
                placeholder="{{ __('Any') }}"
                class="w-20"
            />
        </div>
        <flux:select wire:model.live="sortBy" class="w-44">
            <flux:select.option value="name">{{ __('Name A-Z') }}</flux:select.option>
            <flux:select.option value="newest">{{ __('Newest First') }}</flux:select.option>
            <flux:select.option value="most_properties">{{ __('Most Properties') }}</flux:select.option>
            <flux:select.option value="most_listings">{{ __('Most Listings') }}</flux:select.option>
        </flux:select>
        @if ($search || $type || $quickFilter || $listingsMin)
            <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark" aria-label="{{ __('Clear all filters') }}">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    {{-- Publishers Table --}}
    @if ($publishers->isEmpty())
        <flux:callout icon="users">
            <flux:callout.heading>{{ __('No publishers found') }}</flux:callout.heading>
            <flux:callout.text>
                @if ($search || $type)
                    {{ __('Try adjusting your search filters.') }}
                @else
                    {{ __('Publishers are extracted from listings when scraping. Run a scrape to populate publishers.') }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column>{{ __('Properties') }}</flux:table.column>
                <flux:table.column>{{ __('Listings') }}</flux:table.column>
                <flux:table.column>{{ __('Platforms') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($publishers as $publisher)
                    <flux:table.row wire:key="publisher-{{ $publisher->id }}">
                        <flux:table.cell class="max-w-xs">
                            <flux:heading size="sm" class="truncate">
                                {{ $publisher->name }}
                            </flux:heading>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$publisher->type->color()">
                                <flux:icon :name="$publisher->type->icon()" variant="micro" class="mr-1" />
                                {{ $publisher->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col gap-1">
                                @if ($publisher->phone)
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="phone" variant="micro" class="text-zinc-400" />
                                        <flux:text size="sm">{{ $publisher->phone }}</flux:text>
                                    </div>
                                @endif
                                @if ($publisher->email)
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="envelope" variant="micro" class="text-zinc-400" />
                                        <flux:text size="sm" class="truncate max-w-[150px]">{{ $publisher->email }}</flux:text>
                                    </div>
                                @endif
                                @if (!$publisher->phone && !$publisher->email)
                                    <flux:text class="text-zinc-400">-</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="purple">{{ $publisher->properties_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $publisher->listings_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $platforms = $publisher->platforms;
                            @endphp
                            @if (count($platforms) > 0)
                                <div class="flex -space-x-1">
                                    @foreach (array_slice($platforms, 0, 3) as $platform)
                                        <flux:tooltip content="{{ $platform }}">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-zinc-100 dark:bg-zinc-700 text-xs ring-2 ring-white dark:ring-zinc-800">
                                                {{ substr($platform, 0, 1) }}
                                            </span>
                                        </flux:tooltip>
                                    @endforeach
                                    @if (count($platforms) > 3)
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-zinc-200 dark:bg-zinc-600 text-xs ring-2 ring-white dark:ring-zinc-800">
                                            +{{ count($platforms) - 3 }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                <flux:text class="text-zinc-400">-</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="eye"
                                :href="route('admin.publishers.show', $publisher)"
                                wire:navigate
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $publishers->links() }}
        </div>
    @endif
</div>
