<div>
    {{-- Header Actions Slot (Collection Button) --}}
    <x-slot name="headerActions">
        <button
            wire:click="$toggle('showCollectionPanel')"
            class="relative flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors {{ count($collection) > 0 ? 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-900/50' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
        >
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
            </svg>
            <span class="hidden sm:inline">Coleccion</span>
            @if(count($collection) > 0)
                <span class="flex size-5 items-center justify-center rounded-full bg-amber-500 text-xs font-semibold text-white">
                    {{ count($collection) }}
                </span>
            @endif
        </button>
    </x-slot>

    {{-- Compact Sticky Filter Bar --}}
    <div class="sticky top-14 z-40 border-b border-zinc-200/80 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto max-w-screen-2xl px-3 sm:px-6 lg:px-8">
            {{-- Single Row Filters (Mobile Optimized) --}}
            <div class="flex items-center gap-2 py-2 sm:gap-4 sm:py-3">
                {{-- Operation Type Pills (Compact on mobile) --}}
                <div class="flex items-center gap-0.5 rounded-lg bg-zinc-100 p-0.5 dark:bg-zinc-800 sm:gap-1 sm:p-1">
                    <button
                        wire:click="$set('operationType', '')"
                        class="rounded-md px-2 py-1 text-xs font-medium transition-all sm:px-3 sm:py-1.5 sm:text-sm {{ $operationType === '' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-zinc-100' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
                    >
                        Todas
                    </button>
                    <button
                        wire:click="$set('operationType', 'sale')"
                        class="rounded-md px-2 py-1 text-xs font-medium transition-all sm:px-3 sm:py-1.5 sm:text-sm {{ $operationType === 'sale' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-zinc-100' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
                    >
                        Venta
                    </button>
                    <button
                        wire:click="$set('operationType', 'rent')"
                        class="rounded-md px-2 py-1 text-xs font-medium transition-all sm:px-3 sm:py-1.5 sm:text-sm {{ $operationType === 'rent' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-zinc-100' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
                    >
                        Renta
                    </button>
                </div>

                {{-- Zone Picker (Searchable - replaces search bar) --}}
                <div class="min-w-[120px] max-w-[200px] flex-1 sm:min-w-[160px] sm:max-w-[240px] sm:flex-none">
                    <flux:select
                        variant="listbox"
                        multiple
                        searchable
                        wire:model.live="zones"
                        placeholder="Buscar zona..."
                        size="sm"
                    >
                        @foreach($this->zonesGroupedByCity as $city => $colonias)
                            @foreach($colonias as $colonia)
                                <flux:select.option :value="$colonia">{{ $colonia }}, {{ $city }}</flux:select.option>
                            @endforeach
                        @endforeach
                    </flux:select>
                </div>

                {{-- Property Type (Hidden on mobile, show in More Filters) --}}
                <div class="hidden items-center gap-1 lg:flex">
                    @php
                        $propertyTypes = [
                            '' => 'Tipo',
                            'house' => 'Casa',
                            'apartment' => 'Depto',
                            'land' => 'Terreno',
                            'commercial' => 'Local',
                        ];
                    @endphp
                    @foreach($propertyTypes as $value => $label)
                        <button
                            wire:click="$set('propertyType', '{{ $value }}')"
                            class="rounded-md px-2.5 py-1 text-xs font-medium transition-all {{ $propertyType === $value ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Spacer --}}
                <div class="hidden flex-1 sm:block"></div>

                {{-- More Filters Button --}}
                <button
                    wire:click="$set('showFiltersModal', true)"
                    class="flex items-center gap-1 rounded-md bg-zinc-100 px-2 py-1.5 text-xs font-medium text-zinc-600 transition-colors hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700 sm:gap-1.5 sm:px-3"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                    <span class="hidden xs:inline">Filtros</span>
                    @if($this->activeFilterCount > 0 || $propertyType !== '' || $pricePreset !== '' || $bedrooms !== '')
                        @php
                            $totalFilters = $this->activeFilterCount + ($propertyType !== '' ? 1 : 0) + ($pricePreset !== '' ? 1 : 0) + ($bedrooms !== '' ? 1 : 0);
                        @endphp
                        <span class="flex size-4 items-center justify-center rounded-full bg-zinc-900 text-[10px] font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">
                            {{ $totalFilters }}
                        </span>
                    @endif
                </button>

                {{-- Sort (Icon only on mobile) --}}
                <flux:dropdown>
                    <flux:button variant="ghost" size="sm" class="!px-2 sm:gap-2 sm:!px-3">
                        <svg class="size-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                        </svg>
                        <span class="hidden text-zinc-700 dark:text-zinc-300 sm:inline">Ordenar</span>
                    </flux:button>

                    <flux:menu>
                        <flux:menu.item wire:click="$set('sortBy', 'newest')" :active="$sortBy === 'newest'">Mas recientes</flux:menu.item>
                        <flux:menu.item wire:click="$set('sortBy', 'oldest')" :active="$sortBy === 'oldest'">Mas antiguos</flux:menu.item>
                        <flux:menu.item wire:click="$set('sortBy', 'price_low')" :active="$sortBy === 'price_low'">Precio menor</flux:menu.item>
                        <flux:menu.item wire:click="$set('sortBy', 'price_high')" :active="$sortBy === 'price_high'">Precio mayor</flux:menu.item>
                        <flux:menu.item wire:click="$set('sortBy', 'size')" :active="$sortBy === 'size'">Mayor tamano</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>

            {{-- Quick Filters Row (Hidden on mobile - moved to modal) --}}
            <div class="hidden flex-wrap items-center gap-2 border-t border-zinc-100 py-2 dark:border-zinc-800 sm:flex sm:gap-3 sm:py-2.5">
                {{-- Price Preset Pills --}}
                <div class="flex flex-wrap items-center gap-1">
                    <span class="mr-1 text-[10px] font-medium uppercase tracking-wider text-zinc-400 sm:text-xs">Precio</span>
                    @foreach($this->pricePresets as $key => $preset)
                        <button
                            wire:click="$set('pricePreset', '{{ $pricePreset === $key ? '' : $key }}')"
                            class="rounded-md px-2 py-0.5 text-[10px] font-medium transition-all sm:px-2.5 sm:py-1 sm:text-xs {{ $pricePreset === $key ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                        >
                            {{ $preset['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- Bedrooms Pills --}}
                <div class="flex flex-wrap items-center gap-1">
                    <span class="mr-1 text-[10px] font-medium uppercase tracking-wider text-zinc-400 sm:text-xs">Rec</span>
                    @foreach(['1', '2', '3', '4'] as $bed)
                        <button
                            wire:click="$set('bedrooms', '{{ $bedrooms === $bed ? '' : $bed }}')"
                            class="flex size-6 items-center justify-center rounded-md text-[10px] font-medium transition-all sm:size-7 sm:text-xs {{ $bedrooms === $bed ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                        >
                            {{ $bed }}{{ $bed === '4' ? '+' : '' }}
                        </button>
                    @endforeach
                </div>

                {{-- Clear Filters --}}
                @if($operationType !== '' || $propertyType !== '' || !empty($zones) || $pricePreset !== '' || $bedrooms !== '' || $this->activeFilterCount > 0)
                    <button
                        wire:click="clearFilters"
                        class="ml-auto text-[10px] font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100 sm:text-xs"
                    >
                        Limpiar
                    </button>
                @endif
            </div>

            {{-- Active Filter Pills (Collapsed on mobile) --}}
            @if(!empty($zones) || ($minPrice !== '' && $pricePreset === '') || ($maxPrice !== '' && $pricePreset === '') || $bathrooms !== '' || $minSize !== '' || $maxSize !== '' || $parking !== '' || !empty($amenities))
                <div class="flex flex-wrap items-center gap-1.5 border-t border-zinc-100 py-2 dark:border-zinc-800 sm:gap-2">
                    @foreach($zones as $zone)
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 sm:text-xs">
                            {{ $zone }}
                            <button wire:click="$set('zones', {{ json_encode(array_values(array_filter($zones, fn($z) => $z !== $zone))) }})" class="hover:text-blue-900 dark:hover:text-blue-200">
                                <svg class="size-2.5 sm:size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    @endforeach

                    @if(($minPrice !== '' || $maxPrice !== '') && $pricePreset === '')
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 sm:text-xs">
                            @if($minPrice !== '' && $maxPrice !== '')
                                ${{ number_format((int)$minPrice) }} - ${{ number_format((int)$maxPrice) }}
                            @elseif($minPrice !== '')
                                Desde ${{ number_format((int)$minPrice) }}
                            @else
                                Hasta ${{ number_format((int)$maxPrice) }}
                            @endif
                            <button wire:click="$set('minPrice', ''); $set('maxPrice', '')" class="hover:text-emerald-900 dark:hover:text-emerald-200">
                                <svg class="size-2.5 sm:size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    @endif

                    @if($bathrooms !== '')
                        <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-400 sm:text-xs">
                            {{ $bathrooms }}+ banos
                            <button wire:click="$set('bathrooms', '')" class="hover:text-violet-900 dark:hover:text-violet-200">
                                <svg class="size-2.5 sm:size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    @endif

                    @if($minSize !== '' || $maxSize !== '')
                        <span class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-medium text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 sm:text-xs">
                            @if($minSize !== '' && $maxSize !== '')
                                {{ $minSize }} - {{ $maxSize }} m²
                            @elseif($minSize !== '')
                                Min {{ $minSize }} m²
                            @else
                                Max {{ $maxSize }} m²
                            @endif
                            <button wire:click="$set('minSize', ''); $set('maxSize', '')" class="hover:text-orange-900 dark:hover:text-orange-200">
                                <svg class="size-2.5 sm:size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    @endif

                    @if($parking !== '')
                        <span class="inline-flex items-center gap-1 rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-medium text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400 sm:text-xs">
                            {{ $parking }}+ estac.
                            <button wire:click="$set('parking', '')" class="hover:text-cyan-900 dark:hover:text-cyan-200">
                                <svg class="size-2.5 sm:size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    @endif

                    @foreach($amenities as $amenity)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 sm:text-xs">
                            {{ $availableAmenities[$amenity] ?? $amenity }}
                            <button wire:click="$set('amenities', {{ json_encode(array_values(array_filter($amenities, fn($a) => $a !== $amenity))) }})" class="hover:text-amber-900 dark:hover:text-amber-200">
                                <svg class="size-2.5 sm:size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Results Section --}}
    <div class="mx-auto max-w-screen-2xl px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
        {{-- Results Header --}}
        <div class="mb-4 flex items-baseline justify-between sm:mb-6">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 sm:text-lg">
                <span wire:loading.remove wire:target="operationType,propertyType,zones,pricePreset,bedrooms,bathrooms,sortBy,search">
                    {{ number_format($properties->total()) }} propiedades
                </span>
                <span wire:loading wire:target="operationType,propertyType,zones,pricePreset,bedrooms,bathrooms,sortBy,search" class="inline-flex items-center gap-2">
                    <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Buscando...
                </span>
            </h2>
        </div>

        {{-- Property Grid --}}
        <div class="grid gap-3 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4" wire:loading.class="opacity-60" wire:target="operationType,propertyType,zones,pricePreset,bedrooms,bathrooms,sortBy,search">
            @forelse($properties as $property)
                @php
                    $listing = $property->listings->first();
                    $images = $listing?->raw_data['images'] ?? [];
                    $heroImage = $images[0] ?? null;
                    $operations = $listing?->operations ?? [];
                    $price = $operations[0]['price'] ?? null;
                    $opType = $operations[0]['type'] ?? null;
                @endphp
                <article wire:key="property-{{ $property->id }}" class="group relative">
                    {{-- Add to Collection Button (outside link, positioned over image) --}}
                    <button
                        wire:click="toggleCollection({{ $property->id }})"
                        class="absolute right-2 top-[calc(75%-theme(spacing.2)-theme(spacing.8))] z-10 flex size-8 items-center justify-center rounded-full transition-all duration-150 sm:right-3 sm:top-[calc(75%-theme(spacing.3)-theme(spacing.9))] sm:size-9 {{ $this->isInCollection($property->id) ? 'bg-amber-500 text-white shadow-lg' : 'bg-white/90 text-zinc-600 shadow-md hover:bg-white hover:text-zinc-900' }}"
                        title="{{ $this->isInCollection($property->id) ? 'Quitar de coleccion' : 'Agregar a coleccion' }}"
                    >
                        @if($this->isInCollection($property->id))
                            <svg class="size-4 sm:size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        @else
                            <svg class="size-4 sm:size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        @endif
                    </button>

                    <a href="{{ route('agents.properties.show', $property) }}" wire:navigate class="block overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200/80 transition-all duration-200 hover:shadow-md hover:ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:ring-zinc-700">
                        {{-- Image Container --}}
                        <div class="relative aspect-[4/3] overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                            @if($heroImage)
                                <img
                                    src="{{ $heroImage }}"
                                    alt="{{ $property->colonia }}"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    loading="lazy"
                                />
                            @else
                                <div class="flex h-full w-full items-center justify-center">
                                    <svg class="size-10 text-zinc-300 dark:text-zinc-700" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                </div>
                            @endif

                            {{-- Operation Badge --}}
                            @if($opType)
                                <span class="absolute left-2 top-2 rounded-md px-1.5 py-0.5 text-[10px] font-semibold sm:left-3 sm:top-3 sm:px-2 sm:text-xs {{ $opType === 'rent' ? 'bg-blue-500 text-white' : 'bg-emerald-500 text-white' }}">
                                    {{ $opType === 'rent' ? 'Renta' : 'Venta' }}
                                </span>
                            @endif

                            {{-- Image Count --}}
                            @if(count($images) > 1)
                                <span class="absolute right-2 top-2 flex items-center gap-1 rounded-md bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur-sm sm:right-3 sm:top-3 sm:px-2 sm:text-xs">
                                    <svg class="size-2.5 sm:size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    {{ count($images) }}
                                </span>
                            @endif
                        </div>

                        {{-- Card Content --}}
                        <div class="p-3 sm:p-4">
                            {{-- Price --}}
                            <div class="mb-1">
                                @if($price)
                                    <p class="text-base font-bold tracking-tight text-zinc-900 dark:text-zinc-100 sm:text-lg">
                                        ${{ number_format($price) }}{{ $opType === 'rent' ? '/mes' : '' }}
                                    </p>
                                @else
                                    <p class="text-base font-bold text-zinc-400 dark:text-zinc-600 sm:text-lg">
                                        Consultar precio
                                    </p>
                                @endif
                            </div>

                            {{-- Location --}}
                            <p class="mb-2 text-xs text-zinc-600 dark:text-zinc-400 sm:mb-3 sm:text-sm">
                                {{ $property->colonia }}{{ $property->city ? ', ' . $property->city : '' }}
                            </p>

                            {{-- Stats --}}
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[10px] text-zinc-500 dark:text-zinc-500 sm:gap-x-3 sm:text-xs">
                                @if($property->bedrooms)
                                    <span class="flex items-center gap-0.5 sm:gap-1">
                                        <svg class="size-3 sm:size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                        </svg>
                                        {{ $property->bedrooms }} rec
                                    </span>
                                @endif
                                @if($property->bathrooms)
                                    <span class="flex items-center gap-0.5 sm:gap-1">
                                        <svg class="size-3 sm:size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ $property->bathrooms }} ban
                                    </span>
                                @endif
                                @if($property->parking_spots)
                                    <span class="flex items-center gap-0.5 sm:gap-1">
                                        <svg class="size-3 sm:size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                        </svg>
                                        {{ $property->parking_spots }}
                                    </span>
                                @endif
                                @if($property->built_size_m2)
                                    <span class="flex items-center gap-0.5 sm:gap-1">
                                        <svg class="size-3 sm:size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                        </svg>
                                        {{ number_format($property->built_size_m2) }} m²
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                </article>
            @empty
                {{-- Empty State --}}
                <div class="col-span-full py-12 text-center sm:py-16">
                    <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 sm:size-16">
                        <svg class="size-7 text-zinc-400 sm:size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100 sm:text-lg">No se encontraron propiedades</h3>
                    <p class="mt-1 text-xs text-zinc-500 sm:text-sm">Intenta ajustar los filtros para ver mas resultados.</p>
                    <button
                        wire:click="clearFilters"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200 sm:px-4 sm:text-sm"
                    >
                        Limpiar filtros
                    </button>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($properties->hasPages())
            <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-800 sm:mt-8 sm:pt-6">
                {{ $properties->links() }}
            </div>
        @endif
    </div>

    {{-- All Filters Modal (includes mobile-only filters) --}}
    <flux:modal wire:model="showFiltersModal" class="max-w-md">
        <div class="space-y-5">
            <div>
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Filtros</h3>
                <p class="mt-1 text-sm text-zinc-500">Ajusta los filtros para refinar tu busqueda.</p>
            </div>

            {{-- Property Type (shown in modal for mobile) --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tipo de propiedad</label>
                <div class="flex flex-wrap gap-1">
                    @foreach(['' => 'Todas', 'house' => 'Casa', 'apartment' => 'Depto', 'land' => 'Terreno', 'commercial' => 'Local'] as $value => $label)
                        <button
                            wire:click="$set('propertyType', '{{ $value }}')"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition-all {{ $propertyType === $value ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Price Presets --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Precio {{ $operationType === 'rent' ? '(mensual)' : '' }}
                </label>
                <div class="flex flex-wrap gap-1">
                    <button
                        wire:click="$set('pricePreset', '')"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-all {{ $pricePreset === '' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                    >
                        Todos
                    </button>
                    @foreach($this->pricePresets as $key => $preset)
                        <button
                            wire:click="$set('pricePreset', '{{ $key }}')"
                            class="rounded-md px-3 py-1.5 text-sm font-medium transition-all {{ $pricePreset === $key ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                        >
                            {{ $preset['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Bedrooms --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Recamaras</label>
                <div class="flex gap-1">
                    @foreach(['' => 'Todas', '1' => '1+', '2' => '2+', '3' => '3+', '4' => '4+'] as $value => $label)
                        <button
                            wire:click="$set('bedrooms', '{{ $value }}')"
                            class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all {{ $bedrooms === $value ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Bathrooms --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Banos</label>
                <div class="flex gap-1">
                    @foreach(['' => 'Todos', '1' => '1+', '2' => '2+', '3' => '3+', '4' => '4+'] as $value => $label)
                        <button
                            wire:click="$set('bathrooms', '{{ $value }}')"
                            class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all {{ $bathrooms === $value ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Size Range --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tamano (m²)</label>
                <div class="flex items-center gap-3">
                    <input
                        type="number"
                        wire:model.live.debounce.500ms="minSize"
                        placeholder="Min"
                        class="w-full rounded-lg border-zinc-300 text-sm focus:border-zinc-500 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:focus:border-zinc-500"
                    />
                    <span class="text-zinc-400">-</span>
                    <input
                        type="number"
                        wire:model.live.debounce.500ms="maxSize"
                        placeholder="Max"
                        class="w-full rounded-lg border-zinc-300 text-sm focus:border-zinc-500 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:focus:border-zinc-500"
                    />
                </div>
            </div>

            {{-- Parking --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estacionamiento</label>
                <div class="flex gap-1">
                    @foreach(['' => 'Todos', '1' => '1+', '2' => '2+', '3' => '3+'] as $value => $label)
                        <button
                            wire:click="$set('parking', '{{ $value }}')"
                            class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all {{ $parking === $value ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Amenities --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Amenidades</label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($availableAmenities as $key => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800 {{ in_array($key, $amenities) ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-400 dark:bg-zinc-800' : '' }}">
                            <input
                                type="checkbox"
                                wire:model.live="amenities"
                                value="{{ $key }}"
                                class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                            />
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <button
                    wire:click="clearFilters"
                    class="text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100"
                >
                    Limpiar todo
                </button>
                <button
                    wire:click="applyFilters"
                    class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
                >
                    Aplicar filtros
                </button>
            </div>
        </div>
    </flux:modal>

    {{-- Collection Panel --}}
    <flux:modal wire:model="showCollectionPanel" position="right" class="w-full max-w-md">
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between border-b border-zinc-200 pb-4 dark:border-zinc-700">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Mi coleccion</h3>
                    <p class="text-sm text-zinc-500">{{ count($collection) }} {{ count($collection) === 1 ? 'propiedad' : 'propiedades' }}</p>
                </div>
                @if(count($collection) > 0)
                    <button
                        wire:click="clearCollection"
                        class="text-sm font-medium text-zinc-500 hover:text-red-600 dark:hover:text-red-400"
                    >
                        Vaciar
                    </button>
                @endif
            </div>

            @if(count($collection) > 0)
                <div class="flex-1 space-y-3 overflow-y-auto py-4">
                    @foreach($this->collectionProperties as $property)
                        <div wire:key="collection-{{ $property->id }}" class="flex gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                            @php
                                $listing = $property->listings->first();
                                $images = $listing?->raw_data['images'] ?? [];
                                $heroImage = $images[0] ?? null;
                                $operations = $listing?->operations ?? [];
                                $price = $operations[0]['price'] ?? null;
                                $opType = $operations[0]['type'] ?? null;
                            @endphp

                            <div class="size-16 flex-shrink-0 overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                @if($heroImage)
                                    <img src="{{ $heroImage }}" alt="" class="h-full w-full object-cover" />
                                @else
                                    <div class="flex h-full w-full items-center justify-center">
                                        <svg class="size-6 text-zinc-300 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">
                                    @if($price)
                                        ${{ number_format($price) }}{{ $opType === 'rent' ? '/mes' : '' }}
                                    @else
                                        Sin precio
                                    @endif
                                </p>
                                <p class="truncate text-sm text-zinc-500">
                                    {{ $property->colonia }}{{ $property->city ? ', ' . $property->city : '' }}
                                </p>
                                <p class="text-xs text-zinc-400">
                                    {{ $property->bedrooms ?? '-' }} rec · {{ $property->bathrooms ?? '-' }} ban · {{ $property->built_size_m2 ? number_format($property->built_size_m2) . ' m²' : '-' }}
                                </p>
                            </div>

                            <button
                                wire:click="removeFromCollection({{ $property->id }})"
                                class="flex-shrink-0 self-start text-zinc-400 transition-colors hover:text-red-500"
                            >
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <button
                        disabled
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white opacity-50"
                    >
                        <svg class="size-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Compartir por WhatsApp
                        <span class="rounded bg-green-500/30 px-1.5 py-0.5 text-xs">Pronto</span>
                    </button>
                    <button
                        disabled
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 opacity-50 dark:border-zinc-700 dark:text-zinc-300"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Descargar PDFs
                        <span class="rounded bg-zinc-200 px-1.5 py-0.5 text-xs dark:bg-zinc-700">Pronto</span>
                    </button>
                </div>
            @else
                <div class="flex flex-1 flex-col items-center justify-center py-12 text-center">
                    <div class="mb-4 flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <svg class="size-8 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                        </svg>
                    </div>
                    <h4 class="font-semibold text-zinc-900 dark:text-zinc-100">Coleccion vacia</h4>
                    <p class="mt-1 max-w-[200px] text-sm text-zinc-500">
                        Agrega propiedades tocando el boton + en las tarjetas.
                    </p>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
