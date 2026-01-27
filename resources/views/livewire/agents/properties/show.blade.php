@use('App\Services\PropertyPresenter')

<div class="min-h-screen bg-zinc-50 pb-20 dark:bg-zinc-950 lg:pb-0">
    {{-- Sticky Header --}}
    <div class="sticky top-0 z-50 border-b border-zinc-200/80 bg-white/95 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/95">
        <div class="mx-auto flex h-14 max-w-screen-xl items-center justify-between px-4">
            <a
                href="{{ route('agents.properties.index') }}"
                wire:navigate
                class="flex items-center gap-2 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
                <flux:icon name="arrow-left" class="size-5" />
                <span class="hidden sm:inline">Volver</span>
            </a>

            {{-- Desktop Actions --}}
            <div class="hidden items-center gap-2 lg:flex">
                @can('update', $property)
                    <flux:button :href="route('agents.properties.edit', $property)" variant="ghost" size="sm" icon="pencil" wire:navigate>
                        Editar
                    </flux:button>
                @endcan
                <flux:button variant="ghost" size="sm" icon="share" disabled>
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

            {{-- Mobile Actions --}}
            <div class="flex items-center gap-1 lg:hidden">
                @can('update', $property)
                    <flux:button :href="route('agents.properties.edit', $property)" variant="ghost" size="sm" icon="pencil" wire:navigate />
                @endcan
                <flux:button variant="ghost" size="sm" icon="share" disabled />
            </div>
        </div>
    </div>

    {{-- Hero Image Gallery --}}
    <div class="mx-auto max-w-screen-xl lg:px-6 lg:pt-4">
        <div class="relative bg-zinc-900 lg:overflow-hidden lg:rounded-xl">
            <x-image-carousel
                :images="$this->images"
                :show-thumbnails="false"
                aspect-ratio="aspect-[4/3] lg:aspect-[16/10]"
                :link-to-original="true"
                class="lg:max-h-[75vh]"
            />
            @if (count($this->images) > 1)
                <div class="absolute bottom-4 right-4 rounded-full bg-black/60 px-3 py-1.5 text-sm font-medium text-white backdrop-blur-sm">
                    {{ count($this->images) }} fotos
                </div>
            @endif
        </div>
    </div>

    <div class="mx-auto max-w-screen-xl px-4 pt-6 sm:px-6">
        <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
            {{-- Main Content --}}
            <div class="lg:col-span-2">
                {{-- Price & Location --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="space-y-2">
                            @if ($this->primaryPrice)
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 sm:text-3xl">
                                        {{ PropertyPresenter::formatPrice($this->primaryPrice) }}
                                    </span>
                                    <flux:badge size="sm" :color="PropertyPresenter::operationTypeBadgeColor($this->primaryPrice['type'])">
                                        {{ PropertyPresenter::operationTypeLabel($this->primaryPrice['type']) }}
                                    </flux:badge>
                                </div>
                                @if ($this->primaryPrice['maintenance_fee'])
                                    <p class="text-sm text-zinc-500">
                                        {{ PropertyPresenter::formatMaintenanceFee($this->primaryPrice['maintenance_fee']) }}
                                    </p>
                                @endif

                                @if ($this->pricingDetails && ! empty($this->pricingDetails['included_services']))
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

                            <div class="flex items-center gap-2 text-lg text-zinc-600 dark:text-zinc-400">
                                <flux:icon name="map-pin" class="size-5 shrink-0" />
                                <span class="font-medium">
                                    {{ $property->colonia }}@if ($property->city), {{ $property->city }}@endif
                                </span>
                            </div>
                        </div>

                        @if ($this->pricePerM2)
                            <div class="rounded-lg bg-zinc-100 px-3 py-2 dark:bg-zinc-800">
                                <p class="text-xs text-zinc-500">Precio por m²</p>
                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ PropertyPresenter::formatPricePerM2($this->pricePerM2) }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Key Stats --}}
                    <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3 text-zinc-600 dark:text-zinc-400">
                        @if ($property->bedrooms)
                            <div class="flex items-center gap-2">
                                {!! PropertyPresenter::bedroomIcon() !!}
                                <span class="font-medium">{{ PropertyPresenter::formatBedrooms($property->bedrooms, abbrev: true) }}</span>
                            </div>
                        @endif
                        @if ($property->bathrooms)
                            <div class="flex items-center gap-2">
                                {!! PropertyPresenter::bathroomIcon() !!}
                                <span class="font-medium">{{ PropertyPresenter::formatBathrooms($property->bathrooms, abbrev: true) }}</span>
                            </div>
                        @endif
                        @if ($property->half_bathrooms)
                            <div class="flex items-center gap-2">
                                {!! PropertyPresenter::bathroomIcon() !!}
                                <span class="font-medium">{{ PropertyPresenter::formatHalfBathrooms($property->half_bathrooms) }}</span>
                            </div>
                        @endif
                        @if ($property->parking_spots)
                            <div class="flex items-center gap-2">
                                {!! PropertyPresenter::parkingIcon() !!}
                                <span class="font-medium">{{ PropertyPresenter::formatParking($property->parking_spots, abbrev: true) }}</span>
                            </div>
                        @endif
                        @if ($property->built_size_m2)
                            <div class="flex items-center gap-2">
                                {!! PropertyPresenter::sizeIcon() !!}
                                <span class="font-medium">{{ PropertyPresenter::formatBuiltSize($property->built_size_m2) }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Property Type & Details --}}
                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-500">
                        @if ($property->property_type)
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ PropertyPresenter::propertyTypeLabel($property->property_type) }}</span>
                        @endif
                        @if ($property->lot_size_m2)
                            <span>{{ PropertyPresenter::formatLotSize($property->lot_size_m2) }}</span>
                        @endif
                        @if ($property->age_years !== null)
                            <span>{{ PropertyPresenter::formatAge($property->age_years) }}</span>
                        @endif
                    </div>

                    {{-- Top Amenities --}}
                    @if (count($this->topAmenities) > 0)
                        <div class="mt-5 flex flex-wrap gap-2">
                            @foreach ($this->topAmenities as $amenity)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    <flux:icon name="check" class="size-3.5 text-green-500" />
                                    {{ PropertyPresenter::humanizeAmenity($amenity) }}
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

                    {{-- Amenities --}}
                    @if ($this->categorizedAmenities || count($this->amenities) > 0)
                        @if ($this->categorizedAmenities)
                            @php
                                $unitAmenities = $this->categorizedAmenities['unit'] ?? $this->categorizedAmenities['in_unit'] ?? [];
                                $buildingAmenities = $this->categorizedAmenities['building'] ?? [];
                                $servicesAmenities = $this->categorizedAmenities['services'] ?? [];
                                $extraAmenities = $this->categorizedAmenities['optional'] ?? $this->categorizedAmenities['available_extra'] ?? [];
                            @endphp

                            <div class="space-y-4">
                                {{-- Unit Amenities --}}
                                @if (! empty($unitAmenities))
                                    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
                                            <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                                <flux:icon name="home" variant="mini" class="size-4 text-zinc-600 dark:text-zinc-400" />
                                            </div>
                                            <h3 class="font-medium text-zinc-900 dark:text-zinc-100">En la unidad</h3>
                                        </div>
                                        <div class="flex flex-wrap gap-2 p-4">
                                            @foreach ($unitAmenities as $amenity)
                                                @php $amenityName = is_array($amenity) ? $amenity['name'] : $amenity; @endphp
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                    <flux:icon :name="PropertyPresenter::getAmenityHeroicon($amenityName)" variant="mini" class="size-4 text-zinc-500 dark:text-zinc-400" />
                                                    {{ PropertyPresenter::humanizeAmenity($amenityName) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Building Amenities --}}
                                @if (! empty($buildingAmenities))
                                    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
                                            <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                                <flux:icon name="building-office" variant="mini" class="size-4 text-zinc-600 dark:text-zinc-400" />
                                            </div>
                                            <h3 class="font-medium text-zinc-900 dark:text-zinc-100">Del edificio</h3>
                                        </div>
                                        <div class="flex flex-wrap gap-2 p-4">
                                            @foreach ($buildingAmenities as $amenity)
                                                @php $amenityName = is_array($amenity) ? $amenity['name'] : $amenity; @endphp
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                    <flux:icon :name="PropertyPresenter::getAmenityHeroicon($amenityName)" variant="mini" class="size-4 text-zinc-500 dark:text-zinc-400" />
                                                    {{ PropertyPresenter::humanizeAmenity($amenityName) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Services --}}
                                @if (! empty($servicesAmenities))
                                    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
                                            <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                                <flux:icon name="shield-check" variant="mini" class="size-4 text-zinc-600 dark:text-zinc-400" />
                                            </div>
                                            <h3 class="font-medium text-zinc-900 dark:text-zinc-100">Servicios</h3>
                                        </div>
                                        <div class="flex flex-wrap gap-2 p-4">
                                            @foreach ($servicesAmenities as $amenity)
                                                @php $amenityName = is_array($amenity) ? $amenity['name'] : $amenity; @endphp
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                                    <flux:icon :name="PropertyPresenter::getAmenityHeroicon($amenityName)" variant="mini" class="size-4 text-zinc-500 dark:text-zinc-400" />
                                                    {{ PropertyPresenter::humanizeAmenity($amenityName) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Extra Cost --}}
                                @if (! empty($extraAmenities))
                                    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
                                            <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-200 dark:bg-zinc-700">
                                                <flux:icon name="plus-circle" variant="mini" class="size-4 text-zinc-600 dark:text-zinc-400" />
                                            </div>
                                            <h3 class="font-medium text-zinc-900 dark:text-zinc-100">Costo extra</h3>
                                        </div>
                                        <div class="flex flex-wrap gap-2 p-4">
                                            @foreach ($extraAmenities as $amenity)
                                                @php
                                                    $amenityName = is_array($amenity) ? $amenity['name'] : $amenity;
                                                    $amenityPrice = is_array($amenity) ? ($amenity['price'] ?? null) : null;
                                                @endphp
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                                                    <flux:icon :name="PropertyPresenter::getAmenityHeroicon($amenityName)" variant="mini" class="size-4 text-zinc-400" />
                                                    {{ PropertyPresenter::humanizeAmenity($amenityName) }}
                                                    @if ($amenityPrice)
                                                        <span class="ml-1 text-xs text-zinc-500">({{ $amenityPrice }})</span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            {{-- Flat List Fallback --}}
                            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
                                <flux:heading size="lg" class="mb-4">Que ofrece este lugar</flux:heading>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->amenities as $amenity)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                            <flux:icon :name="PropertyPresenter::getAmenityHeroicon($amenity)" variant="mini" class="size-4 text-zinc-500" />
                                            {{ PropertyPresenter::humanizeAmenity($amenity) }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- Rental Terms (Mobile) --}}
                    @if ($this->primaryPrice && $this->primaryPrice['type'] === 'rent' && $this->hasRentalTermsData)
                        <div class="lg:hidden">
                            @include('livewire.agents.properties.partials.rental-terms', [
                                'rentalTerms' => $this->rentalTerms,
                                'variant' => 'mobile',
                            ])
                        </div>
                    @endif

                    {{-- Building Info (Mobile) --}}
                    <div class="lg:hidden">
                        @include('livewire.agents.properties.partials.building-info', [
                            'buildingInfo' => $this->buildingInfo,
                            'buildingAmenities' => $this->categorizedAmenities['building'] ?? [],
                            'variant' => 'mobile',
                        ])
                    </div>

                    {{-- Good to Know (Mobile) --}}
                    <div class="lg:hidden">
                        @include('livewire.agents.properties.partials.good-to-know', [
                            'propertyInsights' => $this->propertyInsights,
                            'variant' => 'mobile',
                        ])
                    </div>

                    {{-- Location Map (Mobile) --}}
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
                @include('livewire.agents.properties.partials.building-info', [
                    'buildingInfo' => $this->buildingInfo,
                    'buildingAmenities' => $this->categorizedAmenities['building'] ?? [],
                    'variant' => 'desktop',
                ])

                @if ($this->primaryPrice && $this->primaryPrice['type'] === 'rent' && $this->hasRentalTermsData)
                    @include('livewire.agents.properties.partials.rental-terms', [
                        'rentalTerms' => $this->rentalTerms,
                        'variant' => 'desktop',
                    ])
                @endif

                @include('livewire.agents.properties.partials.good-to-know', [
                    'propertyInsights' => $this->propertyInsights,
                    'variant' => 'desktop',
                ])

                @include('livewire.agents.properties.partials.location-map', [
                    'property' => $property,
                    'variant' => 'desktop',
                ])

                @include('livewire.agents.properties.partials.contact-card', [
                    'publishers' => $this->publishers,
                    'variant' => 'desktop',
                ])
            </div>
        </div>

        {{-- Contact Card (Mobile) --}}
        <div class="mt-6 lg:hidden">
            @include('livewire.agents.properties.partials.contact-card', [
                'publishers' => $this->publishers,
                'variant' => 'mobile',
            ])
        </div>
    </div>

    {{-- Mobile Action Bar --}}
    <div class="fixed bottom-0 left-0 right-0 z-50 border-t border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/95 lg:hidden">
        <div class="flex gap-3">
            @can('update', $property)
                <flux:button :href="route('agents.properties.edit', $property)" variant="outline" icon="pencil" wire:navigate>
                    Editar
                </flux:button>
            @endcan
            <flux:button
                wire:click="toggleCollection"
                :variant="$this->isInCollection() ? 'primary' : 'filled'"
                class="flex-1"
                :icon="$this->isInCollection() ? 'check' : 'plus'"
            >
                {{ $this->isInCollection() ? 'En tu coleccion' : 'Agregar a coleccion' }}
            </flux:button>
            <flux:button variant="outline" icon="share" disabled>
                Compartir
            </flux:button>
        </div>
    </div>
</div>
