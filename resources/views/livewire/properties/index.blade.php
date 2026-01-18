<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Properties') }}</flux:heading>
            <flux:subheading>{{ __('Browse canonical properties merged from multiple listings.') }}</flux:subheading>
        </div>
    </div>

    {{-- Stats Bar --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <flux:card class="p-4">
            <flux:text size="sm" class="text-zinc-500">{{ __('Total Properties') }}</flux:text>
            <flux:heading size="lg">{{ $this->stats['total'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm" class="text-zinc-500">{{ __('Multi-Listing') }}</flux:text>
            <flux:heading size="lg" class="text-purple-600">{{ $this->stats['with_listings'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm" class="text-zinc-500">{{ __('Verified') }}</flux:text>
            <flux:heading size="lg" class="text-green-600">{{ $this->stats['verified'] }}</flux:heading>
        </flux:card>
        <flux:card class="p-4">
            <flux:text size="sm" class="text-zinc-500">{{ __('Shown') }}</flux:text>
            <flux:heading size="lg">{{ $properties->total() }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Search by address, colonia, or city...') }}"
                    clearable
                />
            </div>
            <div class="flex gap-2">
                <flux:select wire:model.live="city" class="w-40">
                    <flux:select.option value="">{{ __('All Cities') }}</flux:select.option>
                    @foreach ($this->cities as $c)
                        <flux:select.option value="{{ $c }}">{{ $c }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="colonia" class="w-48">
                    <flux:select.option value="">{{ __('All Colonias') }}</flux:select.option>
                    @foreach ($this->colonias as $col)
                        <flux:select.option value="{{ $col }}">{{ $col }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <flux:select wire:model.live="propertyType" class="w-40">
                <flux:select.option value="">{{ __('All Types') }}</flux:select.option>
                @foreach ($propertyTypes as $type)
                    <flux:select.option value="{{ $type->value }}">{{ ucfirst($type->value) }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex items-center gap-2">
                <flux:input
                    wire:model.live.debounce.500ms="bedroomsMin"
                    type="number"
                    min="0"
                    max="20"
                    placeholder="{{ __('Min beds') }}"
                    class="w-24"
                />
                <flux:text class="text-zinc-400">{{ __('to') }}</flux:text>
                <flux:input
                    wire:model.live.debounce.500ms="bedroomsMax"
                    type="number"
                    min="0"
                    max="20"
                    placeholder="{{ __('Max beds') }}"
                    class="w-24"
                />
            </div>
            <flux:select wire:model.live="sortBy" class="w-44">
                <flux:select.option value="newest">{{ __('Newest First') }}</flux:select.option>
                <flux:select.option value="oldest">{{ __('Oldest First') }}</flux:select.option>
                <flux:select.option value="most_listings">{{ __('Most Listings') }}</flux:select.option>
                <flux:select.option value="highest_confidence">{{ __('Highest Confidence') }}</flux:select.option>
            </flux:select>
            @if ($search || $city || $colonia || $propertyType || $bedroomsMin || $bedroomsMax)
                <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark">
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Properties Table --}}
    @if ($properties->isEmpty())
        <flux:callout icon="home">
            <flux:callout.heading>{{ __('No properties found') }}</flux:callout.heading>
            <flux:callout.text>
                @if ($search || $city || $colonia || $propertyType || $bedroomsMin || $bedroomsMax)
                    {{ __('Try adjusting your search filters.') }}
                @else
                    {{ __('Properties are created when listings are deduplicated. Run the dedup process from the Listings page.') }}
                @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Address') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Features') }}</flux:table.column>
                <flux:table.column>{{ __('Listings') }}</flux:table.column>
                <flux:table.column>{{ __('Agents') }}</flux:table.column>
                <flux:table.column>{{ __('Confidence') }}</flux:table.column>
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
                                @if ($property->built_size_m2)
                                    <flux:badge size="sm" color="zinc">{{ number_format($property->built_size_m2) }}m2</flux:badge>
                                @endif
                            </div>
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
                        <flux:table.cell>
                            @php
                                $agentCount = $property->agents->count();
                                $agencyCount = $property->agencies->count();
                            @endphp
                            <div class="flex flex-col gap-0.5">
                                @if ($agentCount > 0)
                                    <flux:text size="sm">{{ $agentCount }} {{ Str::plural(__('agent'), $agentCount) }}</flux:text>
                                @endif
                                @if ($agencyCount > 0)
                                    <flux:text size="sm" class="text-zinc-500">{{ $agencyCount }} {{ Str::plural(__('agency'), $agencyCount) }}</flux:text>
                                @endif
                                @if ($agentCount === 0 && $agencyCount === 0)
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
                            @endphp
                            <flux:badge size="sm" :color="$color">{{ $confidence }}%</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="eye"
                                :href="route('properties.show', $property)"
                                wire:navigate
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
</div>
