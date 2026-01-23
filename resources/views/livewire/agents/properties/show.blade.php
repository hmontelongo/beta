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
                                    {{ $this->humanizeAmenity($amenity) }}
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
                            <div class="mb-1 flex items-center gap-2">
                                <flux:heading size="lg">Descripcion</flux:heading>
                                @if ($this->descriptionWithSource['source'] === 'ai')
                                    <flux:badge size="xs" color="blue">AI</flux:badge>
                                @endif
                            </div>
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
                                                        <flux:text size="sm">{{ $this->humanizeAmenity(is_array($amenity) ? $amenity['name'] : $amenity) }}</flux:text>
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
                                                        <flux:text size="sm">{{ $this->humanizeAmenity(is_array($amenity) ? $amenity['name'] : $amenity) }}</flux:text>
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
                                                        <flux:text size="sm">{{ $this->humanizeAmenity(is_array($amenity) ? $amenity['name'] : $amenity) }}</flux:text>
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
                                                            {{ $this->humanizeAmenity(is_array($amenity) ? $amenity['name'] : $amenity) }}
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
                                            <flux:text size="sm">{{ $this->humanizeAmenity($amenity) }}</flux:text>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Rental Terms (if rent type and has actual data) --}}
                    @php
                        $hasRentalTermsData = $this->rentalTerms && (
                            !empty($this->rentalTerms['deposit_months']) ||
                            !empty($this->rentalTerms['advance_months']) ||
                            !empty($this->rentalTerms['income_proof_months']) ||
                            isset($this->rentalTerms['pets_allowed']) ||
                            isset($this->rentalTerms['guarantor_required']) ||
                            !empty($this->rentalTerms['max_occupants'])
                        );
                    @endphp
                    @if ($this->primaryPrice && $this->primaryPrice['type'] === 'rent' && $hasRentalTermsData)
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6 lg:hidden">
                            <flux:heading size="lg" class="mb-4">Condiciones de renta</flux:heading>
                            <div class="grid grid-cols-3 gap-4">
                                @if (!empty($this->rentalTerms['deposit_months']))
                                    <div class="rounded-lg bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                                        <p class="text-xs text-zinc-500">Deposito</p>
                                        <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->rentalTerms['deposit_months'] }}</p>
                                        <p class="text-xs text-zinc-500">{{ $this->rentalTerms['deposit_months'] === 1 ? 'mes' : 'meses' }}</p>
                                    </div>
                                @endif
                                @if (!empty($this->rentalTerms['advance_months']))
                                    <div class="rounded-lg bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                                        <p class="text-xs text-zinc-500">Adelanto</p>
                                        <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->rentalTerms['advance_months'] }}</p>
                                        <p class="text-xs text-zinc-500">{{ $this->rentalTerms['advance_months'] === 1 ? 'mes' : 'meses' }}</p>
                                    </div>
                                @endif
                                @if (!empty($this->rentalTerms['income_proof_months']))
                                    <div class="rounded-lg bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                                        <p class="text-xs text-zinc-500">Comprobante</p>
                                        <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->rentalTerms['income_proof_months'] }}x</p>
                                        <p class="text-xs text-zinc-500">ingresos</p>
                                    </div>
                                @endif
                            </div>
                            <div class="mt-4 flex flex-wrap gap-4">
                                @if (isset($this->rentalTerms['pets_allowed']))
                                    <div class="flex items-center gap-2 text-sm">
                                        @if ($this->rentalTerms['pets_allowed'])
                                            <flux:icon name="check-circle" class="size-5 text-green-500" />
                                            <span class="text-zinc-700 dark:text-zinc-300">Mascotas permitidas</span>
                                        @else
                                            <flux:icon name="x-circle" class="size-5 text-red-500" />
                                            <span class="text-zinc-700 dark:text-zinc-300">No se permiten mascotas</span>
                                        @endif
                                    </div>
                                @endif
                                @if (isset($this->rentalTerms['guarantor_required']))
                                    <div class="flex items-center gap-2 text-sm">
                                        @if ($this->rentalTerms['guarantor_required'])
                                            <flux:icon name="user-circle" class="size-5 text-amber-500" />
                                            <span class="text-zinc-700 dark:text-zinc-300">Requiere aval</span>
                                        @else
                                            <flux:icon name="check-circle" class="size-5 text-green-500" />
                                            <span class="text-zinc-700 dark:text-zinc-300">Sin aval requerido</span>
                                        @endif
                                    </div>
                                @endif
                                @if (!empty($this->rentalTerms['max_occupants']))
                                    <div class="flex items-center gap-2 text-sm">
                                        <flux:icon name="users" class="size-5 text-zinc-400" />
                                        <span class="text-zinc-700 dark:text-zinc-300">Max {{ $this->rentalTerms['max_occupants'] }} personas</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- About This Building (Mobile only - desktop shows in sidebar) --}}
                    @if ($this->buildingInfo && (!empty($this->buildingInfo['building_name']) || !empty($this->buildingInfo['nearby'])))
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6 lg:hidden">
                            <flux:heading size="lg" class="mb-4">Sobre este edificio</flux:heading>

                            @if (!empty($this->buildingInfo['building_name']))
                                <div class="mb-4 flex items-center gap-3">
                                    <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon name="building-office-2" class="size-5 text-zinc-600 dark:text-zinc-400" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->buildingInfo['building_name'] }}</p>
                                        @if (!empty($this->buildingInfo['building_type']))
                                            <p class="text-sm text-zinc-500">{{ $this->buildingInfo['building_type'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if (!empty($this->buildingInfo['nearby']))
                                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Cerca de aqui</h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->buildingInfo['nearby'] as $landmark)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-3 py-1.5 text-sm dark:bg-zinc-800">
                                            <span>{{ $this->getLandmarkIcon($landmark['type'] ?? 'default') }}</span>
                                            <span class="text-zinc-700 dark:text-zinc-300">{{ $landmark['name'] }}</span>
                                            @if (!empty($landmark['distance']))
                                                <span class="text-zinc-400">{{ $landmark['distance'] }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Good to Know (Mobile only - desktop shows in sidebar) --}}
                    @if ($this->propertyInsights && (!empty($this->propertyInsights['target_audience']) || !empty($this->propertyInsights['occupancy_type']) || !empty($this->propertyInsights['property_condition'])))
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6 lg:hidden">
                            <flux:heading size="lg" class="mb-4">Bueno saber</flux:heading>
                            <div class="grid grid-cols-3 gap-3">
                                @if (!empty($this->propertyInsights['target_audience']))
                                    <div class="rounded-xl bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                                        <div class="mb-1 text-2xl">👥</div>
                                        <div class="text-xs text-zinc-500">Ideal para</div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatTargetAudience($this->propertyInsights['target_audience']) }}</div>
                                    </div>
                                @endif
                                @if (!empty($this->propertyInsights['occupancy_type']))
                                    <div class="rounded-xl bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                                        <div class="mb-1 text-2xl">🏠</div>
                                        <div class="text-xs text-zinc-500">Mejor para</div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatOccupancyType($this->propertyInsights['occupancy_type']) }}</div>
                                    </div>
                                @endif
                                @if (!empty($this->propertyInsights['property_condition']))
                                    <div class="rounded-xl bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                                        <div class="mb-1 text-2xl">✨</div>
                                        <div class="text-xs text-zinc-500">Condicion</div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ ucfirst($this->propertyInsights['property_condition']) }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Location / Map (Mobile only - desktop shows in sidebar) --}}
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6 lg:hidden">
                        <flux:heading size="lg" class="mb-4">Ubicacion</flux:heading>

                        @if ($property->latitude && $property->longitude)
                            <div class="mb-4 aspect-video overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
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

                        <div class="space-y-2 text-sm">
                            @if ($property->address)
                                <div class="flex items-start gap-2">
                                    <flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                                    <span class="text-zinc-600 dark:text-zinc-400">{{ $property->address }}</span>
                                </div>
                            @endif
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-zinc-500">
                                @if ($property->colonia)
                                    <span>{{ $property->colonia }}</span>
                                @endif
                                @if ($property->city)
                                    <span>{{ $property->city }}</span>
                                @endif
                                @if ($property->state)
                                    <span>{{ $property->state }}</span>
                                @endif
                                @if ($property->postal_code)
                                    <span>CP {{ $property->postal_code }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar (Desktop) --}}
            <div class="hidden space-y-5 lg:block">
                {{-- About This Building (Desktop sidebar) --}}
                @if ($this->buildingInfo && (!empty($this->buildingInfo['building_name']) || !empty($this->buildingInfo['nearby'])))
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <flux:heading size="lg" class="mb-4">Sobre este edificio</flux:heading>

                        @if (!empty($this->buildingInfo['building_name']))
                            <div class="mb-4 flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon name="building-office-2" class="size-5 text-zinc-600 dark:text-zinc-400" />
                                </div>
                                <div>
                                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->buildingInfo['building_name'] }}</p>
                                    @if (!empty($this->buildingInfo['building_type']))
                                        <p class="text-sm text-zinc-500">{{ $this->buildingInfo['building_type'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if (!empty($this->buildingInfo['nearby']))
                            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Cerca de aqui</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->buildingInfo['nearby'] as $landmark)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-3 py-1.5 text-sm dark:bg-zinc-800">
                                        <span>{{ $this->getLandmarkIcon($landmark['type'] ?? 'default') }}</span>
                                        <span class="text-zinc-700 dark:text-zinc-300">{{ $landmark['name'] }}</span>
                                        @if (!empty($landmark['distance']))
                                            <span class="text-zinc-400">{{ $landmark['distance'] }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Rental Terms (Desktop sidebar) --}}
                @php
                    $hasRentalTermsDataSidebar = $this->rentalTerms && (
                        !empty($this->rentalTerms['deposit_months']) ||
                        !empty($this->rentalTerms['advance_months']) ||
                        !empty($this->rentalTerms['income_proof_months']) ||
                        isset($this->rentalTerms['pets_allowed']) ||
                        isset($this->rentalTerms['guarantor_required']) ||
                        !empty($this->rentalTerms['max_occupants'])
                    );
                @endphp
                @if ($this->primaryPrice && $this->primaryPrice['type'] === 'rent' && $hasRentalTermsDataSidebar)
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <flux:heading size="lg" class="mb-4">Condiciones de renta</flux:heading>
                        <div class="grid grid-cols-3 gap-3">
                            @if (!empty($this->rentalTerms['deposit_months']))
                                <div class="rounded-lg bg-zinc-50 p-2.5 text-center dark:bg-zinc-800">
                                    <p class="text-xs text-zinc-500">Deposito</p>
                                    <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->rentalTerms['deposit_months'] }}</p>
                                    <p class="text-xs text-zinc-500">{{ $this->rentalTerms['deposit_months'] === 1 ? 'mes' : 'meses' }}</p>
                                </div>
                            @endif
                            @if (!empty($this->rentalTerms['advance_months']))
                                <div class="rounded-lg bg-zinc-50 p-2.5 text-center dark:bg-zinc-800">
                                    <p class="text-xs text-zinc-500">Adelanto</p>
                                    <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->rentalTerms['advance_months'] }}</p>
                                    <p class="text-xs text-zinc-500">{{ $this->rentalTerms['advance_months'] === 1 ? 'mes' : 'meses' }}</p>
                                </div>
                            @endif
                            @if (!empty($this->rentalTerms['income_proof_months']))
                                <div class="rounded-lg bg-zinc-50 p-2.5 text-center dark:bg-zinc-800">
                                    <p class="text-xs text-zinc-500">Comprobante</p>
                                    <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->rentalTerms['income_proof_months'] }}x</p>
                                    <p class="text-xs text-zinc-500">ingresos</p>
                                </div>
                            @endif
                        </div>
                        <div class="mt-3 flex flex-wrap gap-3">
                            @if (isset($this->rentalTerms['pets_allowed']))
                                <div class="flex items-center gap-1.5 text-sm">
                                    @if ($this->rentalTerms['pets_allowed'])
                                        <flux:icon name="check-circle" class="size-4 text-green-500" />
                                        <span class="text-zinc-700 dark:text-zinc-300">Mascotas permitidas</span>
                                    @else
                                        <flux:icon name="x-circle" class="size-4 text-red-500" />
                                        <span class="text-zinc-700 dark:text-zinc-300">No se permiten mascotas</span>
                                    @endif
                                </div>
                            @endif
                            @if (isset($this->rentalTerms['guarantor_required']))
                                <div class="flex items-center gap-1.5 text-sm">
                                    @if ($this->rentalTerms['guarantor_required'])
                                        <flux:icon name="user-circle" class="size-4 text-amber-500" />
                                        <span class="text-zinc-700 dark:text-zinc-300">Requiere aval</span>
                                    @else
                                        <flux:icon name="check-circle" class="size-4 text-green-500" />
                                        <span class="text-zinc-700 dark:text-zinc-300">Sin aval requerido</span>
                                    @endif
                                </div>
                            @endif
                            @if (!empty($this->rentalTerms['max_occupants']))
                                <div class="flex items-center gap-1.5 text-sm">
                                    <flux:icon name="users" class="size-4 text-zinc-400" />
                                    <span class="text-zinc-700 dark:text-zinc-300">Max {{ $this->rentalTerms['max_occupants'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Good to Know (Desktop sidebar) --}}
                @if ($this->propertyInsights && (!empty($this->propertyInsights['target_audience']) || !empty($this->propertyInsights['occupancy_type']) || !empty($this->propertyInsights['property_condition'])))
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <flux:heading size="lg" class="mb-4">Bueno saber</flux:heading>
                        <div class="grid grid-cols-3 gap-2">
                            @if (!empty($this->propertyInsights['target_audience']))
                                <div class="rounded-xl bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                                    <div class="mb-1 text-xl">👥</div>
                                    <div class="text-xs text-zinc-500">Ideal para</div>
                                    <div class="text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatTargetAudience($this->propertyInsights['target_audience']) }}</div>
                                </div>
                            @endif
                            @if (!empty($this->propertyInsights['occupancy_type']))
                                <div class="rounded-xl bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                                    <div class="mb-1 text-xl">🏠</div>
                                    <div class="text-xs text-zinc-500">Mejor para</div>
                                    <div class="text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatOccupancyType($this->propertyInsights['occupancy_type']) }}</div>
                                </div>
                            @endif
                            @if (!empty($this->propertyInsights['property_condition']))
                                <div class="rounded-xl bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                                    <div class="mb-1 text-xl">✨</div>
                                    <div class="text-xs text-zinc-500">Condicion</div>
                                    <div class="text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ ucfirst($this->propertyInsights['property_condition']) }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Location / Map (Desktop sidebar) --}}
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg" class="mb-4">Ubicacion</flux:heading>

                    @if ($property->latitude && $property->longitude)
                        <div class="mb-4 aspect-[4/3] overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
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

                    <div class="space-y-2 text-sm">
                        @if ($property->address)
                            <div class="flex items-start gap-2">
                                <flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $property->address }}</span>
                            </div>
                        @endif
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                            @if ($property->colonia)
                                <span>{{ $property->colonia }}</span>
                            @endif
                            @if ($property->city)
                                <span>{{ $property->city }}</span>
                            @endif
                            @if ($property->state)
                                <span>{{ $property->state }}</span>
                            @endif
                            @if ($property->postal_code)
                                <span>CP {{ $property->postal_code }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Contact Card --}}
                @if ($this->publishers->isNotEmpty())
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <flux:heading size="lg" class="mb-4">Contacto</flux:heading>
                        <div class="space-y-4">
                            @foreach ($this->publishers as $publisher)
                                @php
                                    $contactNumber = $publisher->whatsapp ?: $publisher->phone;
                                    $cleanNumber = $contactNumber ? preg_replace('/[^0-9]/', '', $contactNumber) : null;
                                @endphp
                                <div class="{{ !$loop->last ? 'border-b border-zinc-100 pb-4 dark:border-zinc-800' : '' }}">
                                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $publisher->name }}</p>
                                    <p class="text-xs text-zinc-400">{{ $publisher->type->label() }}</p>
                                    @if ($cleanNumber)
                                        <a href="https://wa.me/{{ $cleanNumber }}"
                                           target="_blank"
                                           class="mt-2 inline-flex items-center gap-1.5 text-sm text-green-600 hover:text-green-700 dark:text-green-500">
                                            <svg class="size-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                            </svg>
                                            {{ $contactNumber }}
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Mobile: Contact Only (shown below main content) --}}
        <div class="mt-6 lg:hidden">
            @if ($this->publishers->isNotEmpty())
                <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="lg" class="mb-4">Contacto</flux:heading>
                    <div class="space-y-4">
                        @foreach ($this->publishers as $publisher)
                            @php
                                $contactNumber = $publisher->whatsapp ?: $publisher->phone;
                                $cleanNumber = $contactNumber ? preg_replace('/[^0-9]/', '', $contactNumber) : null;
                            @endphp
                            <div class="{{ !$loop->last ? 'border-b border-zinc-100 pb-4 dark:border-zinc-800' : '' }}">
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $publisher->name }}</p>
                                <p class="text-xs text-zinc-400">{{ $publisher->type->label() }}</p>
                                @if ($cleanNumber)
                                    <a href="https://wa.me/{{ $cleanNumber }}"
                                       target="_blank"
                                       class="mt-2 inline-flex items-center gap-1.5 text-sm text-green-600 hover:text-green-700 dark:text-green-500">
                                        <svg class="size-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                        {{ $contactNumber }}
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
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
