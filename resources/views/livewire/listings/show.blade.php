<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex items-start gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('listings.index')" wire:navigate />
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ $listing->raw_data['title'] ?? 'Listing Details' }}</flux:heading>
                <flux:badge>{{ $listing->platform->name }}</flux:badge>
            </div>
            <flux:subheading>{{ $listing->external_id }}</flux:subheading>
        </div>
        <flux:button icon="arrow-top-right-on-square" :href="$listing->original_url" target="_blank">
            {{ __('View Original') }}
        </flux:button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main Content --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Images --}}
            @if (!empty($this->images))
                <flux:card x-data="{ currentImage: 0, images: {{ json_encode($this->images) }} }">
                    {{-- Image Grid --}}
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach (array_slice($this->images, 0, 6) as $index => $imageUrl)
                            <button
                                type="button"
                                x-on:click="currentImage = {{ $index }}; $flux.modal('image-gallery').show()"
                                class="aspect-video overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <img src="{{ $imageUrl }}" alt="Property image" class="h-full w-full object-cover hover:scale-105 transition-transform" loading="lazy" />
                            </button>
                        @endforeach
                    </div>

                    @if (count($this->images) > 6)
                        <flux:button
                            variant="ghost"
                            class="mt-2 w-full"
                            x-on:click="currentImage = 6; $flux.modal('image-gallery').show()"
                        >
                            +{{ count($this->images) - 6 }} {{ __('more images') }}
                        </flux:button>
                    @endif

                    {{-- Single Lightbox Modal --}}
                    <flux:modal name="image-gallery" class="max-w-4xl bg-black/95">
                        <div class="relative flex flex-col items-center">
                            {{-- Main Image --}}
                            <img
                                x-bind:src="images[currentImage]"
                                alt="Property image"
                                class="max-h-[70vh] w-auto object-contain"
                            />

                            {{-- Navigation --}}
                            <div class="mt-4 flex items-center gap-4">
                                <flux:button
                                    variant="ghost"
                                    icon="chevron-left"
                                    x-on:click="currentImage = currentImage > 0 ? currentImage - 1 : images.length - 1"
                                />
                                <flux:text class="tabular-nums">
                                    <span x-text="currentImage + 1"></span> / <span x-text="images.length"></span>
                                </flux:text>
                                <flux:button
                                    variant="ghost"
                                    icon="chevron-right"
                                    x-on:click="currentImage = currentImage < images.length - 1 ? currentImage + 1 : 0"
                                />
                            </div>
                        </div>
                    </flux:modal>
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
                    @if (!empty($listing->raw_data['address']))
                        <flux:text>{{ $listing->raw_data['address'] }}</flux:text>
                    @endif
                    <flux:subheading>
                        {{ collect([$listing->raw_data['colonia'] ?? null, $listing->raw_data['city'] ?? null, $listing->raw_data['state'] ?? null])->filter()->implode(', ') }}
                    </flux:subheading>
                    @if (!empty($listing->raw_data['latitude']) && !empty($listing->raw_data['longitude']))
                        <flux:separator class="my-3" />
                        <div class="flex items-center gap-2">
                            <flux:icon name="map-pin" variant="mini" class="text-zinc-400" />
                            <flux:subheading>{{ $listing->raw_data['latitude'] }}, {{ $listing->raw_data['longitude'] }}</flux:subheading>
                        </div>
                        <flux:button
                            variant="subtle"
                            size="sm"
                            icon="arrow-top-right-on-square"
                            :href="'https://www.google.com/maps?q=' . $listing->raw_data['latitude'] . ',' . $listing->raw_data['longitude']"
                            target="_blank"
                            class="w-full"
                        >
                            {{ __('View on Google Maps') }}
                        </flux:button>
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
                            <flux:heading size="sm">{{ $listing->raw_data['publisher_name'] }}</flux:heading>
                            @if (!empty($listing->raw_data['publisher_type']))
                                <flux:badge size="sm" class="mt-1">{{ ucfirst($listing->raw_data['publisher_type']) }}</flux:badge>
                            @endif
                        </div>
                    </div>
                    @if (!empty($listing->raw_data['whatsapp']) || !empty($listing->raw_data['publisher_url']))
                        <flux:separator class="my-4" />
                        <div class="space-y-2">
                            @if (!empty($listing->raw_data['whatsapp']))
                                <flux:button
                                    variant="subtle"
                                    size="sm"
                                    icon="phone"
                                    :href="'https://wa.me/' . $this->formattedWhatsapp"
                                    target="_blank"
                                    class="w-full"
                                >
                                    {{ $listing->raw_data['whatsapp'] }}
                                </flux:button>
                            @endif
                            @if (!empty($listing->raw_data['publisher_url']))
                                <flux:button
                                    variant="subtle"
                                    size="sm"
                                    icon="arrow-top-right-on-square"
                                    :href="$listing->raw_data['publisher_url']"
                                    target="_blank"
                                    class="w-full"
                                >
                                    {{ __('View Profile') }}
                                </flux:button>
                            @endif
                        </div>
                    @endif
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
        </div>
    </div>
</div>
