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
        <div class="flex flex-wrap items-center gap-2">
            {{-- Freshness Badge with Tooltip --}}
            <flux:tooltip content="{{ __('Last scraped:') }} {{ $property->last_scraped_at?->format('M j, Y') ?? __('Never') }} ({{ $this->freshnessStatus['days_ago'] }} {{ Str::plural(__('day'), $this->freshnessStatus['days_ago']) }} {{ __('ago') }})">
                <flux:badge size="lg" :color="$this->freshnessStatus['color']">
                    {{ $this->freshnessStatus['label'] }}
                </flux:badge>
            </flux:tooltip>

            {{-- Sources Badge with Tooltip --}}
            <flux:tooltip content="{{ collect($this->platforms)->pluck('name')->join(', ') }}">
                <flux:badge size="lg" color="purple">
                    {{ $property->listings->count() }} {{ Str::plural(__('source'), $property->listings->count()) }}
                </flux:badge>
            </flux:tooltip>

            {{-- AI Unified Badge with Tooltip --}}
            @if ($this->unificationStatus['is_unified'])
                <flux:tooltip content="{{ __('Unified on') }} {{ $this->unificationStatus['unified_at']->format('M j, Y') }}. {{ __('Quality score:') }} {{ $property->confidence_score ?? 0 }}%">
                    <flux:badge size="lg" color="blue">
                        {{ __('AI Unified') }}
                    </flux:badge>
                </flux:tooltip>
            @endif
        </div>
    </div>

    {{-- Reanalysis Banner --}}
    @if ($property->needs_reanalysis)
        <flux:callout variant="warning" icon="arrow-path">
            <flux:callout.heading>{{ __('Re-analysis Pending') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('New listing data has been added. This property will be re-analyzed with AI to update unified data.') }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button
                    wire:click="reanalyzeWithAi"
                    wire:loading.attr="disabled"
                    size="sm"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="reanalyzeWithAi">{{ __('Analyze Now') }}</span>
                    <span wire:loading wire:target="reanalyzeWithAi">{{ __('Processing...') }}</span>
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main Content --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Image Carousel with Platform Selector --}}
            @if (count($this->images) > 0)
                <flux:card class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">{{ __('Photos') }}</flux:heading>
                        <div class="flex items-center gap-3">
                            @if (count($this->platforms) > 1)
                                <flux:select wire:model.live="selectedImagePlatform" size="sm" class="w-40">
                                    <flux:select.option value="">{{ __('All platforms') }}</flux:select.option>
                                    @foreach ($this->platforms as $platform)
                                        <flux:select.option value="{{ $platform['slug'] }}">
                                            {{ $platform['name'] }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                            <flux:badge size="sm" color="zinc">{{ count($this->images) }} {{ __('images') }}</flux:badge>
                        </div>
                    </div>
                    <x-image-carousel
                        :images="$this->images"
                        wire:key="carousel-{{ $selectedImagePlatform ?? 'all' }}"
                    />
                </flux:card>
            @endif

            {{-- Description --}}
            @if ($this->description['text'])
                <flux:card class="p-4">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:heading size="lg">{{ __('Description') }}</flux:heading>
                        @if ($this->description['source'] === 'unified')
                            <flux:badge size="sm" color="blue">{{ __('AI Unified') }}</flux:badge>
                        @elseif ($this->description['source'] === 'enriched')
                            <flux:badge size="sm" color="green">{{ __('AI Enhanced') }}</flux:badge>
                        @endif
                    </div>
                    <div
                        x-data="{ expanded: false }"
                        class="relative"
                    >
                        <div
                            class="prose prose-sm dark:prose-invert max-w-none"
                            :class="{ 'line-clamp-6': !expanded }"
                        >
                            {!! nl2br(e($this->description['text'])) !!}
                        </div>
                        @if (strlen($this->description['text']) > 500)
                            <button
                                @click="expanded = !expanded"
                                class="mt-2 text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                x-text="expanded ? '{{ __('Show less') }}' : '{{ __('Show more') }}'"
                            ></button>
                        @endif
                    </div>
                </flux:card>
            @endif

            {{-- Amenities --}}
            @if ($property->amenities && count($property->amenities) > 0)
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-4">{{ __('Amenities') }}</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($property->amenities as $amenity)
                            <flux:badge size="sm" color="zinc">{{ str_replace('_', ' ', ucfirst($amenity)) }}</flux:badge>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            {{-- AI Insights (Inconsistencies) --}}
            @if ($this->unificationStatus['is_unified'] && count($this->inconsistencies) > 0)
                <flux:card class="p-4">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="exclamation-triangle" class="size-5 text-amber-500" />
                        <flux:heading size="lg">{{ __('Data Inconsistencies') }}</flux:heading>
                        <flux:badge size="sm" color="amber">{{ count($this->inconsistencies) }}</flux:badge>
                    </div>
                    <flux:text size="sm" class="text-zinc-500 mb-4">
                        {{ __('The AI found conflicting data between listings and resolved them:') }}
                    </flux:text>
                    <div class="space-y-4">
                        @foreach ($this->inconsistencies as $inconsistency)
                            <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800">
                                <div class="flex items-center gap-2 mb-2">
                                    <flux:text class="font-medium">{{ ucfirst(str_replace('_', ' ', $inconsistency['field'])) }}</flux:text>
                                </div>
                                <div class="grid grid-cols-2 gap-2 mb-2 text-sm">
                                    @foreach ($inconsistency['values'] ?? [] as $value)
                                        <div class="flex items-center gap-1">
                                            <flux:badge size="xs" color="zinc">Listing #{{ $value['listing_id'] }}</flux:badge>
                                            <flux:text size="sm">{{ $value['value'] ?? 'N/A' }}</flux:text>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="flex items-center gap-2 pt-2 border-t border-amber-200 dark:border-amber-700">
                                    <flux:icon name="check-circle" class="size-4 text-green-500" />
                                    <flux:text size="sm" class="font-medium">{{ __('Resolved') }}: {{ $inconsistency['resolved_value'] ?? 'N/A' }}</flux:text>
                                </div>
                                @if (!empty($inconsistency['reasoning']))
                                    <flux:text size="xs" class="text-zinc-500 mt-1 italic">
                                        {{ $inconsistency['reasoning'] }}
                                    </flux:text>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            {{-- Raw Data Viewer --}}
            @if (count($this->rawDataByPlatform) > 0)
                <flux:card class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">{{ __('Raw Data') }}</flux:heading>
                        @if (count($this->platforms) > 1)
                            <flux:select wire:model.live="selectedRawDataPlatform" size="sm" class="w-40">
                                @foreach ($this->platforms as $platform)
                                    <flux:select.option value="{{ $platform['slug'] }}">
                                        {{ $platform['name'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                    </div>
                    @if ($this->selectedRawData)
                        <div
                            x-data="{ viewMode: 'formatted' }"
                            class="space-y-4"
                        >
                            <div class="flex gap-2">
                                <flux:button
                                    size="sm"
                                    x-on:click="viewMode = 'formatted'"
                                    ::variant="viewMode === 'formatted' ? 'primary' : 'ghost'"
                                >
                                    {{ __('Formatted') }}
                                </flux:button>
                                <flux:button
                                    size="sm"
                                    x-on:click="viewMode = 'json'"
                                    ::variant="viewMode === 'json' ? 'primary' : 'ghost'"
                                >
                                    {{ __('JSON') }}
                                </flux:button>
                            </div>

                            {{-- Formatted View --}}
                            <div x-show="viewMode === 'formatted'" class="space-y-3">
                                @php $rawData = $this->selectedRawData['data']; @endphp

                                @if (!empty($rawData['title']))
                                    <div>
                                        <flux:text size="sm" class="text-zinc-500">{{ __('Title') }}</flux:text>
                                        <flux:text>{{ $rawData['title'] }}</flux:text>
                                    </div>
                                @endif

                                @if (!empty($rawData['description']))
                                    <div>
                                        <flux:text size="sm" class="text-zinc-500">{{ __('Description') }}</flux:text>
                                        <div class="prose prose-sm dark:prose-invert max-w-none line-clamp-4">
                                            {!! nl2br(e(Str::limit($rawData['description'], 500))) !!}
                                        </div>
                                    </div>
                                @endif

                                @if (!empty($rawData['operations']))
                                    <div>
                                        <flux:text size="sm" class="text-zinc-500 mb-2">{{ __('Operations') }}</flux:text>
                                        @foreach ($rawData['operations'] as $op)
                                            <div class="flex items-center gap-2 mb-1">
                                                <flux:badge size="xs" :color="($op['type'] ?? '') === 'rent' ? 'blue' : 'green'">
                                                    {{ ucfirst($op['type'] ?? 'unknown') }}
                                                </flux:badge>
                                                <flux:text size="sm">${{ number_format($op['price'] ?? 0) }} {{ $op['currency'] ?? 'MXN' }}</flux:text>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    @foreach (['bedrooms', 'bathrooms', 'parking_spots', 'built_size_m2', 'lot_size_m2', 'age_years'] as $field)
                                        @if (isset($rawData[$field]))
                                            <div>
                                                <flux:text size="sm" class="text-zinc-500">{{ ucfirst(str_replace('_', ' ', $field)) }}</flux:text>
                                                <flux:text>{{ $rawData[$field] }}</flux:text>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            {{-- JSON View --}}
                            <div x-show="viewMode === 'json'" x-cloak>
                                <pre class="p-4 rounded-lg bg-zinc-900 text-zinc-100 text-xs overflow-x-auto max-h-96">{{ json_encode($this->selectedRawData['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    @endif
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
            {{-- Property Details (with Price) --}}
            <flux:card class="p-4">
                {{-- Price Section --}}
                @if ($this->primaryPrice)
                    @php
                        $pricesByType = collect($this->allPrices)->groupBy('type');
                        $hasMultipleTypes = $pricesByType->count() > 1;
                        $variations = $this->priceVariations;
                    @endphp
                    <div class="mb-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-center gap-2 mb-1">
                            <flux:badge size="sm" :color="$this->primaryPrice['type'] === 'rent' ? 'blue' : 'green'">
                                {{ $this->primaryPrice['type'] === 'rent' ? __('For Rent') : __('For Sale') }}
                            </flux:badge>
                            @if (count($this->allPrices) > 1)
                                <flux:text size="xs" class="text-zinc-400">
                                    {{ __('from') }} {{ count($this->allPrices) }} {{ __('listings') }}
                                </flux:text>
                            @endif
                        </div>

                        {{-- Price with variation range --}}
                        @if ($variations && $variations['has_variations'])
                            <div class="flex items-baseline gap-1">
                                <flux:heading size="xl">${{ number_format($variations['min']) }} - ${{ number_format($variations['max']) }}</flux:heading>
                                <flux:text size="sm" class="text-zinc-500">{{ $this->primaryPrice['currency'] }}</flux:text>
                                @if ($this->primaryPrice['type'] === 'rent')
                                    <flux:text size="xs" class="text-zinc-400">/ {{ __('mo') }}</flux:text>
                                @endif
                            </div>
                            {{-- Expandable price breakdown --}}
                            <div x-data="{ showPriceDetails: false }" class="mt-2">
                                <button @click="showPriceDetails = !showPriceDetails" class="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400">
                                    <span x-text="showPriceDetails ? '{{ __('Hide prices') }}' : '{{ __('Show prices by platform') }}'"></span>
                                    <flux:icon name="chevron-down" class="size-3 transition-transform" x-bind:class="showPriceDetails && 'rotate-180'" />
                                </button>
                                <div x-show="showPriceDetails" x-collapse class="mt-2 space-y-1">
                                    @foreach ($variations['by_platform'] as $priceInfo)
                                        <div class="flex items-center justify-between rounded bg-zinc-50 dark:bg-zinc-800 px-2 py-1">
                                            <flux:text size="xs" class="text-zinc-600 dark:text-zinc-400">{{ $priceInfo['platform'] }}</flux:text>
                                            <flux:text size="xs" class="font-medium">${{ number_format($priceInfo['price']) }}</flux:text>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="flex items-baseline gap-1">
                                <flux:heading size="xl">${{ number_format($this->primaryPrice['price']) }}</flux:heading>
                                <flux:text size="sm" class="text-zinc-500">{{ $this->primaryPrice['currency'] }}</flux:text>
                                @if ($this->primaryPrice['type'] === 'rent')
                                    <flux:text size="xs" class="text-zinc-400">/ {{ __('mo') }}</flux:text>
                                @endif
                            </div>
                        @endif

                        @if ($this->primaryPrice['maintenance_fee'])
                            <flux:text size="xs" class="text-zinc-500">
                                + ${{ number_format($this->primaryPrice['maintenance_fee']) }} {{ __('maint.') }}
                            </flux:text>
                        @endif
                        @if ($hasMultipleTypes)
                            @foreach ($pricesByType as $type => $prices)
                                @if ($type !== $this->primaryPrice['type'])
                                    <div class="mt-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-2">
                                            <flux:badge size="xs" :color="$type === 'rent' ? 'blue' : 'green'">
                                                {{ $type === 'rent' ? __('Also Rent') : __('Also Sale') }}
                                            </flux:badge>
                                            <flux:text size="sm" class="font-medium">
                                                ${{ number_format($prices->first()['price']) }}
                                            </flux:text>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                @endif

                {{-- Property Details --}}
                <flux:heading size="lg" class="mb-3">{{ __('Details') }}</flux:heading>
                <div class="grid grid-cols-2 gap-3">
                    @if ($property->property_type)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Type') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ ucfirst($property->property_type->value) }}</flux:text>
                        </div>
                    @endif
                    @if ($property->property_subtype)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Subtype') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ ucfirst($property->property_subtype->value) }}</flux:text>
                        </div>
                    @endif
                    @if ($property->bedrooms)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Bedrooms') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $property->bedrooms }}</flux:text>
                        </div>
                    @endif
                    @if ($property->bathrooms)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Bathrooms') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $property->bathrooms }}</flux:text>
                        </div>
                    @endif
                    @if ($property->half_bathrooms)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Half Baths') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $property->half_bathrooms }}</flux:text>
                        </div>
                    @endif
                    @if ($property->parking_spots)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Parking') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $property->parking_spots }}</flux:text>
                        </div>
                    @endif
                    @if ($property->built_size_m2)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Built') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ number_format($property->built_size_m2) }} m²</flux:text>
                        </div>
                    @endif
                    @if ($property->lot_size_m2)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Lot') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ number_format($property->lot_size_m2) }} m²</flux:text>
                        </div>
                    @endif
                    @if ($property->age_years)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Age') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $property->age_years }} {{ Str::plural(__('yr'), $property->age_years) }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>

            {{-- Location + Map --}}
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-3">{{ __('Location') }}</flux:heading>

                {{-- Map --}}
                @if ($property->latitude && $property->longitude)
                    <div class="aspect-video rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 mb-4">
                        <iframe
                            width="100%"
                            height="100%"
                            style="border:0"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade"
                            src="https://www.google.com/maps/embed/v1/place?key={{ config('services.google.maps_api_key') }}&q={{ $property->latitude }},{{ $property->longitude }}&zoom=15"
                        ></iframe>
                    </div>
                @endif

                {{-- Address Details --}}
                <div class="space-y-2">
                    @if ($property->address)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Address') }}</flux:text>
                            <flux:text size="sm">{{ $property->address }}</flux:text>
                        </div>
                    @endif
                    @if ($property->interior_number)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Interior') }}</flux:text>
                            <flux:text size="sm">{{ $property->interior_number }}</flux:text>
                        </div>
                    @endif
                    @if ($property->colonia)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Colonia') }}</flux:text>
                            <flux:text size="sm">{{ $property->colonia }}</flux:text>
                        </div>
                    @endif
                    <div class="flex gap-4">
                        @if ($property->city)
                            <div class="flex-1">
                                <flux:text size="xs" class="text-zinc-500">{{ __('City') }}</flux:text>
                                <flux:text size="sm">{{ $property->city }}</flux:text>
                            </div>
                        @endif
                        @if ($property->state)
                            <div class="flex-1">
                                <flux:text size="xs" class="text-zinc-500">{{ __('State') }}</flux:text>
                                <flux:text size="sm">{{ $property->state }}</flux:text>
                            </div>
                        @endif
                    </div>
                    @if ($property->postal_code)
                        <div>
                            <flux:text size="xs" class="text-zinc-500">{{ __('Postal Code') }}</flux:text>
                            <flux:text size="sm">{{ $property->postal_code }}</flux:text>
                        </div>
                    @endif
                </div>

            </flux:card>

            {{-- Publishers / Contact Info --}}
            @if ($this->uniquePublishers->isNotEmpty())
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-4">{{ __('Contact') }}</flux:heading>

                    <div class="space-y-4">
                        @foreach ($this->uniquePublishers as $publisher)
                            <div class="flex items-start gap-3 {{ !$loop->last ? 'pb-4 border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="font-medium">{{ $publisher->name }}</flux:text>
                                        <flux:badge size="xs" :color="$publisher->type->color()">
                                            {{ $publisher->type->label() }}
                                        </flux:badge>
                                    </div>
                                    @if ($publisher->phone)
                                        <div class="flex items-center gap-1 mt-1">
                                            <flux:icon name="phone" class="size-3 text-zinc-400" />
                                            <a href="tel:{{ $publisher->phone }}" class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-blue-600">
                                                {{ $publisher->phone }}
                                            </a>
                                        </div>
                                    @endif
                                    @if ($publisher->whatsapp)
                                        <div class="flex items-center gap-1 mt-1">
                                            <flux:icon name="chat-bubble-left" class="size-3 text-green-500" />
                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $publisher->whatsapp) }}" target="_blank" class="text-sm text-green-600 hover:text-green-800">
                                                {{ __('WhatsApp') }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="eye"
                                    :href="route('publishers.show', $publisher)"
                                    wire:navigate
                                />
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            {{-- Original Listings --}}
            @if (count($this->listingLinks) > 0)
                <flux:card class="p-4">
                    <flux:heading size="lg" class="mb-4">{{ __('View Original Listings') }}</flux:heading>
                    <div class="space-y-2">
                        @foreach ($this->listingLinks as $link)
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="arrow-top-right-on-square"
                                :href="$link['url']"
                                target="_blank"
                                class="w-full justify-start"
                            >
                                {{ $link['platform'] }}
                            </flux:button>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            {{-- AI Unification Actions --}}
            <flux:card class="p-4">
                <flux:heading size="lg" class="mb-4">{{ __('AI Unification') }}</flux:heading>
                @if ($this->unificationStatus['is_unified'])
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center gap-2">
                            <flux:icon name="check-circle" class="size-5 text-green-500" />
                            <flux:text>{{ __('Data unified by AI') }}</flux:text>
                        </div>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Unified') }} {{ $this->unificationStatus['unified_at']?->diffForHumans() }}
                        </flux:text>
                        @if ($this->unificationStatus['inconsistencies_count'] > 0)
                            <flux:text size="sm" class="text-amber-600">
                                {{ $this->unificationStatus['inconsistencies_count'] }} {{ __('inconsistencies resolved') }}
                            </flux:text>
                        @endif
                    </div>
                @else
                    <flux:text size="sm" class="text-zinc-500 mb-4">
                        {{ __('Property data has not been unified by AI yet.') }}
                    </flux:text>
                @endif
                <flux:button
                    wire:click="reanalyzeWithAi"
                    wire:loading.attr="disabled"
                    variant="primary"
                    size="sm"
                    icon="sparkles"
                    class="w-full"
                >
                    <span wire:loading.remove wire:target="reanalyzeWithAi">
                        {{ $this->unificationStatus['is_unified'] ? __('Re-analyze with AI') : __('Analyze with AI') }}
                    </span>
                    <span wire:loading wire:target="reanalyzeWithAi">{{ __('Processing...') }}</span>
                </flux:button>
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
                        <flux:text size="sm" class="text-zinc-500">{{ __('Data Completeness') }}</flux:text>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                <div
                                    class="h-full {{ $this->completenessScore >= 80 ? 'bg-green-500' : ($this->completenessScore >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                    style="width: {{ $this->completenessScore }}%"
                                ></div>
                            </div>
                            <flux:text size="sm" class="font-medium">{{ $this->completenessScore }}%</flux:text>
                        </div>
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
