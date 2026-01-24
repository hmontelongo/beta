<div>
    {{-- Compact Sticky Filter Bar --}}
    <div class="sticky top-14 z-40 border-b border-zinc-200/80 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto max-w-screen-2xl px-3 sm:px-6 lg:px-8">
            {{-- Single Row Filters --}}
            <div class="flex items-center gap-2 overflow-x-auto py-2.5 sm:gap-3">
                {{-- Operation Type Pills --}}
                <div class="flex shrink-0 items-center rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800">
                    @foreach(['' => 'Todas', 'sale' => 'Venta', 'rent' => 'Renta'] as $value => $label)
                        <button
                            wire:click="$set('operationType', '{{ $value }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-xs font-semibold transition-all',
                                'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-zinc-100' => $operationType === $value && $value === '',
                                'bg-blue-500 text-white shadow-sm' => $operationType === $value && $value !== '',
                                'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' => $operationType !== $value,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Zone Picker --}}
                <div class="w-32 shrink-0 sm:w-52">
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

                {{-- Property Type (Hidden on mobile) --}}
                <div class="hidden items-center rounded-lg bg-zinc-100 p-1 dark:bg-zinc-800 lg:flex">
                    @foreach(['' => 'Todas', 'house' => 'Casa', 'apartment' => 'Depto', 'land' => 'Terreno', 'commercial' => 'Local'] as $value => $label)
                        <button
                            wire:click="$set('propertyType', '{{ $value }}')"
                            @class([
                                'rounded-md px-3 py-1.5 text-xs font-semibold transition-all',
                                'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-zinc-100' => $propertyType === $value,
                                'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' => $propertyType !== $value,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Spacer --}}
                <div class="flex-1"></div>

                {{-- More Filters Button --}}
                @php
                    $hasActiveFilters = $this->activeFilterCount > 0 || $propertyType !== '' || $pricePreset !== '' || $bedrooms !== '';
                    $totalFilters = $this->activeFilterCount + ($propertyType !== '' ? 1 : 0) + ($pricePreset !== '' ? 1 : 0) + ($bedrooms !== '' ? 1 : 0);
                @endphp
                <button
                    wire:click="$set('showFiltersModal', true)"
                    @class([
                        'flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all',
                        'bg-blue-50 text-blue-600 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400' => $hasActiveFilters,
                        'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400' => !$hasActiveFilters,
                    ])
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                    @if($hasActiveFilters)
                        <span class="flex size-5 items-center justify-center rounded-full bg-blue-500 text-[10px] font-bold text-white">{{ $totalFilters }}</span>
                    @endif
                </button>

                {{-- Sort --}}
                <flux:dropdown class="shrink-0">
                    <flux:button variant="ghost" size="sm" class="!px-2.5">
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

                {{-- Collection Buttons --}}
                <div class="flex shrink-0 items-center">
                    <button
                        wire:click="$toggle('showCollectionPanel')"
                        @class([
                            'flex items-center gap-1.5 rounded-l-lg border-r border-zinc-200 px-3 py-1.5 text-xs font-semibold transition-all dark:border-zinc-700',
                            'bg-blue-50 text-blue-600 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400' => count($this->collectionPropertyIds) > 0,
                            'bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' => count($this->collectionPropertyIds) === 0,
                        ])
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                        </svg>
                        <span class="hidden sm:inline">Coleccion</span>
                        @if(count($this->collectionPropertyIds) > 0)
                            <span class="flex size-5 items-center justify-center rounded-full bg-blue-500 text-[10px] font-bold text-white">{{ count($this->collectionPropertyIds) }}</span>
                        @endif
                    </button>
                    <a
                        href="{{ route('agents.collections.index') }}"
                        wire:navigate
                        @class([
                            'flex items-center rounded-r-lg px-2 py-1.5 transition-all',
                            'bg-blue-50 text-blue-600 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400' => count($this->collectionPropertyIds) > 0,
                            'bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' => count($this->collectionPropertyIds) === 0,
                        ])
                        title="Ver todas mis colecciones"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Quick Filters Row (Desktop only) --}}
            <div class="hidden items-center gap-4 border-t border-zinc-100 py-2 dark:border-zinc-800 sm:flex">
                {{-- Price Preset Pills --}}
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Precio</span>
                    @foreach($this->pricePresets as $key => $preset)
                        <button
                            wire:click="$set('pricePreset', '{{ $pricePreset === $key ? '' : $key }}')"
                            @class([
                                'rounded-md px-2.5 py-1 text-xs font-semibold transition-all',
                                'bg-blue-500 text-white' => $pricePreset === $key,
                                'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400' => $pricePreset !== $key,
                            ])
                        >
                            {{ $preset['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- Bedrooms Pills (click to toggle, like price) --}}
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Rec</span>
                    @foreach(['1', '2', '3', '4'] as $bed)
                        <button
                            wire:click="$set('bedrooms', '{{ $bedrooms === $bed ? '' : $bed }}')"
                            @class([
                                'flex size-7 items-center justify-center rounded-md text-xs font-semibold transition-all',
                                'bg-blue-500 text-white' => $bedrooms === $bed,
                                'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400' => $bedrooms !== $bed,
                            ])
                        >
                            {{ $bed }}{{ $bed === '4' ? '+' : '' }}
                        </button>
                    @endforeach
                </div>

                {{-- Clear Filters --}}
                @if($operationType !== '' || $propertyType !== '' || !empty($zones) || $pricePreset !== '' || $bedrooms !== '' || $this->activeFilterCount > 0)
                    <button wire:click="clearFilters" class="ml-auto text-xs font-semibold text-red-500 hover:text-red-600">
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
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 sm:text-xs">
                            {{ $availableAmenities[$amenity] ?? $amenity }}
                            <button wire:click="$set('amenities', {{ json_encode(array_values(array_filter($amenities, fn($a) => $a !== $amenity))) }})" class="hover:text-blue-900 dark:hover:text-blue-200">
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
        <div class="mb-4 flex items-center justify-between gap-3 sm:mb-6">
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
        <div wire:key="grid-{{ $this->gridKey }}" class="animate-stagger grid gap-3 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4" wire:loading.class="opacity-60" wire:target="operationType,propertyType,zones,pricePreset,bedrooms,bathrooms,sortBy,search">
            @forelse($properties as $property)
                @php
                    $listing = $property->listings->first();
                    $images = $listing?->raw_data['images'] ?? [];
                    $heroImage = $images[0] ?? null;
                    $operations = $listing?->operations ?? [];
                    $price = $operations[0]['price'] ?? null;
                    $opType = $operations[0]['type'] ?? null;
                @endphp
                <article wire:key="property-{{ $property->id }}" class="animate-card-enter group relative">
                    {{-- Add to Collection Button (outside link, positioned over image) --}}
                    <button
                        wire:click="toggleCollection({{ $property->id }})"
                        class="absolute right-2 top-[calc(75%-theme(spacing.2)-theme(spacing.8))] z-10 flex size-8 items-center justify-center rounded-full transition-all duration-150 sm:right-3 sm:top-[calc(75%-theme(spacing.3)-theme(spacing.9))] sm:size-9 {{ $this->isInCollection($property->id) ? 'bg-blue-500 text-white shadow-lg shadow-blue-500/30' : 'bg-white/90 text-zinc-600 shadow-md hover:bg-white hover:text-zinc-900' }}"
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

                    <a href="{{ route('agents.properties.show', $property) }}" wire:navigate class="block overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-zinc-200/80 transition-all duration-200 hover:shadow-lg hover:ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:ring-zinc-700">
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
                                <span class="absolute left-2 top-2 rounded-md px-1.5 py-0.5 text-[10px] font-semibold sm:left-3 sm:top-3 sm:px-2 sm:text-xs {{ $opType === 'rent' ? 'bg-blue-500 text-white' : 'bg-zinc-800 text-white' }}">
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

    {{-- All Filters Modal --}}
    <flux:modal wire:model="showFiltersModal" class="max-w-lg">
        <div class="space-y-5">
            {{-- Header --}}
            <div>
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Filtros</h3>
                <p class="mt-1 text-sm text-zinc-500">Ajusta los filtros para refinar tu busqueda.</p>
            </div>

            {{-- Property Type - equal width buttons --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tipo de propiedad</label>
                <div class="grid grid-cols-5 gap-1.5">
                    @foreach(['' => 'Todas', 'house' => 'Casa', 'apartment' => 'Depto', 'land' => 'Terreno', 'commercial' => 'Local'] as $value => $label)
                        <button
                            wire:key="proptype-{{ $value }}-{{ $propertyType }}"
                            wire:click="$set('propertyType', '{{ $value }}')"
                            @class([
                                'rounded-lg py-2 text-center text-sm font-medium transition-all',
                                'bg-blue-500 text-white' => $propertyType === $value,
                                'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' => $propertyType !== $value,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Price - grid of equal buttons --}}
            <div>
                <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Precio {{ $operationType === 'rent' ? '(mensual)' : '' }}
                </label>
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        wire:key="price-all-{{ $pricePreset }}"
                        wire:click="$set('pricePreset', '')"
                        @class([
                            'rounded-lg py-2 text-center text-sm font-medium transition-all',
                            'bg-blue-500 text-white' => $pricePreset === '',
                            'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' => $pricePreset !== '',
                        ])
                    >
                        Todos
                    </button>
                    @foreach($this->pricePresets as $key => $preset)
                        <button
                            wire:key="price-{{ $key }}-{{ $pricePreset }}"
                            wire:click="$set('pricePreset', '{{ $key }}')"
                            @class([
                                'rounded-lg py-2 text-center text-sm font-medium transition-all',
                                'bg-blue-500 text-white' => $pricePreset === $key,
                                'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' => $pricePreset !== $key,
                            ])
                        >
                            {{ $preset['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Bedrooms & Bathrooms - matching grids --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Recamaras</label>
                    <div class="grid grid-cols-4 gap-1" wire:key="bedrooms-group-{{ $bedrooms }}">
                        @foreach(['1' => '1+', '2' => '2+', '3' => '3+', '4' => '4+'] as $value => $label)
                            <button
                                wire:click="$set('bedrooms', '{{ (string) $bedrooms === (string) $value ? '' : $value }}')"
                                @class([
                                    'rounded-md py-1.5 text-center text-sm font-medium transition-all',
                                    'bg-blue-500 text-white' => (string) $bedrooms === (string) $value,
                                    'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' => (string) $bedrooms !== (string) $value,
                                ])
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Banos</label>
                    <div class="grid grid-cols-4 gap-1" wire:key="bathrooms-group-{{ $bathrooms }}">
                        @foreach(['1' => '1+', '2' => '2+', '3' => '3+', '4' => '4+'] as $value => $label)
                            <button
                                wire:click="$set('bathrooms', '{{ (string) $bathrooms === (string) $value ? '' : $value }}')"
                                @class([
                                    'rounded-md py-1.5 text-center text-sm font-medium transition-all',
                                    'bg-blue-500 text-white' => (string) $bathrooms === (string) $value,
                                    'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' => (string) $bathrooms !== (string) $value,
                                ])
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Size & Parking - matching layout --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Tamano (m²)</label>
                    <div class="flex items-center gap-2">
                        <flux:input type="number" wire:model.live.debounce.500ms="minSize" placeholder="Min" size="sm" />
                        <span class="text-zinc-400">—</span>
                        <flux:input type="number" wire:model.live.debounce.500ms="maxSize" placeholder="Max" size="sm" />
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estacionamiento</label>
                    <div class="grid grid-cols-4 gap-1" wire:key="parking-group-{{ $parking }}">
                        @foreach(['1' => '1+', '2' => '2+', '3' => '3+', '4' => '4+'] as $value => $label)
                            <button
                                wire:click="$set('parking', '{{ (string) $parking === (string) $value ? '' : $value }}')"
                                @class([
                                    'rounded-md py-1.5 text-center text-sm font-medium transition-all',
                                    'bg-blue-500 text-white' => (string) $parking === (string) $value,
                                    'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' => (string) $parking !== (string) $value,
                                ])
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Amenities (Airbnb-style with icons) --}}
            <div>
                <label class="mb-2.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Amenidades</label>
                @php
                    $amenityIcons = [
                        'swimming_pool' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /><circle cx="12" cy="12" r="3" />',
                        '24_hour_security' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />',
                        'gated_community' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />',
                        'covered_parking' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />',
                        'roof_garden' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />',
                        'terrace' => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />',
                        'furnished' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />',
                        'pet_friendly' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z" />',
                        'gym' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />',
                        'elevator' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />',
                    ];
                @endphp
                <div class="grid grid-cols-3 gap-2">
                    @foreach($availableAmenities as $key => $label)
                        <label
                            @class([
                                'flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 p-3 text-center transition-all',
                                'border-blue-500 bg-blue-50 dark:bg-blue-900/20' => in_array($key, $amenities),
                                'border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' => !in_array($key, $amenities),
                            ])
                        >
                            <input type="checkbox" wire:model.live="amenities" value="{{ $key }}" class="sr-only" />
                            <svg class="size-6 {{ in_array($key, $amenities) ? 'text-blue-600' : 'text-zinc-500' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                {!! $amenityIcons[$key] ?? '<path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />' !!}
                            </svg>
                            <span class="text-xs font-medium {{ in_array($key, $amenities) ? 'text-blue-700 dark:text-blue-400' : 'text-zinc-600 dark:text-zinc-400' }}">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <button wire:click="clearFilters" class="text-sm font-semibold text-red-500 hover:text-red-600">
                    Limpiar todo
                </button>
                <button
                    wire:click="applyFilters"
                    class="rounded-lg bg-blue-500 px-6 py-2.5 text-sm font-semibold text-white transition-all hover:bg-blue-600"
                >
                    Aplicar filtros
                </button>
            </div>
        </div>
    </flux:modal>

    {{-- Collection Panel --}}
    <flux:modal wire:model="showCollectionPanel" position="right" class="w-full max-w-3xl" :dismissible="true">
        <div class="flex h-full flex-col">
            {{-- Header --}}
            <div class="mb-4">
                <flux:heading size="lg">Mi seleccion</flux:heading>
                <div class="mt-3 flex items-center gap-3">
                    <flux:input
                        wire:model.blur="saveName"
                        wire:keydown.enter="saveCollectionName"
                        placeholder="Nombre de la coleccion"
                        class="max-w-xs"
                    />
                    <flux:button wire:click="saveCollectionName" variant="ghost" icon="bookmark" size="sm" />
                    <flux:text size="sm" class="text-zinc-500">
                        {{ count($this->collectionPropertyIds) }} {{ count($this->collectionPropertyIds) === 1 ? 'propiedad' : 'propiedades' }}
                    </flux:text>
                </div>
            </div>

            @if(count($this->collectionPropertyIds) > 0)
                {{-- Property Grid --}}
                <div class="flex-1 overflow-y-auto">
                    <div class="grid grid-cols-3 gap-3">
                        @foreach($this->collectionProperties as $property)
                            @php
                                $listing = $property->listings->first();
                                $images = $listing?->raw_data['images'] ?? [];
                                $heroImage = $images[0] ?? null;
                                $operations = $listing?->operations ?? [];
                                $price = $operations[0]['price'] ?? null;
                                $opType = $operations[0]['type'] ?? null;
                            @endphp
                            <div wire:key="collection-{{ $property->id }}" class="group relative overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                <a href="{{ route('agents.properties.show', $property) }}" wire:navigate class="block">
                                    <div class="aspect-[4/3] bg-zinc-200 dark:bg-zinc-700">
                                        @if($heroImage)
                                            <img src="{{ $heroImage }}" alt="" class="h-full w-full object-cover" />
                                        @else
                                            <div class="flex h-full w-full items-center justify-center">
                                                <svg class="size-6 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="p-2">
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                            @if($price)
                                                ${{ number_format($price) }}<span class="text-xs font-normal text-zinc-500">{{ $opType === 'rent' ? '/mes' : '' }}</span>
                                            @else
                                                <span class="text-zinc-400">Sin precio</span>
                                            @endif
                                        </p>
                                        <p class="truncate text-xs text-zinc-500">{{ $property->colonia }}</p>
                                        @if($property->bedrooms || $property->bathrooms)
                                            <p class="mt-0.5 text-xs text-zinc-400">
                                                @if($property->bedrooms){{ $property->bedrooms }}rec @endif
                                                @if($property->bathrooms){{ $property->bathrooms }}ban @endif
                                            </p>
                                        @endif
                                    </div>
                                </a>
                                <button
                                    wire:click="removeFromCollection({{ $property->id }})"
                                    class="absolute right-1.5 top-1.5 flex size-6 items-center justify-center rounded-full bg-black/60 text-white transition-all hover:bg-red-500"
                                    title="Quitar de seleccion"
                                >
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Action Bar --}}
                <div class="mt-4 flex items-center gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    {{-- Sharing Actions (left) --}}
                    <flux:button wire:click="copyShareLink" variant="ghost" icon="link" size="sm">
                        Copiar link
                    </flux:button>
                    <flux:button wire:click="shareViaWhatsApp" size="sm" class="!bg-green-600 !text-white hover:!bg-green-700">
                        <span class="flex items-center gap-1.5">
                            <x-icons.whatsapp class="size-4" />
                            WhatsApp
                        </span>
                    </flux:button>

                    <div class="flex-1"></div>

                    {{-- Navigation (right) --}}
                    <flux:button :href="route('agents.collections.index')" variant="ghost" icon="folder" size="sm" wire:navigate>
                        Colecciones
                    </flux:button>

                    {{-- Overflow Menu --}}
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" icon="ellipsis-vertical" size="sm" />
                        <flux:menu>
                            <flux:menu.item x-on:click="$flux.modal('confirm-clear-collection').show()" icon="trash" class="text-red-600">
                                Vaciar seleccion
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex flex-1 flex-col items-center justify-center py-12 text-center">
                    <div class="relative mb-4">
                        <div class="flex size-20 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-900/30 dark:to-indigo-900/30">
                            <svg class="size-10 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                            </svg>
                        </div>
                        <div class="absolute -right-1 -top-1 flex size-7 items-center justify-center rounded-full bg-white shadow-md dark:bg-zinc-800">
                            <svg class="size-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </div>
                    </div>
                    <h4 class="font-bold text-zinc-900 dark:text-zinc-100">Tu seleccion esta vacia</h4>
                    <p class="mt-2 max-w-[220px] text-sm text-zinc-500">
                        Toca el boton <span class="inline-flex size-5 items-center justify-center rounded-full bg-blue-100 text-blue-600">+</span> en las propiedades para agregarlas.
                    </p>
                    <a
                        href="{{ route('agents.collections.index') }}"
                        wire:navigate
                        class="mt-6 text-sm font-medium text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        Ver mis colecciones guardadas
                    </a>
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Confirm New Collection Modal --}}
    <x-confirm-modal
        name="confirm-new-collection"
        title="¿Nueva seleccion?"
        message="La coleccion actual se conservara en tus colecciones."
    >
        <flux:button variant="primary" wire:click="startNewCollection">
            Crear nueva
        </flux:button>
    </x-confirm-modal>

    {{-- Confirm Clear Collection Modal --}}
    <x-confirm-modal
        name="confirm-clear-collection"
        title="¿Vaciar seleccion?"
        message="Esta accion no se puede deshacer."
    >
        <flux:button variant="danger" wire:click="clearCollection">
            Vaciar
        </flux:button>
    </x-confirm-modal>
</div>
