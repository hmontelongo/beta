<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Publishers') }}</flux:heading>
            <flux:subheading>{{ __('Agents, agencies, and developers listing properties.') }}</flux:subheading>
        </div>
    </div>

    {{-- Stats Bar --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text size="sm" class="text-zinc-500">{{ __('Total') }}</flux:text>
            <flux:heading size="lg">{{ $this->stats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm" class="text-zinc-500">{{ __('Individuals') }}</flux:text>
            <flux:heading size="lg" class="text-blue-600">{{ $this->stats['individual'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm" class="text-zinc-500">{{ __('Agencies') }}</flux:text>
            <flux:heading size="lg" class="text-purple-600">{{ $this->stats['agency'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm" class="text-zinc-500">{{ __('Developers') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->stats['developer'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
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
        <flux:select wire:model.live="sortBy" class="w-44">
            <flux:select.option value="name">{{ __('Name A-Z') }}</flux:select.option>
            <flux:select.option value="newest">{{ __('Newest First') }}</flux:select.option>
            <flux:select.option value="most_properties">{{ __('Most Properties') }}</flux:select.option>
            <flux:select.option value="most_listings">{{ __('Most Listings') }}</flux:select.option>
        </flux:select>
        @if ($search || $type)
            <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark">
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
                                :href="route('publishers.show', $publisher)"
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
