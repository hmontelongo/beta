<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Properties') }}</flux:heading>
            <flux:subheading>{{ __('Browse canonical properties merged from multiple listings.') }}</flux:subheading>
        </div>
    </div>

    {{-- Filter Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <flux:card class="p-3">
            <div class="flex items-center gap-2">
                <flux:icon name="home" class="size-4 text-zinc-400" />
                <flux:text size="sm" class="text-zinc-500">{{ __('Total') }}</flux:text>
            </div>
            <flux:heading size="lg">{{ $this->filterStats['total'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'multi_listing' ? 'ring-2 ring-purple-400 bg-purple-50 dark:bg-purple-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'multi_listing' ? '' : 'multi_listing' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="document-duplicate" class="size-4 {{ $quickFilter === 'multi_listing' ? 'text-purple-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'multi_listing' ? 'text-purple-600 dark:text-purple-400' : 'text-zinc-500' }}">{{ __('Multi-Listing') }}</flux:text>
            </div>
            <flux:heading size="lg" class="text-purple-600">{{ $this->filterStats['multi_listing'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'needs_reanalysis' ? 'ring-2 ring-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'needs_reanalysis' ? '' : 'needs_reanalysis' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="arrow-path" class="size-4 {{ $quickFilter === 'needs_reanalysis' ? 'text-amber-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'needs_reanalysis' ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-500' }}">{{ __('Needs Update') }}</flux:text>
            </div>
            <flux:heading size="lg" class="{{ $this->filterStats['needs_reanalysis'] > 0 ? 'text-amber-600' : '' }}">{{ $this->filterStats['needs_reanalysis'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'with_parking' ? 'ring-2 ring-blue-400 bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'with_parking' ? '' : 'with_parking' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="truck" class="size-4 {{ $quickFilter === 'with_parking' ? 'text-blue-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'with_parking' ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500' }}">{{ __('With Parking') }}</flux:text>
            </div>
            <flux:heading size="lg" class="{{ $quickFilter === 'with_parking' ? 'text-blue-600' : '' }}">{{ $this->filterStats['with_parking'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'high_confidence' ? 'ring-2 ring-green-400 bg-green-50 dark:bg-green-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'high_confidence' ? '' : 'high_confidence' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="check-badge" class="size-4 {{ $quickFilter === 'high_confidence' ? 'text-green-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'high_confidence' ? 'text-green-600 dark:text-green-400' : 'text-zinc-500' }}">{{ __('High Conf.') }}</flux:text>
            </div>
            <flux:heading size="lg" class="text-green-600">{{ $this->filterStats['high_confidence'] }}</flux:heading>
        </flux:card>
        <flux:card
            class="p-3 cursor-pointer transition-colors {{ $quickFilter === 'low_confidence' ? 'ring-2 ring-red-400 bg-red-50 dark:bg-red-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
            wire:click="$set('quickFilter', '{{ $quickFilter === 'low_confidence' ? '' : 'low_confidence' }}')"
        >
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="size-4 {{ $quickFilter === 'low_confidence' ? 'text-red-500' : 'text-zinc-400' }}" />
                <flux:text size="sm" class="{{ $quickFilter === 'low_confidence' ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}">{{ __('Low Conf.') }}</flux:text>
            </div>
            <flux:heading size="lg" class="{{ $this->filterStats['low_confidence'] > 0 ? 'text-red-600' : '' }}">{{ $this->filterStats['low_confidence'] }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters Row --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="{{ __('Search by address, colonia, or city...') }}"
                clearable
            />
        </div>
        <flux:select wire:model.live="propertyType" class="w-36">
            <flux:select.option value="">{{ __('All Types') }}</flux:select.option>
            @foreach ($propertyTypes as $type)
                <flux:select.option value="{{ $type->value }}">{{ ucfirst($type->value) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="city" class="w-44">
            <flux:select.option value="">{{ __('All Cities') }}</flux:select.option>
            @foreach ($this->cities as $c)
                <flux:select.option value="{{ $c }}">{{ $c }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:button
            wire:click="$set('showFiltersModal', true)"
            variant="{{ $this->advancedFilterCount > 0 ? 'filled' : 'outline' }}"
            icon="adjustments-horizontal"
        >
            {{ __('Filters') }}
            @if ($this->advancedFilterCount > 0)
                <flux:badge size="sm" color="zinc" class="ml-1">{{ $this->advancedFilterCount }}</flux:badge>
            @endif
        </flux:button>
    </div>

    {{-- Active Filter Chips --}}
    @if (count($this->activeFilters) > 0)
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($this->activeFilters as $key => $label)
                <flux:badge color="zinc" class="gap-1">
                    {{ $label }}
                    <button
                        wire:click="removeFilter('{{ $key }}')"
                        class="ml-1 hover:text-red-500 transition-colors"
                        aria-label="{{ __('Remove filter') }}"
                    >
                        <flux:icon name="x-mark" variant="micro" />
                    </button>
                </flux:badge>
            @endforeach
            <flux:button wire:click="clearAllFilters" size="sm" variant="ghost">
                {{ __('Clear all') }}
            </flux:button>
        </div>
    @endif

    {{-- Properties Table --}}
    @if ($properties->isEmpty())
        <flux:callout icon="home">
            <flux:callout.heading>{{ __('No properties found') }}</flux:callout.heading>
            <flux:callout.text>
                @if (count($this->activeFilters) > 0)
                    {{ __('Try adjusting your search filters.') }}
                @else
                    {{ __('Properties are created when listings are deduplicated. Run the dedup process from the Listings page.') }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'address'" :direction="$sortDir" wire:click="sort('address')">
                    {{ __('Address') }}
                </flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'bedrooms'" :direction="$sortDir" wire:click="sort('bedrooms')">
                    {{ __('Features') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'size'" :direction="$sortDir" wire:click="sort('size')">
                    {{ __('Size') }}
                </flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'listings'" :direction="$sortDir" wire:click="sort('listings')">
                    {{ __('Listings') }}
                </flux:table.column>
                <flux:table.column>{{ __('Publishers') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'confidence'" :direction="$sortDir" wire:click="sort('confidence')">
                    {{ __('Confidence') }}
                </flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($properties as $property)
                    <flux:table.row wire:key="property-{{ $property->id }}">
                        <flux:table.cell class="max-w-xs">
                            <flux:heading size="sm" class="truncate">
                                {{ $property->address ?? __('Unknown Address') }}
                            </flux:heading>
                            <flux:text size="sm" class="truncate text-zinc-500">
                                {{ $property->colonia }}{{ $property->colonia && $property->city ? ', ' : '' }}{{ $property->city }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($property->property_type)
                                <flux:badge size="sm">{{ ucfirst($property->property_type->value) }}</flux:badge>
                            @else
                                <flux:text class="text-zinc-400">-</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @if ($property->bedrooms)
                                    <flux:badge size="sm" color="zinc">{{ $property->bedrooms }} {{ __('bd') }}</flux:badge>
                                @endif
                                @if ($property->bathrooms)
                                    <flux:badge size="sm" color="zinc">{{ $property->bathrooms }} {{ __('ba') }}</flux:badge>
                                @endif
                                @if ($property->parking_spots)
                                    <flux:badge size="sm" color="zinc">{{ $property->parking_spots }} {{ __('pk') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($property->built_size_m2)
                                <flux:text size="sm">{{ number_format($property->built_size_m2) }} m²</flux:text>
                            @else
                                <flux:text class="text-zinc-400">-</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" color="purple">{{ $property->listings_count }}</flux:badge>
                                <div class="flex -space-x-1">
                                    @foreach ($property->platforms->take(3) as $platform)
                                        <flux:tooltip content="{{ $platform->name }}">
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-zinc-100 dark:bg-zinc-700 text-xs ring-2 ring-white dark:ring-zinc-800">
                                                {{ substr($platform->name, 0, 1) }}
                                            </span>
                                        </flux:tooltip>
                                    @endforeach
                                    @if ($property->platforms->count() > 3)
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-zinc-200 dark:bg-zinc-600 text-xs ring-2 ring-white dark:ring-zinc-800">
                                            +{{ $property->platforms->count() - 3 }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-[12rem]">
                            @php
                                $publishers = $property->publishers;
                                $firstPublisher = $publishers->first();
                            @endphp
                            <div class="flex flex-col gap-1">
                                @if ($firstPublisher)
                                    <flux:tooltip content="{{ $publishers->pluck('name')->join(', ') }}">
                                        <div class="flex items-center gap-1">
                                            <flux:icon :name="$firstPublisher->type->icon()" variant="micro" class="text-zinc-400 shrink-0" />
                                            <flux:text size="sm" class="truncate">
                                                {{ Str::limit($firstPublisher->name, 18) }}
                                            </flux:text>
                                            @if ($publishers->count() > 1)
                                                <flux:badge size="sm" color="zinc">+{{ $publishers->count() - 1 }}</flux:badge>
                                            @endif
                                        </div>
                                    </flux:tooltip>
                                @else
                                    <flux:text class="text-zinc-400">-</flux:text>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $confidence = $property->confidence_score ?? 0;
                                $color = match(true) {
                                    $confidence >= 80 => 'green',
                                    $confidence >= 60 => 'yellow',
                                    default => 'zinc',
                                };
                                $tooltipText = match(true) {
                                    $confidence >= 80 => __('High confidence in data accuracy'),
                                    $confidence >= 60 => __('Moderate confidence - may need review'),
                                    default => __('Low confidence - data may be incomplete'),
                                };
                            @endphp
                            <flux:tooltip :content="$tooltipText">
                                <flux:badge size="sm" :color="$color">{{ $confidence }}%</flux:badge>
                            </flux:tooltip>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="eye"
                                :href="route('properties.show', $property)"
                                wire:navigate
                                aria-label="{{ __('View property') }}"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $properties->links() }}
        </div>
    @endif

    {{-- Filters Modal --}}
    <flux:modal wire:model="showFiltersModal" class="max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('More Filters') }}</flux:heading>

            {{-- Bedrooms --}}
            <div class="space-y-2">
                <flux:label>{{ __('Bedrooms') }}</flux:label>
                <div class="flex flex-wrap gap-2">
                    @foreach (['', '1', '2', '3', '4', '5'] as $value)
                        <flux:button
                            wire:click="$set('bedrooms', '{{ $value }}')"
                            size="sm"
                            :variant="$bedrooms === $value ? 'primary' : 'outline'"
                        >
                            {{ $value === '' ? __('Any') : $value . '+' }}
                        </flux:button>
                    @endforeach
                </div>
            </div>

            {{-- Bathrooms --}}
            <div class="space-y-2">
                <flux:label>{{ __('Bathrooms') }}</flux:label>
                <div class="flex flex-wrap gap-2">
                    @foreach (['', '1', '2', '3', '4'] as $value)
                        <flux:button
                            wire:click="$set('bathrooms', '{{ $value }}')"
                            size="sm"
                            :variant="$bathrooms === $value ? 'primary' : 'outline'"
                        >
                            {{ $value === '' ? __('Any') : $value . '+' }}
                        </flux:button>
                    @endforeach
                </div>
            </div>

            {{-- Has Parking --}}
            <div class="flex items-center justify-between">
                <flux:label>{{ __('Has Parking') }}</flux:label>
                <flux:switch wire:model="hasParking" />
            </div>

            {{-- Size Range --}}
            <div class="space-y-2">
                <flux:label>{{ __('Built Size (m²)') }}</flux:label>
                <div class="flex items-center gap-3">
                    <flux:input
                        wire:model="minSize"
                        type="number"
                        min="0"
                        placeholder="{{ __('Min') }}"
                        class="w-28"
                    />
                    <flux:text class="text-zinc-400">{{ __('to') }}</flux:text>
                    <flux:input
                        wire:model="maxSize"
                        type="number"
                        min="0"
                        placeholder="{{ __('Max') }}"
                        class="w-28"
                    />
                </div>
            </div>

            {{-- Amenities --}}
            <div class="space-y-2">
                <flux:label>{{ __('Amenities') }}</flux:label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($popularAmenities as $key => $label)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <flux:checkbox
                                wire:model="amenities"
                                value="{{ $key }}"
                            />
                            <flux:text size="sm">{{ $label }}</flux:text>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Modal Actions --}}
            <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="clearAdvancedFilters" variant="ghost">
                    {{ __('Clear filters') }}
                </flux:button>
                <flux:button wire:click="applyFilters" variant="primary">
                    {{ __('Show results') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
