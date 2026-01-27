@php
    use App\Services\PropertyPresenter;
    $agent = $collection->user;
    $brandColor = $agent->brand_color ?? '#3b82f6';
    $brandColorLight = $brandColor . '15';
    $brandColorMedium = $brandColor . '30';
@endphp

<div
    x-data="{ scrolled: false }"
    x-on:scroll.window="scrolled = window.scrollY > 420"
    class="min-h-screen bg-zinc-100 dark:bg-zinc-950"
>
    {{-- Sticky Mini Header (shown when scrolled past the hero) --}}
    <div
        x-show="scrolled"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-full"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-full"
        x-cloak
        class="fixed inset-x-0 top-0 z-50 border-b border-zinc-200/80 bg-white/95 shadow-sm backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/95"
    >
        <div class="mx-auto flex max-w-5xl items-center gap-4 px-6 py-3">
            {{-- Agent mini avatar --}}
            @if($agent->avatar_url)
                <img src="{{ $agent->avatar_url }}" alt="{{ $agent->display_name }}" class="size-8 rounded-full object-cover ring-2 ring-white shadow-sm" />
            @else
                <div class="flex size-8 items-center justify-center rounded-full text-sm font-bold text-white ring-2 ring-white shadow-sm" style="background: {{ $brandColor }};">
                    {{ substr($agent->display_name, 0, 1) }}
                </div>
            @endif

            {{-- Collection name --}}
            <span class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $collection->name }}</span>

            {{-- Thumbnail strip (overflow-visible for badges, hidden scrollbar) --}}
            <div class="ml-auto flex gap-2 overflow-visible py-1" style="-webkit-overflow-scrolling: touch;">
                @foreach($this->properties as $index => $prop)
                    <button
                        x-on:click="document.getElementById('property-{{ $prop['id'] }}').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                        class="group relative shrink-0 transition-transform hover:scale-105"
                        title="Propiedad {{ $index + 1 }}"
                    >
                        @if(count($prop['images']) > 0)
                            <img src="{{ $prop['images'][0] }}" alt="" class="size-10 rounded-lg object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" />
                        @else
                            <div class="flex size-10 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon name="photo" class="size-5 text-zinc-400" />
                            </div>
                        @endif
                        <span class="absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full text-[10px] font-bold text-white shadow" style="background: {{ $brandColor }};">
                            {{ $index + 1 }}
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Cover / Header Section - Magazine Style --}}
    <header class="relative overflow-hidden bg-white dark:bg-zinc-900">
        {{-- Decorative gradient background --}}
        <div class="absolute inset-0 -z-10">
            <div class="absolute inset-0 bg-gradient-to-br from-zinc-50 via-white to-zinc-100 dark:from-zinc-900 dark:via-zinc-900 dark:to-zinc-800"></div>
            <div class="absolute right-0 top-0 h-[400px] w-[600px] -translate-y-1/2 translate-x-1/4 rounded-full opacity-60" style="background: radial-gradient(circle, {{ $brandColor }}20 0%, transparent 70%);"></div>
            <div class="absolute bottom-0 left-0 h-[300px] w-[400px] translate-y-1/2 -translate-x-1/4 rounded-full opacity-40" style="background: radial-gradient(circle, {{ $brandColor }}15 0%, transparent 70%);"></div>
        </div>

        <div class="relative mx-auto max-w-5xl px-6 py-12 lg:px-8 lg:py-16">
            {{-- Agent Branding --}}
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-5">
                    {{-- Avatar with brand ring --}}
                    <div class="relative">
                        <div class="absolute -inset-1 rounded-full opacity-50 blur-sm" style="background: {{ $brandColor }}"></div>
                        @if($agent->avatar_url)
                            <img
                                src="{{ $agent->avatar_url }}"
                                alt="{{ $agent->display_name }}"
                                class="relative size-20 rounded-full object-cover ring-4 ring-white dark:ring-zinc-800 lg:size-24"
                            />
                        @else
                            <div
                                class="relative flex size-20 items-center justify-center rounded-full text-3xl font-bold text-white ring-4 ring-white dark:ring-zinc-800 lg:size-24 lg:text-4xl"
                                style="background: linear-gradient(135deg, {{ $brandColor }}, {{ $brandColor }}dd);"
                            >
                                {{ substr($agent->display_name, 0, 1) }}
                            </div>
                        @endif
                    </div>

                    <div>
                        <h2 class="text-2xl font-bold text-zinc-900 dark:text-white lg:text-3xl">
                            {{ $agent->display_name }}
                        </h2>
                        @if($agent->tagline)
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 lg:text-base">
                                {{ $agent->tagline }}
                            </p>
                        @endif
                        <div class="mt-3 flex flex-wrap gap-3">
                            @if($agent->whatsapp)
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $agent->whatsapp) }}" class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700 transition hover:bg-green-100 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50">
                                    <x-icons.whatsapp class="size-4" />
                                    {{ $agent->whatsapp }}
                                </a>
                            @endif
                            @if($agent->email)
                                <a href="mailto:{{ $agent->email }}" class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                    <flux:icon name="envelope" class="size-4" />
                                    {{ $agent->email }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Collection Title - Magazine Style --}}
            <div class="mt-10 border-t border-zinc-200 pt-10 dark:border-zinc-800 lg:mt-12 lg:pt-12">
                <div class="flex items-end gap-4">
                    <div class="h-16 w-1 rounded-full lg:h-20" style="background: linear-gradient(180deg, {{ $brandColor }}, {{ $brandColor }}66);"></div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">Coleccion exclusiva</p>
                        <h1 class="mt-1 text-4xl font-bold tracking-tight text-zinc-900 dark:text-white lg:text-5xl">
                            {{ $collection->name }}
                        </h1>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap items-center gap-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <span class="inline-flex items-center gap-2 rounded-full px-4 py-2 font-medium" style="background: {{ $brandColorLight }}; color: {{ $brandColor }};">
                        <flux:icon name="home" class="size-4" />
                        {{ $this->properties->count() }} {{ $this->properties->count() === 1 ? 'propiedad' : 'propiedades' }}
                    </span>
                    @if($collection->client)
                        <span class="inline-flex items-center gap-2">
                            <flux:icon name="user" class="size-4" />
                            Preparado para <strong class="text-zinc-900 dark:text-white">{{ $collection->client->name }}</strong>
                        </span>
                    @endif
                </div>

                {{-- Property Thumbnail Navigation --}}
                @if($this->properties->count() > 1)
                    <div class="mt-8 print:hidden">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Navega las propiedades</p>
                        <div class="flex gap-3 overflow-visible pt-2">
                            @foreach($this->properties as $index => $prop)
                                <button
                                    x-on:click="document.getElementById('property-{{ $prop['id'] }}').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                                    class="group relative shrink-0 transition-transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 rounded-xl"
                                    style="--tw-ring-color: {{ $brandColor }};"
                                    title="Ir a propiedad {{ $index + 1 }}"
                                >
                                    @if(count($prop['images']) > 0)
                                        <img src="{{ $prop['images'][0] }}" alt="" class="size-16 rounded-xl object-cover ring-2 ring-white shadow-md dark:ring-zinc-800 lg:size-20" />
                                    @else
                                        <div class="flex size-16 items-center justify-center rounded-xl bg-zinc-100 ring-2 ring-white shadow-md dark:bg-zinc-800 dark:ring-zinc-800 lg:size-20">
                                            <flux:icon name="photo" class="size-6 text-zinc-400" />
                                        </div>
                                    @endif
                                    <span class="absolute -right-2 -top-2 flex size-6 items-center justify-center rounded-full text-xs font-bold text-white shadow-lg lg:size-7 lg:text-sm" style="background: {{ $brandColor }};">
                                        {{ $index + 1 }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Bottom accent line (hidden in print/PDF to avoid thick bar) --}}
        @unless(request()->has('pdf'))
            <div class="h-1 print:hidden" style="background: linear-gradient(90deg, {{ $brandColor }}, {{ $brandColor }}66, transparent);"></div>
        @endunless
    </header>

    {{-- Properties Grid --}}
    <main class="mx-auto max-w-5xl px-6 py-12 lg:px-8 lg:py-16">
        @if ($this->properties->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-2xl bg-white py-20 text-center shadow-sm dark:bg-zinc-900">
                <div class="flex size-20 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="folder-open" class="size-10 text-zinc-400" />
                </div>
                <p class="mt-6 text-xl font-semibold text-zinc-900 dark:text-white">Esta coleccion esta vacia</p>
                <p class="mt-2 text-zinc-500">No hay propiedades en esta coleccion todavia.</p>
            </div>
        @else
            <div class="space-y-16">
                @foreach ($this->properties as $prop)
                    <article id="property-{{ $prop['id'] }}" class="group relative scroll-mt-20">
                        {{-- Property Number Badge --}}
                        <div class="absolute -left-3 -top-3 z-10 flex size-12 items-center justify-center rounded-xl text-lg font-bold text-white shadow-lg lg:-left-4 lg:-top-4 lg:size-14 lg:text-xl" style="background: linear-gradient(135deg, {{ $brandColor }}, {{ $brandColor }}cc);">
                            {{ $prop['position'] }}
                        </div>

                        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200/50 dark:bg-zinc-900 dark:ring-zinc-800">
                            {{-- Image Section --}}
                            @php
                                $selectedIndex = $selectedImages[$prop['id']] ?? 0;
                                $mainImage = $prop['images'][$selectedIndex] ?? $prop['images'][0] ?? null;
                            @endphp
                            <div class="pdf-image-section relative">
                                @if(count($prop['images']) > 0)
                                    {{-- Main Image --}}
                                    <div class="relative aspect-[16/9] w-full overflow-hidden bg-black">
                                        <img
                                            src="{{ $mainImage }}"
                                            alt="{{ $prop['colonia'] }}"
                                            class="size-full object-cover"
                                            wire:key="main-image-{{ $prop['id'] }}-{{ $selectedIndex }}"
                                        />
                                        {{-- Gradient overlay --}}
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>

                                        {{-- Price Badge on Image --}}
                                        <div class="absolute bottom-4 left-4 right-4 flex items-end justify-between">
                                            <div>
                                                <p class="text-3xl font-bold text-white drop-shadow-lg lg:text-4xl">
                                                    @if($prop['price'])
                                                        {{ PropertyPresenter::formatPrice($prop['price']) }}
                                                    @else
                                                        Consultar
                                                    @endif
                                                </p>
                                                @if($prop['pricePerM2'])
                                                    <p class="mt-1 text-sm text-white/70">
                                                        {{ PropertyPresenter::formatPricePerM2($prop['pricePerM2']) }}
                                                    </p>
                                                @endif
                                            </div>
                                            @if($prop['price'])
                                                <span class="rounded-full px-4 py-2 text-sm font-bold uppercase tracking-wide text-white shadow-lg" style="background: {{ $prop['price']['type'] === 'sale' ? $brandColor : '#10b981' }};">
                                                    {{ PropertyPresenter::operationTypeLabel($prop['price']['type']) }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Image Counter --}}
                                        @if(count($prop['images']) > 1)
                                            <div class="absolute right-4 top-4 rounded-full bg-black/50 px-3 py-1 text-sm font-medium text-white backdrop-blur-sm">
                                                {{ $selectedIndex + 1 }} / {{ min(count($prop['images']), 4) }}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Thumbnail Strip --}}
                                    @if(count($prop['images']) > 1)
                                        <div class="grid grid-cols-4 gap-1 bg-zinc-50 p-2 print:gap-0.5 print:bg-white print:p-1 dark:bg-zinc-800/50">
                                            @foreach(array_slice($prop['images'], 0, 4) as $index => $thumb)
                                                <button
                                                    wire:click="selectImage({{ $prop['id'] }}, {{ $index }})"
                                                    class="aspect-[4/3] overflow-hidden rounded-lg print:rounded-sm {{ $selectedIndex === $index ? 'ring-2 ring-offset-1' : 'opacity-80 hover:opacity-100' }}"
                                                    style="{{ $selectedIndex === $index ? '--tw-ring-color: ' . $brandColor . ';' : '' }}"
                                                    title="Ver imagen {{ $index + 1 }}"
                                                >
                                                    <img src="{{ $thumb }}" alt="" class="size-full object-cover" />
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <div class="flex aspect-[16/9] w-full items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon name="photo" class="size-20 text-zinc-300 dark:text-zinc-600" />
                                    </div>
                                @endif
                            </div>

                            {{-- Content Section --}}
                            <div class="p-6 lg:p-8">
                                {{-- Property Type & Location Row --}}
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-xl font-bold text-zinc-900 dark:text-white lg:text-2xl">
                                            {{ PropertyPresenter::propertyTypeLabel($prop['propertyType']) }}
                                            @if($prop['propertyInsights'] && !empty($prop['propertyInsights']['property_condition']))
                                                <span class="ml-2 rounded-full px-2.5 py-0.5 text-sm font-medium" style="background: {{ $brandColorLight }}; color: {{ $brandColor }};">
                                                    {{ PropertyPresenter::conditionLabel($prop['propertyInsights']['property_condition']) }}
                                                </span>
                                            @endif
                                        </h3>
                                        <p class="mt-1 flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                            <flux:icon name="map-pin" class="size-4" style="color: {{ $brandColor }};" />
                                            {{ $prop['fullAddress'] ?: ($prop['colonia'] . ($prop['city'] ? ', ' . $prop['city'] : '') . ($prop['state'] ? ', ' . $prop['state'] : '')) }}
                                        </p>
                                    </div>
                                    @if($prop['latitude'] && $prop['longitude'])
                                        <a
                                            href="https://www.google.com/maps?q={{ $prop['latitude'] }},{{ $prop['longitude'] }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                        >
                                            <flux:icon name="arrow-top-right-on-square" class="size-4" />
                                            Ver mapa
                                        </a>
                                    @endif
                                </div>

                                {{-- Specs Grid - Magazine Style --}}
                                <div class="pdf-specs-grid mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                                    @if($prop['bedrooms'])
                                        <div class="flex flex-col items-center justify-center rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                            <flux:icon name="home-modern" class="size-6 text-zinc-400" />
                                            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ $prop['bedrooms'] }}</p>
                                            <p class="text-xs text-zinc-500">{{ __('property.specs.bedrooms', [], 'es') }}</p>
                                        </div>
                                    @endif
                                    @if($prop['bathrooms'])
                                        <div class="flex flex-col items-center justify-center rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                            <svg class="size-6 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /><rect x="4" y="10" width="16" height="8" rx="2" /></svg>
                                            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ $prop['bathrooms'] }}</p>
                                            <p class="text-xs text-zinc-500">{{ __('property.specs.bathrooms', [], 'es') }}</p>
                                        </div>
                                    @endif
                                    @if($prop['halfBathrooms'])
                                        <div class="flex flex-col items-center justify-center rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                            <svg class="size-6 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="6" y="12" width="12" height="6" rx="1" /></svg>
                                            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ $prop['halfBathrooms'] }}</p>
                                            <p class="text-xs text-zinc-500">{{ __('property.specs.half_bathroom', [], 'es') }}</p>
                                        </div>
                                    @endif
                                    @if($prop['parkingSpaces'])
                                        <div class="flex flex-col items-center justify-center rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                            <flux:icon name="truck" class="size-6 text-zinc-400" />
                                            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ $prop['parkingSpaces'] }}</p>
                                            <p class="text-xs text-zinc-500">{{ __('property.specs.parkings', [], 'es') }}</p>
                                        </div>
                                    @endif
                                    @if($prop['builtSizeM2'])
                                        <div class="flex flex-col items-center justify-center rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                            <flux:icon name="square-3-stack-3d" class="size-6 text-zinc-400" />
                                            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($prop['builtSizeM2']) }}</p>
                                            <p class="text-xs text-zinc-500">m² {{ __('property.specs.built', [], 'es') }}</p>
                                        </div>
                                    @endif
                                    @if($prop['lotSizeM2'])
                                        <div class="flex flex-col items-center justify-center rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/50">
                                            <flux:icon name="map" class="size-6 text-zinc-400" />
                                            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($prop['lotSizeM2']) }}</p>
                                            <p class="text-xs text-zinc-500">m² {{ __('property.specs.lot', [], 'es') }}</p>
                                        </div>
                                    @endif
                                </div>

                                {{-- Target Audience Pills --}}
                                @if($prop['propertyInsights'] && !empty($prop['propertyInsights']['target_audience']))
                                    <div class="pdf-section mt-6">
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-500">Ideal para</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($prop['propertyInsights']['target_audience'] as $audience)
                                                <span class="inline-flex items-center rounded-full px-4 py-1.5 text-sm font-medium" style="background: {{ $brandColorMedium }}; color: {{ $brandColor }};">
                                                    {{ PropertyPresenter::targetAudienceLabel($audience) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Description --}}
                                @if($prop['description'])
                                    <div class="pdf-section mt-8 border-t border-zinc-100 pt-8 dark:border-zinc-800">
                                        <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-zinc-900 dark:text-white">
                                            <span class="h-4 w-1 rounded-full" style="background: {{ $brandColor }};"></span>
                                            Descripcion
                                        </h4>
                                        <div class="prose prose-zinc max-w-none text-zinc-600 prose-headings:text-zinc-900 prose-headings:text-base prose-headings:font-semibold prose-p:leading-relaxed prose-li:marker:text-zinc-400 dark:text-zinc-400 dark:prose-headings:text-white">
                                            {!! $prop['description'] !!}
                                        </div>
                                    </div>
                                @endif

                                {{-- Building Info --}}
                                @if($prop['buildingInfo'] && (!empty($prop['buildingInfo']['building_name']) || !empty($prop['buildingInfo']['nearby'])))
                                    <div class="pdf-section mt-8 border-t border-zinc-100 pt-8 dark:border-zinc-800">
                                        <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-zinc-900 dark:text-white">
                                            <span class="h-4 w-1 rounded-full" style="background: {{ $brandColor }};"></span>
                                            Edificio y Alrededores
                                        </h4>
                                        <div class="rounded-xl bg-zinc-50 p-5 dark:bg-zinc-800/50">
                                            @if(!empty($prop['buildingInfo']['building_name']))
                                                <p class="text-lg font-semibold text-zinc-900 dark:text-white">
                                                    {{ $prop['buildingInfo']['building_name'] }}
                                                    @if(!empty($prop['buildingInfo']['building_type']))
                                                        <span class="ml-2 text-sm font-normal text-zinc-500">
                                                            · {{ PropertyPresenter::buildingTypeLabel($prop['buildingInfo']['building_type']) }}
                                                        </span>
                                                    @endif
                                                </p>
                                            @endif
                                            @if(!empty($prop['buildingInfo']['nearby']))
                                                <div class="mt-4 flex flex-wrap gap-2">
                                                    @foreach($prop['buildingInfo']['nearby'] as $landmark)
                                                        @php
                                                            // Handle both string and array formats
                                                            $landmarkName = is_array($landmark) ? ($landmark['name'] ?? '') : $landmark;
                                                            $landmarkType = is_array($landmark) ? ($landmark['type'] ?? 'default') : 'default';
                                                            $landmarkDistance = is_array($landmark) ? ($landmark['distance'] ?? null) : null;
                                                        @endphp
                                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-sm text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700">
                                                            <span>{{ PropertyPresenter::getLandmarkIcon($landmarkType) }}</span>
                                                            {{ $landmarkName }}
                                                            @if(!empty($landmarkDistance))
                                                                <span class="text-zinc-400">· {{ $landmarkDistance }}</span>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                {{-- Amenities - Magazine Grid Style --}}
                                @if($prop['categorizedAmenities'] || count($prop['flatAmenities']) > 0)
                                    <div class="pdf-section mt-8 border-t border-zinc-100 pt-8 dark:border-zinc-800">
                                        <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-zinc-900 dark:text-white">
                                            <span class="h-4 w-1 rounded-full" style="background: {{ $brandColor }};"></span>
                                            Amenidades
                                        </h4>
                                        @if($prop['categorizedAmenities'])
                                            <div class="grid gap-6 md:grid-cols-3">
                                                @if(!empty($prop['categorizedAmenities']['in_unit'] ?? $prop['categorizedAmenities']['unit'] ?? []))
                                                    <div class="rounded-xl bg-zinc-50 p-5 dark:bg-zinc-800/50">
                                                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">En la unidad</p>
                                                        <div class="flex flex-wrap gap-2">
                                                            @foreach($prop['categorizedAmenities']['in_unit'] ?? $prop['categorizedAmenities']['unit'] ?? [] as $amenity)
                                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-sm text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700">
                                                                    <span style="color: {{ $brandColor }};">✓</span>
                                                                    {{ PropertyPresenter::humanizeAmenity($amenity) }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                                @if(!empty($prop['categorizedAmenities']['building'] ?? []))
                                                    <div class="rounded-xl bg-zinc-50 p-5 dark:bg-zinc-800/50">
                                                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Del edificio</p>
                                                        <div class="flex flex-wrap gap-2">
                                                            @foreach($prop['categorizedAmenities']['building'] as $amenity)
                                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-sm text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700">
                                                                    <span style="color: {{ $brandColor }};">✓</span>
                                                                    {{ PropertyPresenter::humanizeAmenity($amenity) }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                                @if(!empty($prop['categorizedAmenities']['services'] ?? []))
                                                    <div class="rounded-xl bg-zinc-50 p-5 dark:bg-zinc-800/50">
                                                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Servicios incluidos</p>
                                                        <div class="flex flex-wrap gap-2">
                                                            @foreach($prop['categorizedAmenities']['services'] as $service)
                                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-sm text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700">
                                                                    <span style="color: {{ $brandColor }};">✓</span>
                                                                    {{ PropertyPresenter::humanizeAmenity($service) }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($prop['flatAmenities'] as $amenity)
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-50 px-3 py-1 text-sm text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700">
                                                        <span style="color: {{ $brandColor }};">✓</span>
                                                        {{ PropertyPresenter::humanizeAmenity($amenity) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Pricing Details --}}
                                @if($prop['pricingDetails'] && (!empty($prop['pricingDetails']['included_services']) || !empty($prop['pricingDetails']['extra_costs'])))
                                    <div class="pdf-section mt-8 border-t border-zinc-100 pt-8 dark:border-zinc-800">
                                        <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-zinc-900 dark:text-white">
                                            <span class="h-4 w-1 rounded-full" style="background: {{ $brandColor }};"></span>
                                            Detalles de Precio
                                        </h4>
                                        <div class="grid gap-4 md:grid-cols-2">
                                            @if(!empty($prop['pricingDetails']['included_services']))
                                                <div class="rounded-xl bg-emerald-50 p-5 dark:bg-emerald-900/20">
                                                    <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                                                        <flux:icon name="check-circle" class="size-5" />
                                                        Incluido en el precio
                                                    </p>
                                                    <ul class="space-y-2">
                                                        @foreach($prop['pricingDetails']['included_services'] as $service)
                                                            @php
                                                                // Handle both string and array formats
                                                                $serviceLabel = is_array($service)
                                                                    ? ($service['details'] ?? ucfirst($service['service'] ?? ''))
                                                                    : PropertyPresenter::humanizeAmenity($service);
                                                            @endphp
                                                            <li class="text-sm text-emerald-800 dark:text-emerald-300">
                                                                {{ $serviceLabel }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                            @if(!empty($prop['pricingDetails']['extra_costs']))
                                                <div class="rounded-xl bg-amber-50 p-5 dark:bg-amber-900/20">
                                                    <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-400">
                                                        <flux:icon name="information-circle" class="size-5" />
                                                        Costos adicionales
                                                    </p>
                                                    <ul class="space-y-2">
                                                        @foreach($prop['pricingDetails']['extra_costs'] as $cost)
                                                            @php
                                                                // Handle both string and array formats
                                                                if (is_array($cost)) {
                                                                    $costLabel = ucfirst($cost['item'] ?? '');
                                                                    $costPrice = $cost['price'] ?? null;
                                                                    $costPeriod = $cost['period'] ?? null;
                                                                } else {
                                                                    $costLabel = PropertyPresenter::humanizeAmenity($cost);
                                                                    $costPrice = null;
                                                                    $costPeriod = null;
                                                                }
                                                            @endphp
                                                            <li class="text-sm text-amber-800 dark:text-amber-300">
                                                                {{ $costLabel }}
                                                                @if($costPrice)
                                                                    : ${{ number_format($costPrice) }}
                                                                    @if(!empty($costPeriod))
                                                                        /{{ $costPeriod === 'monthly' ? 'mes' : $costPeriod }}
                                                                    @endif
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                {{-- Rental Terms --}}
                                @if($prop['price'] && $prop['price']['type'] === 'rent' && $prop['rentalTerms'])
                                    @php $terms = $prop['rentalTerms']; @endphp
                                    @if(!empty($terms['deposit_months']) || !empty($terms['advance_months']) || isset($terms['pets_allowed']) || isset($terms['guarantor_required']) || !empty($terms['restrictions']))
                                        <div class="pdf-section mt-8 border-t border-zinc-100 pt-8 dark:border-zinc-800">
                                            <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                                <span class="h-4 w-1 rounded-full bg-amber-500"></span>
                                                Requisitos de Renta
                                            </h4>
                                            <div class="rounded-xl bg-amber-50 p-6 dark:bg-amber-900/20">
                                                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                                    @if(!empty($terms['deposit_months']))
                                                        <div class="text-center">
                                                            <p class="text-3xl font-bold text-amber-700 dark:text-amber-400">{{ $terms['deposit_months'] }}</p>
                                                            <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-500">Deposito (meses)</p>
                                                        </div>
                                                    @endif
                                                    @if(!empty($terms['advance_months']))
                                                        <div class="text-center">
                                                            <p class="text-3xl font-bold text-amber-700 dark:text-amber-400">{{ $terms['advance_months'] }}</p>
                                                            <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-500">Adelanto (meses)</p>
                                                        </div>
                                                    @endif
                                                    @if(isset($terms['pets_allowed']))
                                                        <div class="text-center">
                                                            <p class="text-3xl font-bold text-amber-700 dark:text-amber-400">{{ $terms['pets_allowed'] ? '✓' : '✗' }}</p>
                                                            <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-500">Mascotas</p>
                                                        </div>
                                                    @endif
                                                    @if(isset($terms['guarantor_required']))
                                                        <div class="text-center">
                                                            <p class="text-3xl font-bold text-amber-700 dark:text-amber-400">{{ $terms['guarantor_required'] ? 'Si' : 'No' }}</p>
                                                            <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-500">Aval requerido</p>
                                                        </div>
                                                    @endif
                                                </div>
                                                @if(!empty($terms['restrictions']))
                                                    <div class="mt-4 border-t border-amber-200 pt-4 dark:border-amber-800">
                                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-500">Notas importantes</p>
                                                        <ul class="list-inside list-disc space-y-1 text-sm text-amber-800 dark:text-amber-300">
                                                            @foreach($terms['restrictions'] as $restriction)
                                                                <li>{{ $restriction }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </main>

    {{-- Footer - Magazine Style --}}
    <footer class="border-t border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto max-w-5xl px-6 py-12 lg:px-8">
            <div class="flex flex-col items-center gap-6 text-center">
                {{-- Agent Mini Card --}}
                <div class="flex items-center gap-4">
                    @if($agent->avatar_url)
                        <img src="{{ $agent->avatar_url }}" alt="{{ $agent->display_name }}" class="size-12 rounded-full object-cover" />
                    @else
                        <div class="flex size-12 items-center justify-center rounded-full text-lg font-bold text-white" style="background: {{ $brandColor }};">
                            {{ substr($agent->display_name, 0, 1) }}
                        </div>
                    @endif
                    <div class="text-left">
                        <p class="font-semibold text-zinc-900 dark:text-white">{{ $agent->display_name }}</p>
                        <p class="text-sm text-zinc-500">{{ $agent->whatsapp ?? $agent->email }}</p>
                    </div>
                </div>

                {{-- CTA --}}
                @if($agent->whatsapp)
                    <a
                        href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $agent->whatsapp) }}?text={{ urlencode('Hola! Vi la colección "' . $collection->name . '" y me gustaría más información.') }}"
                        class="inline-flex items-center gap-2 rounded-full bg-green-600 px-6 py-3 font-semibold text-white shadow-lg transition hover:bg-green-700 hover:shadow-xl"
                    >
                        <x-icons.whatsapp class="size-5" />
                        Contactar por WhatsApp
                    </a>
                @endif

                <p class="text-xs text-zinc-400 dark:text-zinc-600">
                    Generado el {{ now()->format('d/m/Y') }}
                </p>
            </div>
        </div>
    </footer>
</div>
