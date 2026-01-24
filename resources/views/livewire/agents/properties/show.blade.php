@use('App\Services\CollectionPropertyPresenter')
<div class="min-h-screen bg-zinc-50 pb-20 dark:bg-zinc-950 lg:pb-0">
    {{-- Minimal Sticky Header --}}
    <div class="sticky top-0 z-50 border-b border-zinc-200/80 bg-white/95 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/95">
        <div class="mx-auto flex h-14 max-w-screen-xl items-center justify-between px-4">
            <a href="{{ route('agents.properties.index') }}" wire:navigate
               class="flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                <flux:icon name="arrow-left" class="size-5" />
                <span class="hidden sm:inline">Volver</span>
            </a>

            {{-- Desktop Actions --}}
            <div class="hidden items-center gap-2 lg:flex">
                <flux:button variant="ghost" size="sm" icon="share">
                    Compartir
                </flux:button>
                <flux:button
                    wire:click="toggleCollection"
                    :variant="$this->isInCollection() ? 'primary' : 'filled'"
                    size="sm"
                    :icon="$this->isInCollection() ? 'check' : 'plus'"
                >
                    {{ $this->isInCollection() ? 'En coleccion' : 'Agregar' }}
                </flux:button>
            </div>

            {{-- Mobile: Just icons --}}
            <div class="flex items-center gap-1 lg:hidden">
                <flux:button variant="ghost" size="sm" icon="share" />
            </div>
        </div>
    </div>

    {{-- Hero Image Gallery - Immersive --}}
    <div class="relative bg-zinc-900">
        <x-image-carousel
            :images="$this->images"
            :show-thumbnails="false"
            aspect-ratio="aspect-[4/3] lg:aspect-[16/10]"
            :link-to-original="true"
            class="lg:max-h-[75vh]"
        />
        {{-- Image Counter Overlay --}}
        @if(count($this->images) > 1)
            <div class="absolute bottom-4 right-4 rounded-full bg-black/60 px-3 py-1.5 text-sm font-medium text-white backdrop-blur-sm">
                {{ count($this->images) }} fotos
            </div>
        @endif
    </div>

    <div class="mx-auto max-w-screen-xl px-4 pt-6 sm:px-6">
        <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
            {{-- Main Content Column --}}
            <div class="lg:col-span-2">
                {{-- Price & Location Hero Section --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="space-y-2">
                            {{-- Price --}}
                            @if ($this->primaryPrice)
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 sm:text-3xl">
                                        ${{ number_format($this->primaryPrice['price']) }}
                                    </span>
                                    @if ($this->primaryPrice['type'] === 'rent')
                                        <span class="text-lg text-zinc-500">/mes</span>
                                    @endif
                                    <flux:badge size="sm" :color="$this->primaryPrice['type'] === 'rent' ? 'blue' : 'green'">
                                        {{ $this->primaryPrice['type'] === 'rent' ? 'Renta' : 'Venta' }}
                                    </flux:badge>
                                </div>
                                @if ($this->primaryPrice['maintenance_fee'])
                                    <p class="text-sm text-zinc-500">
                                        + ${{ number_format($this->primaryPrice['maintenance_fee']) }} mantenimiento
                                    </p>
                                @endif

                                {{-- Included Services as green badges --}}
                                @if ($this->pricingDetails && !empty($this->pricingDetails['included_services']))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($this->pricingDetails['included_services'] as $service)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-1 text-xs text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                <flux:icon name="check" class="size-3" />
                                                {{ ucfirst($service['service'] ?? $service) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            @endif

                            {{-- Location --}}
                            <div class="flex items-center gap-2 text-lg text-zinc-600 dark:text-zinc-400">
                                <flux:icon name="map-pin" class="size-5 shrink-0" />
                                <span class="font-medium">
                                    {{ $property->colonia }}@if($property->city), {{ $property->city }}@endif
                                </span>
                            </div>
                        </div>

                        {{-- Price per m2 --}}
                        @if ($this->pricePerM2)
                            <div class="rounded-lg bg-zinc-100 px-3 py-2 dark:bg-zinc-800">
                                <p class="text-xs text-zinc-500">Precio por m²</p>
                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">${{ number_format($this->pricePerM2) }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Key Stats Row --}}
                    <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3 text-zinc-600 dark:text-zinc-400">
                        @if ($property->bedrooms)
                            <div class="flex items-center gap-2">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                                <span class="font-medium">{{ $property->bedrooms }} rec</span>
                            </div>
                        @endif
                        @if ($property->bathrooms)
                            <div class="flex items-center gap-2">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="font-medium">{{ $property->bathrooms }} banos</span>
                            </div>
                        @endif
                        @if ($property->parking_spots)
                            <div class="flex items-center gap-2">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                </svg>
                                <span class="font-medium">{{ $property->parking_spots }} est</span>
                            </div>
                        @endif
                        @if ($property->built_size_m2)
                            <div class="flex items-center gap-2">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                </svg>
                                <span class="font-medium">{{ number_format($property->built_size_m2) }} m²</span>
                            </div>
                        @endif
                    </div>

                    {{-- Property Type & Lot Size --}}
                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-500">
                        @if ($property->property_type)
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ ucfirst($property->property_type->value) }}</span>
                        @endif
                        @if ($property->lot_size_m2)
                            <span>Terreno: {{ number_format($property->lot_size_m2) }} m²</span>
                        @endif
                        @if ($property->age_years)
                            <span>{{ $property->age_years }} {{ $property->age_years === 1 ? 'ano' : 'anos' }}</span>
                        @endif
                    </div>

                    {{-- Top Amenity Badges --}}
                    @if (count($this->topAmenities) > 0)
                        <div class="mt-5 flex flex-wrap gap-2">
                            @foreach ($this->topAmenities as $amenity)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    <flux:icon name="check" class="size-3.5 text-green-500" />
                                    {{ CollectionPropertyPresenter::humanizeAmenity($amenity) }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Content Sections --}}
                <div class="mt-6 space-y-6">
                    {{-- Description --}}
                    @if ($this->description)
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg">Descripcion</flux:heading>
                                @if ($this->descriptionWithSource['source'] === 'ai')
                                    <flux:badge size="xs" color="blue">AI</flux:badge>
                                @endif
                            </div>
                            <flux:separator class="my-4" />
                            <div x-data="{ expanded: false }" class="relative">
                                <div
                                    x-bind:class="{ 'max-h-32 overflow-hidden': !expanded }"
                                    class="prose prose-sm max-w-none text-zinc-600 dark:prose-invert dark:text-zinc-400"
                                >
                                    @if ($this->descriptionWithSource['source'] === 'ai')
                                        {!! $this->description !!}
                                    @else
                                        {!! nl2br(e($this->description)) !!}
                                    @endif
                                </div>
                                @if (strlen($this->description) > 250)
                                    <div
                                        x-show="!expanded"
                                        class="pointer-events-none absolute bottom-0 left-0 right-0 h-12 bg-gradient-to-t from-white to-transparent dark:from-zinc-900"
                                    ></div>
                                    <button
                                        x-on:click="expanded = !expanded"
                                        class="mt-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                    >
                                        <span x-text="expanded ? 'Mostrar menos' : 'Leer mas'"></span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Categorized Amenities - Airbnb-style mini-cards --}}
                    @if ($this->categorizedAmenities || count($this->amenities) > 0)
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                            <flux:heading size="lg" class="mb-4">Que ofrece este lugar</flux:heading>

                            @if ($this->categorizedAmenities)
                                {{-- Categorized View - Simple Lists --}}
                                <div class="grid gap-6 sm:grid-cols-2">
                                    @if (!empty($this->categorizedAmenities['unit'] ?? $this->categorizedAmenities['in_unit'] ?? []))
                                        <div>
                                            <flux:text size="xs" class="mb-2 font-semibold uppercase tracking-wide text-zinc-500">En la unidad</flux:text>
                                            <div class="space-y-2">
                                                @foreach ($this->categorizedAmenities['unit'] ?? $this->categorizedAmenities['in_unit'] ?? [] as $amenity)
                                                    <div class="flex items-center gap-2">
                                                        <flux:icon name="check" class="size-4 text-green-500" />
                                                        <flux:text size="sm">{{ CollectionPropertyPresenter::humanizeAmenity(is_array($amenity) ? $amenity['name'] : $amenity) }}</flux:text>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if (!empty($this->categorizedAmenities['building']))
                                        <div>
                                            <flux:text size="xs" class="mb-2 font-semibold uppercase tracking-wide text-zinc-500">Del edificio</flux:text>
                                            <div class="space-y-2">
                                                @foreach ($this->categorizedAmenities['building'] as $amenity)
                                                    <div class="flex items-center gap-2">
                                                        <flux:icon name="check" class="size-4 text-green-500" />
                                                        <flux:text size="sm">{{ CollectionPropertyPresenter::humanizeAmenity(is_array($amenity) ? $amenity['name'] : $amenity) }}</flux:text>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if (!empty($this->categorizedAmenities['services']))
                                        <div>
                                            <flux:text size="xs" class="mb-2 font-semibold uppercase tracking-wide text-zinc-500">Servicios</flux:text>
                                            <div class="space-y-2">
                                                @foreach ($this->categorizedAmenities['services'] as $amenity)
                                                    <div class="flex items-center gap-2">
                                                        <flux:icon name="check" class="size-4 text-blue-500" />
                                                        <flux:text size="sm">{{ CollectionPropertyPresenter::humanizeAmenity(is_array($amenity) ? $amenity['name'] : $amenity) }}</flux:text>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if (!empty($this->categorizedAmenities['optional'] ?? $this->categorizedAmenities['available_extra'] ?? []))
                                        <div>
                                            <flux:text size="xs" class="mb-2 font-semibold uppercase tracking-wide text-zinc-500">Costo extra</flux:text>
                                            <div class="space-y-2">
                                                @foreach ($this->categorizedAmenities['optional'] ?? $this->categorizedAmenities['available_extra'] ?? [] as $amenity)
                                                    <div class="flex items-center gap-2">
                                                        <flux:icon name="plus" class="size-4 text-zinc-400" />
                                                        <flux:text size="sm" class="text-zinc-500">
                                                            {{ CollectionPropertyPresenter::humanizeAmenity(is_array($amenity) ? $amenity['name'] : $amenity) }}
                                                            @if (is_array($amenity) && !empty($amenity['price']))
                                                                <span class="text-xs">({{ $amenity['price'] }})</span>
                                                            @endif
                                                        </flux:text>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                {{-- Flat List Fallback --}}
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($this->amenities as $amenity)
                                        <div class="flex items-center gap-2">
                                            <flux:icon name="check" class="size-4 text-green-500" />
                                            <flux:text size="sm">{{ CollectionPropertyPresenter::humanizeAmenity($amenity) }}</flux:text>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Rental Terms (if rent type and has actual data) - Mobile --}}
                    @if ($this->primaryPrice && $this->primaryPrice['type'] === 'rent' && $this->hasRentalTermsData)
                        <div class="lg:hidden">
                            @include('livewire.agents.properties.partials.rental-terms', [
                                'rentalTerms' => $this->rentalTerms,
                                'variant' => 'mobile',
                            ])
                        </div>
                    @endif

                    {{-- About This Building (Mobile only - desktop shows in sidebar) --}}
                    <div class="lg:hidden">
                        @include('livewire.agents.properties.partials.building-info', [
                            'buildingInfo' => $this->buildingInfo,
                            'variant' => 'mobile',
                        ])
                    </div>

                    {{-- Good to Know (Mobile only - desktop shows in sidebar) --}}
                    <div class="lg:hidden">
                        @include('livewire.agents.properties.partials.good-to-know', [
                            'propertyInsights' => $this->propertyInsights,
                            'variant' => 'mobile',
                        ])
                    </div>

                    {{-- Location / Map (Mobile only - desktop shows in sidebar) --}}
                    <div class="lg:hidden">
                        @include('livewire.agents.properties.partials.location-map', [
                            'property' => $property,
                            'variant' => 'mobile',
                        ])
                    </div>
                </div>
            </div>

            {{-- Sidebar (Desktop) --}}
            <div class="hidden space-y-5 lg:block">
                {{-- About This Building (Desktop sidebar) --}}
                @include('livewire.agents.properties.partials.building-info', [
                    'buildingInfo' => $this->buildingInfo,
                    'variant' => 'desktop',
                ])

                {{-- Rental Terms (Desktop sidebar) --}}
                @if ($this->primaryPrice && $this->primaryPrice['type'] === 'rent' && $this->hasRentalTermsData)
                    @include('livewire.agents.properties.partials.rental-terms', [
                        'rentalTerms' => $this->rentalTerms,
                        'variant' => 'desktop',
                    ])
                @endif

                {{-- Good to Know (Desktop sidebar) --}}
                @include('livewire.agents.properties.partials.good-to-know', [
                    'propertyInsights' => $this->propertyInsights,
                    'variant' => 'desktop',
                ])

                {{-- Location / Map (Desktop sidebar) --}}
                @include('livewire.agents.properties.partials.location-map', [
                    'property' => $property,
                    'variant' => 'desktop',
                ])

                {{-- Contact Card --}}
                @include('livewire.agents.properties.partials.contact-card', [
                    'publishers' => $this->publishers,
                    'variant' => 'desktop',
                ])
            </div>
        </div>

        {{-- Mobile: Contact Only (shown below main content) --}}
        <div class="mt-6 lg:hidden">
            @include('livewire.agents.properties.partials.contact-card', [
                'publishers' => $this->publishers,
                'variant' => 'mobile',
            ])
        </div>
    </div>

    {{-- Sticky Bottom Action Bar (Mobile Only) --}}
    <div class="fixed bottom-0 left-0 right-0 z-50 border-t border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/95 lg:hidden">
        <div class="flex gap-3">
            <flux:button
                wire:click="toggleCollection"
                :variant="$this->isInCollection() ? 'primary' : 'filled'"
                class="flex-1"
                :icon="$this->isInCollection() ? 'check' : 'plus'"
            >
                {{ $this->isInCollection() ? 'En tu coleccion' : 'Agregar a coleccion' }}
            </flux:button>
            <flux:button variant="outline" icon="share">
                Compartir
            </flux:button>
        </div>
    </div>
</div>
