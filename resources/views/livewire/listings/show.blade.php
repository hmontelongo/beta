<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex items-start gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('listings.index')" wire:navigate />
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ $listing->raw_data['title'] ?? 'Listing Details' }}</flux:heading>
                <flux:badge>{{ $listing->platform->name }}</flux:badge>
            </div>
            <flux:text class="mt-1">{{ $listing->external_id }}</flux:text>
        </div>
        <flux:button icon="arrow-top-right-on-square" :href="$listing->original_url" target="_blank">
            {{ __('View Original') }}
        </flux:button>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main Content --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Images --}}
            @if (!empty($listing->raw_data['images']))
                <flux:card>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach (array_slice($listing->raw_data['images'], 0, 6) as $image)
                            <a href="{{ $image['url'] }}" target="_blank" class="aspect-video overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                <img
                                    src="{{ $image['url'] }}"
                                    alt="Property image"
                                    class="h-full w-full object-cover transition-transform hover:scale-105"
                                    loading="lazy"
                                />
                            </a>
                        @endforeach
                    </div>
                    @if (count($listing->raw_data['images']) > 6)
                        <flux:text size="sm" class="mt-2 text-center">
                            +{{ count($listing->raw_data['images']) - 6 }} {{ __('more images') }}
                        </flux:text>
                    @endif
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
                        <div class="mb-2 last:mb-0">
                            <flux:badge color="{{ $operation['type'] === 'rent' ? 'blue' : 'green' }}" class="mb-2">
                                {{ $operation['type'] === 'rent' ? __('For Rent') : __('For Sale') }}
                            </flux:badge>
                            <flux:heading size="xl">
                                ${{ number_format($operation['price'] ?? 0) }}
                                <span class="text-base font-normal text-zinc-500">{{ $operation['currency'] ?? 'MXN' }}</span>
                            </flux:heading>
                            @if (!empty($operation['maintenance_fee']))
                                <flux:text size="sm">+ ${{ number_format($operation['maintenance_fee']) }} {{ __('maintenance') }}</flux:text>
                            @endif
                        </div>
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
                            <flux:text>{{ ucfirst($listing->raw_data['property_type']) }}</flux:text>
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
                <dl class="space-y-2">
                    @if (!empty($listing->raw_data['address']))
                        <flux:text>{{ $listing->raw_data['address'] }}</flux:text>
                    @endif
                    <flux:text class="text-zinc-500">
                        {{ collect([$listing->raw_data['colonia'] ?? null, $listing->raw_data['city'] ?? null, $listing->raw_data['state'] ?? null])->filter()->implode(', ') }}
                    </flux:text>
                </dl>
            </flux:card>

            {{-- Publisher --}}
            @if (!empty($listing->raw_data['publisher_name']))
                <flux:card>
                    <flux:heading size="lg" class="mb-4">{{ __('Publisher') }}</flux:heading>
                    <div class="flex items-center gap-3">
                        @if (!empty($listing->raw_data['publisher_logo']))
                            <img src="{{ $listing->raw_data['publisher_logo'] }}" alt="" class="h-10 w-10 rounded-lg object-contain" />
                        @endif
                        <div>
                            <flux:heading size="sm">{{ $listing->raw_data['publisher_name'] }}</flux:heading>
                            @if (!empty($listing->raw_data['whatsapp']))
                                <flux:text size="sm">{{ $listing->raw_data['whatsapp'] }}</flux:text>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endif

            {{-- Meta --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Scrape Info') }}</flux:heading>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <flux:text class="text-zinc-500">{{ __('Scraped') }}</flux:text>
                        <flux:text>{{ $listing->scraped_at?->format('M j, Y H:i') }}</flux:text>
                    </div>
                    <div class="flex justify-between">
                        <flux:text class="text-zinc-500">{{ __('Platform ID') }}</flux:text>
                        <flux:text>{{ $listing->external_id }}</flux:text>
                    </div>
                    @if (!empty($listing->raw_data['platform_metadata']['days_published']))
                        <div class="flex justify-between">
                            <flux:text class="text-zinc-500">{{ __('Days Published') }}</flux:text>
                            <flux:text>{{ $listing->raw_data['platform_metadata']['days_published'] }}</flux:text>
                        </div>
                    @endif
                </dl>
            </flux:card>
        </div>
    </div>
</div>
