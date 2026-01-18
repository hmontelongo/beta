<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:button
                    :href="route('properties.index')"
                    wire:navigate
                    variant="ghost"
                    size="sm"
                    icon="arrow-left"
                />
                <flux:heading size="xl" level="1">{{ $property->address ?? __('Property Details') }}</flux:heading>
            </div>
            <flux:subheading class="mt-1">
                {{ $property->colonia }}{{ $property->colonia && $property->city ? ', ' : '' }}{{ $property->city }}{{ $property->state ? ', ' . $property->state : '' }}
            </flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @php
                $confidence = $property->confidence_score ?? 0;
                $color = match(true) {
                    $confidence >= 80 => 'green',
                    $confidence >= 60 => 'yellow',
                    default => 'zinc',
                };
            @endphp
            <flux:badge size="lg" :color="$color">{{ __('Confidence') }}: {{ $confidence }}%</flux:badge>
            <flux:badge size="lg" color="purple">{{ $property->listings->count() }} {{ Str::plural(__('listing'), $property->listings->count()) }}</flux:badge>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Image Gallery --}}
            @if (count($this->images) > 0)
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-4">{{ __('Photos') }}</flux:heading>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                        @foreach ($this->images as $image)
                            <a href="{{ $image }}" target="_blank" class="relative aspect-square overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800 hover:opacity-80 transition">
                                <img src="{{ $image }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                            </a>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            {{-- Property Details --}}
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('Property Details') }}</flux:heading>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                    @if ($property->property_type)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Type') }}</flux:text>
                            <flux:text class="font-medium">{{ ucfirst($property->property_type->value) }}</flux:text>
                        </div>
                    @endif
                    @if ($property->property_subtype)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Subtype') }}</flux:text>
                            <flux:text class="font-medium">{{ ucfirst($property->property_subtype->value) }}</flux:text>
                        </div>
                    @endif
                    @if ($property->bedrooms)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Bedrooms') }}</flux:text>
                            <flux:text class="font-medium">{{ $property->bedrooms }}</flux:text>
                        </div>
                    @endif
                    @if ($property->bathrooms)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Bathrooms') }}</flux:text>
                            <flux:text class="font-medium">{{ $property->bathrooms }}</flux:text>
                        </div>
                    @endif
                    @if ($property->half_bathrooms)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Half Baths') }}</flux:text>
                            <flux:text class="font-medium">{{ $property->half_bathrooms }}</flux:text>
                        </div>
                    @endif
                    @if ($property->parking_spots)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Parking') }}</flux:text>
                            <flux:text class="font-medium">{{ $property->parking_spots }}</flux:text>
                        </div>
                    @endif
                    @if ($property->built_size_m2)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Built Size') }}</flux:text>
                            <flux:text class="font-medium">{{ number_format($property->built_size_m2) }} m²</flux:text>
                        </div>
                    @endif
                    @if ($property->lot_size_m2)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Lot Size') }}</flux:text>
                            <flux:text class="font-medium">{{ number_format($property->lot_size_m2) }} m²</flux:text>
                        </div>
                    @endif
                    @if ($property->age_years)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Age') }}</flux:text>
                            <flux:text class="font-medium">{{ $property->age_years }} {{ Str::plural(__('year'), $property->age_years) }}</flux:text>
                        </div>
                    @endif
                </div>

                @if ($property->amenities && count($property->amenities) > 0)
                    <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:text size="sm" class="text-zinc-500 mb-2">{{ __('Amenities') }}</flux:text>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($property->amenities as $amenity)
                                <flux:badge size="sm" color="zinc">{{ $amenity }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </flux:card>

            {{-- Price Comparison --}}
            @if (count($this->allPrices) > 0)
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-4">{{ __('Price Comparison') }}</flux:heading>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Platform') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column>{{ __('Price') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->allPrices as $priceData)
                                <flux:table.row wire:key="price-{{ $priceData['listing_id'] }}-{{ $priceData['type'] }}">
                                    <flux:table.cell>
                                        <flux:badge size="sm">{{ $priceData['platform'] }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$priceData['type'] === 'rent' ? 'blue' : 'green'">
                                            {{ $priceData['type'] === 'rent' ? __('Rent') : __('Sale') }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:text class="font-medium">
                                            ${{ number_format($priceData['price']) }}
                                            <span class="text-zinc-400 text-sm">{{ $priceData['currency'] }}</span>
                                        </flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="eye"
                                            :href="route('listings.show', $priceData['listing_id'])"
                                            wire:navigate
                                        />
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            @endif

            {{-- Source Listings --}}
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('Source Listings') }}</flux:heading>
                <div class="space-y-4">
                    @foreach ($property->listings as $listing)
                        <div wire:key="listing-{{ $listing->id }}" class="flex items-start justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <flux:badge size="sm">{{ $listing->platform->name }}</flux:badge>
                                    <flux:text size="sm" class="text-zinc-500">
                                        {{ $listing->external_id }}
                                    </flux:text>
                                </div>
                                <flux:heading size="sm" class="truncate">
                                    {{ $listing->raw_data['title'] ?? __('Untitled') }}
                                </flux:heading>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ __('Scraped') }} {{ $listing->scraped_at?->diffForHumans() }}
                                </flux:text>
                            </div>
                            <div class="flex gap-2 ml-4">
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
                        </div>
                    @endforeach
                </div>
            </flux:card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Location --}}
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('Location') }}</flux:heading>
                <div class="space-y-2">
                    @if ($property->address)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Address') }}</flux:text>
                            <flux:text>{{ $property->address }}</flux:text>
                        </div>
                    @endif
                    @if ($property->interior_number)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Interior') }}</flux:text>
                            <flux:text>{{ $property->interior_number }}</flux:text>
                        </div>
                    @endif
                    @if ($property->colonia)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Colonia') }}</flux:text>
                            <flux:text>{{ $property->colonia }}</flux:text>
                        </div>
                    @endif
                    @if ($property->city)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('City') }}</flux:text>
                            <flux:text>{{ $property->city }}</flux:text>
                        </div>
                    @endif
                    @if ($property->state)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('State') }}</flux:text>
                            <flux:text>{{ $property->state }}</flux:text>
                        </div>
                    @endif
                    @if ($property->postal_code)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Postal Code') }}</flux:text>
                            <flux:text>{{ $property->postal_code }}</flux:text>
                        </div>
                    @endif
                </div>
                @if ($property->latitude && $property->longitude)
                    <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:text size="sm" class="text-zinc-500 mb-2">{{ __('Coordinates') }}</flux:text>
                        <flux:text class="font-mono text-sm">{{ $property->latitude }}, {{ $property->longitude }}</flux:text>
                        <div class="mt-2">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="map-pin"
                                href="https://www.google.com/maps?q={{ $property->latitude }},{{ $property->longitude }}"
                                target="_blank"
                            >
                                {{ __('View on Map') }}
                            </flux:button>
                        </div>
                    </div>
                @endif
            </flux:card>

            {{-- Agents --}}
            @if ($property->agents->count() > 0)
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-4">{{ __('Agents') }}</flux:heading>
                    <div class="space-y-3">
                        @foreach ($property->agents as $agent)
                            <div wire:key="agent-{{ $agent->id }}" class="flex items-center gap-3">
                                <flux:avatar size="sm" name="{{ $agent->name }}" />
                                <div class="flex-1 min-w-0">
                                    <flux:text class="font-medium truncate">{{ $agent->name }}</flux:text>
                                    @if ($agent->phone)
                                        <flux:text size="sm" class="text-zinc-500">{{ $agent->phone }}</flux:text>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            {{-- Agencies --}}
            @if ($property->agencies->count() > 0)
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-4">{{ __('Agencies') }}</flux:heading>
                    <div class="space-y-3">
                        @foreach ($property->agencies as $agency)
                            <div wire:key="agency-{{ $agency->id }}" class="flex items-center gap-3">
                                <flux:avatar size="sm" name="{{ $agency->name }}" />
                                <div class="flex-1 min-w-0">
                                    <flux:text class="font-medium truncate">{{ $agency->name }}</flux:text>
                                    @if ($agency->phone)
                                        <flux:text size="sm" class="text-zinc-500">{{ $agency->phone }}</flux:text>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            {{-- Platforms --}}
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('Listed On') }}</flux:heading>
                <div class="flex flex-wrap gap-2">
                    @foreach ($property->platforms as $platform)
                        <flux:badge size="lg">{{ $platform->name }}</flux:badge>
                    @endforeach
                </div>
            </flux:card>

            {{-- Metadata --}}
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('Metadata') }}</flux:heading>
                <div class="space-y-2">
                    <div>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Property ID') }}</flux:text>
                        <flux:text class="font-mono">{{ $property->id }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Status') }}</flux:text>
                        <flux:badge size="sm">{{ ucfirst($property->status?->value ?? 'unknown') }}</flux:badge>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Created') }}</flux:text>
                        <flux:text>{{ $property->created_at->format('M j, Y') }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Last Updated') }}</flux:text>
                        <flux:text>{{ $property->updated_at->diffForHumans() }}</flux:text>
                    </div>
                    @if ($property->last_scraped_at)
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Last Scraped') }}</flux:text>
                            <flux:text>{{ $property->last_scraped_at->diffForHumans() }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>
    </div>
</div>
