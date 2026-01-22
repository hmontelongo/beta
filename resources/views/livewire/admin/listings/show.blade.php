<div class="space-y-6" @if($this->isProcessing) wire:poll.2s @endif>
    {{-- Page Header --}}
    <div class="flex items-start gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('admin.listings.index')" wire:navigate aria-label="{{ __('Back to listings') }}" />
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ $listing->raw_data['title'] ?? 'Listing Details' }}</flux:heading>
                <flux:badge>{{ $listing->platform->name }}</flux:badge>
            </div>
            <flux:subheading>{{ $listing->external_id }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button
                wire:click="rescrape"
                wire:loading.attr="disabled"
                :disabled="$isRescraping"
                icon="arrow-path"
                variant="ghost"
            >
                <span wire:loading.remove wire:target="rescrape">{{ __('Re-scrape') }}</span>
                <span wire:loading wire:target="rescrape">{{ __('Queued...') }}</span>
            </flux:button>
            <flux:button icon="arrow-top-right-on-square" :href="$listing->original_url" target="_blank">
                {{ __('View Original') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main Content --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Image Carousel --}}
            @if (!empty($this->images))
                <flux:card class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">{{ __('Photos') }}</flux:heading>
                        <flux:badge size="sm" color="zinc">{{ count($this->images) }} {{ __('images') }}</flux:badge>
                    </div>
                    <x-image-carousel :images="$this->images" :max-thumbnails="10" />
                </flux:card>
            @endif

            {{-- Description --}}
            @if (!empty($listing->raw_data['description']))
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Description') }}</flux:heading>
                    <flux:text class="whitespace-pre-line">{{ $listing->raw_data['description'] }}</flux:text>
                </flux:card>
            @endif

            {{-- Amenities --}}
            @if (!empty($listing->raw_data['amenities']))
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Amenities') }}</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($listing->raw_data['amenities'] as $amenity)
                            <flux:badge color="zinc">{{ ucwords(str_replace('_', ' ', $amenity)) }}</flux:badge>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            {{-- Raw Data --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Raw Data') }}</flux:heading>
                <pre class="max-h-96 overflow-auto rounded-lg bg-zinc-100 p-4 text-xs dark:bg-zinc-800">{{ json_encode($listing->raw_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </flux:card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Price --}}
            @if (!empty($listing->raw_data['operations']))
                <flux:card>
                    @foreach ($listing->raw_data['operations'] as $operation)
                        <div class="mb-4 last:mb-0">
                            <flux:badge color="{{ $operation['type'] === 'rent' ? 'blue' : 'green' }}" class="mb-2">
                                {{ $operation['type'] === 'rent' ? __('For Rent') : __('For Sale') }}
                            </flux:badge>
                            <flux:heading size="xl">
                                ${{ number_format($operation['price'] ?? 0) }}
                                <span class="text-base font-normal text-zinc-500">{{ $operation['currency'] ?? 'MXN' }}</span>
                            </flux:heading>
                            @if (!empty($operation['maintenance_fee']))
                                <flux:subheading>+ ${{ number_format($operation['maintenance_fee']) }} {{ __('maintenance') }}</flux:subheading>
                            @endif
                        </div>
                        @if (!$loop->last)
                            <flux:separator class="my-4" />
                        @endif
                    @endforeach
                </flux:card>
            @endif

            {{-- Property Details --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>
                <dl class="space-y-3">
                    @if (!empty($listing->raw_data['property_type']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Type') }}</flux:text>
                            <flux:text>
                                {{ ucfirst($listing->raw_data['property_type']) }}
                                @if (!empty($listing->raw_data['property_subtype']))
                                    <span class="text-zinc-400">({{ ucfirst(str_replace('_', ' ', $listing->raw_data['property_subtype'])) }})</span>
                                @endif
                            </flux:text>
                        </div>
                    @endif
                    @if (!empty($listing->raw_data['bedrooms']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Bedrooms') }}</flux:text>
                            <flux:text>{{ $listing->raw_data['bedrooms'] }}</flux:text>
                        </div>
                    @endif
                    @if (!empty($listing->raw_data['bathrooms']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Bathrooms') }}</flux:text>
                            <flux:text>{{ $listing->raw_data['bathrooms'] }}{{ !empty($listing->raw_data['half_bathrooms']) ? ' + ' . $listing->raw_data['half_bathrooms'] . ' half' : '' }}</flux:text>
                        </div>
                    @endif
                    @if (!empty($listing->raw_data['parking_spots']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Parking') }}</flux:text>
                            <flux:text>{{ $listing->raw_data['parking_spots'] }} {{ __('spots') }}</flux:text>
                        </div>
                    @endif
                    @if (!empty($listing->raw_data['built_size_m2']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Built Size') }}</flux:text>
                            <flux:text>{{ number_format($listing->raw_data['built_size_m2']) }} m&sup2;</flux:text>
                        </div>
                    @endif
                    @if (!empty($listing->raw_data['lot_size_m2']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Lot Size') }}</flux:text>
                            <flux:text>{{ number_format($listing->raw_data['lot_size_m2']) }} m&sup2;</flux:text>
                        </div>
                    @endif
                    @if (!empty($listing->raw_data['age_years']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Age') }}</flux:text>
                            <flux:text>{{ $listing->raw_data['age_years'] }} {{ __('years') }}</flux:text>
                        </div>
                    @endif
                </dl>
            </flux:card>

            {{-- Location --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Location') }}</flux:heading>
                <div class="space-y-2">
                    @if (!empty($listing->raw_data['geocoded_address']))
                        <flux:text class="text-sm">{{ $listing->raw_data['geocoded_address'] }}</flux:text>
                    @elseif (!empty($listing->raw_data['address']))
                        <flux:text>{{ $listing->raw_data['address'] }}</flux:text>
                    @endif
                    <flux:subheading>
                        {{ collect([$listing->raw_data['colonia'] ?? null, $listing->raw_data['city'] ?? null, $listing->raw_data['state'] ?? null])->filter()->implode(', ') }}
                    </flux:subheading>
                    @if (!empty($listing->raw_data['latitude']) && !empty($listing->raw_data['longitude']))
                        <flux:separator class="my-3" />

                        {{-- Google Maps Preview --}}
                        @php
                            $lat = $listing->raw_data['latitude'];
                            $lng = $listing->raw_data['longitude'];
                            $mapsUrl = $listing->raw_data['google_maps_url'] ?? "https://www.google.com/maps?q={$lat},{$lng}";
                        @endphp
                        <a href="{{ $mapsUrl }}" target="_blank" class="block rounded-lg overflow-hidden hover:opacity-90 transition-opacity">
                            <img
                                src="https://maps.googleapis.com/maps/api/staticmap?center={{ $lat }},{{ $lng }}&zoom=15&size=400x200&maptype=roadmap&markers=color:red|{{ $lat }},{{ $lng }}&key={{ config('services.google.maps_api_key') }}"
                                alt="Map location"
                                class="w-full h-32 object-cover bg-zinc-100 dark:bg-zinc-800"
                                loading="lazy"
                            />
                        </a>

                        <div class="flex items-center justify-between text-xs text-zinc-500">
                            <span>{{ number_format($lat, 6) }}, {{ number_format($lng, 6) }}</span>
                            @if (!empty($listing->raw_data['geocoding_accuracy']))
                                <flux:badge size="sm" color="{{ $listing->raw_data['geocoding_accuracy'] === 'ROOFTOP' ? 'green' : 'zinc' }}">
                                    {{ $listing->raw_data['geocoding_accuracy'] }}
                                </flux:badge>
                            @endif
                        </div>

                        <flux:button
                            variant="subtle"
                            size="sm"
                            icon="arrow-top-right-on-square"
                            :href="$mapsUrl"
                            target="_blank"
                            class="w-full"
                        >
                            {{ __('Open in Google Maps') }}
                        </flux:button>
                    @else
                        <flux:callout variant="warning" icon="map-pin" class="mt-2">
                            <flux:callout.text>{{ __('No coordinates available.') }}</flux:callout.text>
                        </flux:callout>
                    @endif
                </div>
            </flux:card>

            {{-- Publisher --}}
            @if (!empty($listing->raw_data['publisher_name']))
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Publisher') }}</flux:heading>
                    <div class="flex items-start gap-3">
                        @if (!empty($listing->raw_data['publisher_logo']))
                            <flux:avatar src="{{ $listing->raw_data['publisher_logo'] }}" size="lg" />
                        @else
                            <flux:avatar icon="building-office" size="lg" />
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <flux:heading size="sm">{{ $listing->raw_data['publisher_name'] }}</flux:heading>
                                @if (!empty($listing->raw_data['publisher_url']))
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="arrow-top-right-on-square"
                                        :href="$listing->raw_data['publisher_url']"
                                        target="_blank"
                                        inset
                                    />
                                @endif
                            </div>
                            @if (!empty($listing->raw_data['publisher_type']))
                                <flux:badge size="sm" class="mt-1">{{ ucfirst($listing->raw_data['publisher_type']) }}</flux:badge>
                            @endif
                            @if (!empty($listing->raw_data['whatsapp']))
                                <a href="https://wa.me/{{ $this->formattedWhatsapp }}" target="_blank" class="mt-2 flex items-center gap-1.5 text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200">
                                    <flux:icon name="phone" variant="micro" />
                                    {{ $listing->raw_data['whatsapp'] }}
                                </a>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endif

            {{-- External Codes --}}
            @if (!empty($listing->raw_data['external_codes']) && count(array_filter($listing->raw_data['external_codes'])) > 0)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('External Codes') }}</flux:heading>
                    <dl class="space-y-2">
                        @foreach ($listing->raw_data['external_codes'] as $source => $code)
                            @if (!empty($code))
                                <div class="flex justify-between items-center">
                                    <flux:text class="text-zinc-500">{{ ucfirst($source) }}</flux:text>
                                    <flux:badge color="purple" size="sm">{{ $code }}</flux:badge>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </flux:card>
            @endif

            {{-- Meta --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Scrape Info') }}</flux:heading>
                <dl class="space-y-2">
                    <div class="flex justify-between">
                        <flux:text class="text-zinc-500">{{ __('Scraped') }}</flux:text>
                        <flux:text>{{ $listing->scraped_at?->format('M j, Y H:i') }}</flux:text>
                    </div>
                    <div class="flex justify-between">
                        <flux:text class="text-zinc-500">{{ __('Platform ID') }}</flux:text>
                        <flux:text>{{ $listing->external_id }}</flux:text>
                    </div>
                    @if (!empty($listing->raw_data['platform_metadata']['posting_id']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Posting ID') }}</flux:text>
                            <flux:text>{{ $listing->raw_data['platform_metadata']['posting_id'] }}</flux:text>
                        </div>
                    @endif
                    @if (!empty($listing->raw_data['platform_metadata']['property_type_id']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Type ID') }}</flux:text>
                            <flux:text>{{ $listing->raw_data['platform_metadata']['property_type_id'] }}</flux:text>
                        </div>
                    @endif
                    @if (!empty($listing->raw_data['images']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Images') }}</flux:text>
                            <flux:text>{{ count($listing->raw_data['images']) }}</flux:text>
                        </div>
                    @endif
                </dl>
            </flux:card>

            {{-- Processing Status --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Processing') }}</flux:heading>

                {{-- Active Processing Indicator --}}
                @if ($this->isProcessing)
                    <div class="mb-4 flex items-center gap-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3 border border-blue-200 dark:border-blue-800">
                        <flux:icon name="arrow-path" class="size-5 text-blue-600 dark:text-blue-400 animate-spin" />
                        <div>
                            <flux:text size="sm" class="font-medium text-blue-800 dark:text-blue-200">
                                {{ __('Processing in progress...') }}
                            </flux:text>
                            <flux:text size="xs" class="text-blue-600 dark:text-blue-400">
                                @if ($listing->geocode_status === null)
                                    {{ __('Waiting for geocoding') }}
                                @elseif ($listing->dedup_status->value === 'processing')
                                    {{ __('Running deduplication') }}
                                @else
                                    {{ __('Completing pipeline') }}
                                @endif
                            </flux:text>
                        </div>
                    </div>
                @endif

                {{-- Deduplication --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:text class="text-zinc-500">{{ __('Deduplication') }}</flux:text>
                        <flux:badge :color="$listing->dedup_status->color()" :icon="$listing->dedup_status->icon()">
                            {{ ucfirst(str_replace('_', ' ', $listing->dedup_status->value)) }}
                        </flux:badge>
                    </div>

                    @if ($listing->property_id)
                        <div class="flex items-center justify-between">
                            <flux:text class="text-zinc-500">{{ __('Property') }}</flux:text>
                            <div class="flex items-center gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="eye"
                                    :href="route('admin.properties.show', $listing->property_id)"
                                    wire:navigate
                                />
                                <flux:badge color="zinc">#{{ $listing->property_id }}</flux:badge>
                            </div>
                        </div>
                    @endif

                    @if ($listing->dedup_checked_at)
                        <div class="flex items-center justify-between">
                            <flux:text class="text-zinc-500">{{ __('Checked') }}</flux:text>
                            <flux:text>{{ $listing->dedup_checked_at->diffForHumans() }}</flux:text>
                        </div>
                    @endif

                    <flux:button
                        wire:click="runDeduplication"
                        wire:loading.attr="disabled"
                        :disabled="!$this->canDedup"
                        variant="primary"
                        size="sm"
                        icon="document-duplicate"
                        class="w-full"
                    >
                        <span wire:loading.remove wire:target="runDeduplication">
                            {{ $listing->dedup_status->value !== 'pending' ? __('Re-run Dedup') : __('Run Dedup') }}
                        </span>
                        <span wire:loading wire:target="runDeduplication">{{ __('Processing...') }}</span>
                    </flux:button>

                    {{-- Listing Group & Sister Listings --}}
                    @if ($listing->listingGroup)
                        <flux:separator class="my-3" />
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <flux:text size="sm" class="text-zinc-500 font-medium">
                                    {{ __('Listing Group') }}
                                </flux:text>
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" :color="$listing->listingGroup->status->color()">
                                        {{ $listing->listingGroup->status->label() }}
                                    </flux:badge>
                                    @if ($listing->listingGroup->match_score !== null)
                                        <flux:badge size="sm" :color="$listing->listingGroup->match_score >= 0.8 ? 'green' : ($listing->listingGroup->match_score >= 0.6 ? 'amber' : 'red')">
                                            {{ number_format($listing->listingGroup->match_score * 100) }}%
                                        </flux:badge>
                                    @endif
                                </div>
                            </div>

                            {{-- Current listing indicator --}}
                            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 p-2">
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" color="blue">{{ __('Current') }}</flux:badge>
                                    <flux:badge size="sm">{{ $listing->platform->name }}</flux:badge>
                                    <flux:text size="sm" class="truncate flex-1">{{ $listing->external_id }}</flux:text>
                                </div>
                            </div>

                            {{-- Sibling listings --}}
                            @if ($this->siblingListings->isNotEmpty())
                                <flux:text size="xs" class="text-zinc-400 uppercase tracking-wide">
                                    {{ __('Related Listings') }}
                                </flux:text>
                                <div class="space-y-2">
                                    @foreach ($this->siblingListings as $sibling)
                                        <a
                                            href="{{ route('admin.listings.show', $sibling) }}"
                                            wire:navigate
                                            class="flex items-center gap-2 rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                                        >
                                            <flux:badge size="sm">{{ $sibling->platform->name }}</flux:badge>
                                            <div class="min-w-0 flex-1">
                                                <flux:text size="sm" class="truncate font-medium">
                                                    {{ Str::limit($sibling->raw_data['title'] ?? $sibling->external_id, 30) }}
                                                </flux:text>
                                                @if (!empty($sibling->raw_data['operations'][0]['price']))
                                                    <flux:text size="xs" class="text-zinc-500">
                                                        ${{ number_format($sibling->raw_data['operations'][0]['price']) }}
                                                    </flux:text>
                                                @endif
                                            </div>
                                            <flux:icon name="chevron-right" class="size-4 text-zinc-400" />
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>
    </div>
</div>
