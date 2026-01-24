@php
    use App\Services\CollectionPropertyPresenter;
    $agent = $collection->user;
    $brandColor = $agent->brand_color ?? '#3b82f6';
@endphp

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
    {{-- Agent Header with Brand Color Accent --}}
    <header class="border-b-4 bg-white dark:bg-zinc-900" style="border-color: {{ $brandColor }}">
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                {{-- Agent Info --}}
                <div class="flex items-center gap-4">
                    @if($agent->avatar_url)
                        <img
                            src="{{ $agent->avatar_url }}"
                            alt="{{ $agent->display_name }}"
                            class="size-16 rounded-full object-cover ring-2 ring-zinc-200 dark:ring-zinc-700 sm:size-20"
                        />
                    @else
                        <div
                            class="flex size-16 items-center justify-center rounded-full text-2xl font-bold text-white sm:size-20 sm:text-3xl"
                            style="background-color: {{ $brandColor }}"
                        >
                            {{ substr($agent->display_name, 0, 1) }}
                        </div>
                    @endif

                    <div>
                        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 sm:text-2xl">
                            {{ $agent->display_name }}
                        </h2>
                        @if($agent->tagline)
                            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $agent->tagline }}
                            </p>
                        @endif
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                            @if($agent->whatsapp)
                                <span class="flex items-center gap-1">
                                    <x-icons.whatsapp class="size-3.5 text-green-600" />
                                    {{ $agent->whatsapp }}
                                </span>
                            @endif
                            @if($agent->email)
                                <span class="flex items-center gap-1">
                                    <flux:icon name="envelope" class="size-3.5" />
                                    {{ $agent->email }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Collection Info --}}
            <div class="mt-8">
                <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 sm:text-4xl">
                    {{ $collection->name }}
                </h1>
                <p class="mt-2 text-zinc-600 dark:text-zinc-400">
                    {{ $this->properties->count() }} {{ $this->properties->count() === 1 ? 'propiedad seleccionada' : 'propiedades seleccionadas' }}
                    @if($collection->client)
                        · Preparado para {{ $collection->client->name }}
                    @endif
                </p>
            </div>
        </div>
    </header>

    {{-- Properties --}}
    <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($this->properties->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl bg-white py-16 text-center shadow-sm dark:bg-zinc-900">
                <flux:icon name="folder-open" class="size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="mt-4 text-lg font-medium text-zinc-900 dark:text-zinc-100">Esta coleccion esta vacia</p>
                <p class="mt-1 text-sm text-zinc-500">No hay propiedades en esta coleccion.</p>
            </div>
        @else
            <div class="space-y-12">
                @foreach ($this->properties as $prop)
                    <article class="overflow-hidden rounded-2xl bg-white shadow-sm dark:bg-zinc-900">
                        {{-- Property Header with Reference --}}
                        <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4 dark:border-zinc-800">
                            <div>
                                <h2 class="text-xl font-bold sm:text-2xl" style="color: {{ $brandColor }}">
                                    Propiedad #{{ $prop['position'] }}
                                </h2>
                                <p class="text-xs text-zinc-400">ID: {{ $prop['id'] }}</p>
                            </div>
                            @if($prop['price'])
                                <span
                                    class="inline-flex items-center rounded-full px-4 py-1.5 text-sm font-semibold uppercase text-white"
                                    style="background-color: {{ $prop['price']['type'] === 'sale' ? $brandColor : '#10b981' }}"
                                >
                                    {{ $prop['price']['type'] === 'sale' ? 'En Venta' : 'En Renta' }}
                                </span>
                            @endif
                        </div>

                        {{-- Image Gallery --}}
                        <div class="relative">
                            @if(count($prop['images']) > 0)
                                <div class="aspect-[16/10] w-full overflow-hidden bg-zinc-100 dark:bg-zinc-800 sm:aspect-[16/9]">
                                    <img
                                        src="{{ $prop['images'][0] }}"
                                        alt="{{ $prop['colonia'] }}"
                                        class="size-full object-cover"
                                        loading="lazy"
                                    />
                                </div>
                                @if(count($prop['images']) > 1)
                                    <div class="grid grid-cols-3 gap-1 p-1 sm:gap-2 sm:p-2">
                                        @foreach(array_slice($prop['images'], 1, 3) as $thumb)
                                            <div class="aspect-[4/3] overflow-hidden rounded bg-zinc-100 dark:bg-zinc-800">
                                                <img
                                                    src="{{ $thumb }}"
                                                    alt=""
                                                    class="size-full object-cover"
                                                    loading="lazy"
                                                />
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <div class="flex aspect-[16/10] w-full items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon name="photo" class="size-16 text-zinc-300 dark:text-zinc-600" />
                                </div>
                            @endif
                        </div>

                        {{-- Property Content --}}
                        <div class="p-6 sm:p-8">
                            {{-- Price Section --}}
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 sm:text-4xl">
                                        @if($prop['price'])
                                            ${{ number_format($prop['price']['price']) }}
                                            <span class="text-lg font-normal text-zinc-500">{{ $prop['price']['currency'] }}{{ $prop['price']['type'] === 'rent' ? '/mes' : '' }}</span>
                                        @else
                                            Consultar precio
                                        @endif
                                    </p>
                                    @if($prop['maintenanceFee'])
                                        <p class="mt-1 text-sm text-zinc-500">
                                            + ${{ number_format($prop['maintenanceFee']['amount']) }} mantenimiento/mes
                                        </p>
                                    @endif
                                </div>
                                @if($prop['pricePerM2'])
                                    <div class="text-right">
                                        <p class="text-xs text-zinc-500">Precio por m²</p>
                                        <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">${{ number_format($prop['pricePerM2']) }}</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Property Type & Condition --}}
                            <div class="mt-3">
                                <p class="text-lg font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ CollectionPropertyPresenter::getPropertyTypeLabel($prop['propertyType']) }}
                                    @if($prop['ageYears'])
                                        · {{ $prop['ageYears'] }} {{ $prop['ageYears'] === 1 ? 'año' : 'años' }}
                                    @endif
                                    @if($prop['propertyInsights'] && !empty($prop['propertyInsights']['property_condition']))
                                        · {{ CollectionPropertyPresenter::getConditionLabel($prop['propertyInsights']['property_condition']) }}
                                    @endif
                                </p>
                            </div>

                            {{-- Location Section --}}
                            <div class="mt-4 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                                <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">
                                    <flux:icon name="map-pin" class="size-4" />
                                    Ubicacion
                                </h3>
                                <p class="text-zinc-600 dark:text-zinc-400">
                                    {{ $prop['fullAddress'] ?: ($prop['colonia'] . ($prop['city'] ? ', ' . $prop['city'] : '') . ($prop['state'] ? ', ' . $prop['state'] : '')) }}
                                </p>
                                @if($prop['latitude'] && $prop['longitude'])
                                    <a
                                        href="https://www.google.com/maps?q={{ $prop['latitude'] }},{{ $prop['longitude'] }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-2 inline-flex items-center gap-1 text-sm hover:underline"
                                        style="color: {{ $brandColor }}"
                                    >
                                        <flux:icon name="arrow-top-right-on-square" class="size-3.5" />
                                        Ver en Google Maps
                                    </a>
                                @endif
                            </div>

                            {{-- Specs Grid --}}
                            <div class="mt-6 grid grid-cols-2 gap-4 border-y border-zinc-200 py-6 dark:border-zinc-700 sm:grid-cols-6">
                                @if($prop['bedrooms'])
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $prop['bedrooms'] }}</p>
                                        <p class="text-xs text-zinc-500">Recamaras</p>
                                    </div>
                                @endif
                                @if($prop['bathrooms'])
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $prop['bathrooms'] }}</p>
                                        <p class="text-xs text-zinc-500">Banos</p>
                                    </div>
                                @endif
                                @if($prop['halfBathrooms'])
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $prop['halfBathrooms'] }}</p>
                                        <p class="text-xs text-zinc-500">Medio bano</p>
                                    </div>
                                @endif
                                @if($prop['parkingSpaces'])
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $prop['parkingSpaces'] }}</p>
                                        <p class="text-xs text-zinc-500">Estacionamientos</p>
                                    </div>
                                @endif
                                @if($prop['builtSizeM2'])
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($prop['builtSizeM2']) }}</p>
                                        <p class="text-xs text-zinc-500">m² Construidos</p>
                                    </div>
                                @endif
                                @if($prop['lotSizeM2'])
                                    <div class="text-center">
                                        <p class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($prop['lotSizeM2']) }}</p>
                                        <p class="text-xs text-zinc-500">m² Terreno</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Ideal For / Target Audience --}}
                            @if($prop['propertyInsights'] && !empty($prop['propertyInsights']['target_audience']))
                                <div class="mt-6">
                                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">
                                        Ideal para
                                    </h3>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($prop['propertyInsights']['target_audience'] as $audience)
                                            <span
                                                class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                                                style="background-color: {{ $brandColor }}20; color: {{ $brandColor }}"
                                            >
                                                {{ CollectionPropertyPresenter::getTargetAudienceLabel($audience) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Full Description --}}
                            @if($prop['description'])
                                <div class="mt-6">
                                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">
                                        Descripcion
                                    </h3>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400 [&>h3]:mt-4 [&>h3]:mb-2 [&>h3]:text-sm [&>h3]:font-semibold [&>h3]:text-zinc-700 [&>h3]:dark:text-zinc-300 [&>h3:first-child]:mt-0 [&>p]:mb-3 [&>p]:leading-relaxed [&>ul]:my-2 [&>ul]:list-disc [&>ul]:pl-5 [&>ol]:my-2 [&>ol]:list-decimal [&>ol]:pl-5 [&>li]:mb-1">
                                        {!! $prop['description'] !!}
                                    </div>
                                </div>
                            @endif

                            {{-- Building Info / Nearby Places --}}
                            @if($prop['buildingInfo'] && (!empty($prop['buildingInfo']['building_name']) || !empty($prop['buildingInfo']['nearby']) || !empty($prop['buildingInfo']['building_type'])))
                                <div class="mt-6">
                                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">
                                        Edificio y Alrededores
                                    </h3>
                                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                                        @if(!empty($prop['buildingInfo']['building_name']))
                                            <p class="font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ $prop['buildingInfo']['building_name'] }}
                                                @if(!empty($prop['buildingInfo']['building_type']))
                                                    <span class="font-normal text-zinc-500">
                                                        · {{ CollectionPropertyPresenter::getBuildingTypeLabel($prop['buildingInfo']['building_type']) }}
                                                    </span>
                                                @endif
                                            </p>
                                        @endif
                                        @if(!empty($prop['buildingInfo']['nearby']))
                                            <div class="mt-3 space-y-2">
                                                <p class="text-xs font-medium text-zinc-500">Lugares cercanos:</p>
                                                <div class="grid gap-2 sm:grid-cols-2">
                                                    @foreach($prop['buildingInfo']['nearby'] as $landmark)
                                                        <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                                            <span class="text-base">{{ CollectionPropertyPresenter::getLandmarkIcon($landmark['type'] ?? '') }}</span>
                                                            <span>{{ $landmark['name'] ?? '' }}</span>
                                                            @if(!empty($landmark['distance']))
                                                                <span class="text-zinc-400">· {{ $landmark['distance'] }}</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- All Amenities --}}
                            @if($prop['categorizedAmenities'] || count($prop['flatAmenities']) > 0)
                                <div class="mt-6">
                                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">Amenidades</h3>
                                    @if($prop['categorizedAmenities'])
                                        <div class="grid gap-6 sm:grid-cols-2">
                                            @if(!empty($prop['categorizedAmenities']['in_unit'] ?? $prop['categorizedAmenities']['unit'] ?? []))
                                                <div>
                                                    <p class="mb-2 text-xs font-medium text-zinc-500">En la unidad</p>
                                                    <div class="space-y-1">
                                                        @foreach($prop['categorizedAmenities']['in_unit'] ?? $prop['categorizedAmenities']['unit'] ?? [] as $amenity)
                                                            <p class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                                                <span style="color: {{ $brandColor }}">✓</span>
                                                                {{ CollectionPropertyPresenter::humanizeAmenity($amenity) }}
                                                            </p>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                            @if(!empty($prop['categorizedAmenities']['building'] ?? []))
                                                <div>
                                                    <p class="mb-2 text-xs font-medium text-zinc-500">Del edificio</p>
                                                    <div class="space-y-1">
                                                        @foreach($prop['categorizedAmenities']['building'] as $amenity)
                                                            <p class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                                                <span style="color: {{ $brandColor }}">✓</span>
                                                                {{ CollectionPropertyPresenter::humanizeAmenity($amenity) }}
                                                            </p>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                            @if(!empty($prop['categorizedAmenities']['services'] ?? []))
                                                <div>
                                                    <p class="mb-2 text-xs font-medium text-zinc-500">Servicios incluidos</p>
                                                    <div class="space-y-1">
                                                        @foreach($prop['categorizedAmenities']['services'] as $service)
                                                            <p class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                                                <span style="color: {{ $brandColor }}">✓</span>
                                                                {{ CollectionPropertyPresenter::humanizeAmenity($service) }}
                                                            </p>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            @foreach($prop['flatAmenities'] as $amenity)
                                                <p class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                                    <span style="color: {{ $brandColor }}">✓</span>
                                                    {{ CollectionPropertyPresenter::humanizeAmenity($amenity) }}
                                                </p>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Pricing Details (included services, extra costs) --}}
                            @if($prop['pricingDetails'] && (!empty($prop['pricingDetails']['included_services']) || !empty($prop['pricingDetails']['extra_costs'])))
                                <div class="mt-6">
                                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">Detalles de Precio</h3>
                                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                        @if(!empty($prop['pricingDetails']['included_services']))
                                            <div class="mb-3">
                                                <p class="mb-2 text-xs font-medium text-green-600">Incluido en el precio:</p>
                                                <div class="space-y-1">
                                                    @foreach($prop['pricingDetails']['included_services'] as $service)
                                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                                            <span class="text-green-600">✓</span>
                                                            {{ $service['details'] ?? ucfirst($service['service'] ?? '') }}
                                                        </p>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        @if(!empty($prop['pricingDetails']['extra_costs']))
                                            <div>
                                                <p class="mb-2 text-xs font-medium text-amber-600">Costos adicionales:</p>
                                                <div class="space-y-1">
                                                    @foreach($prop['pricingDetails']['extra_costs'] as $cost)
                                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                                            {{ ucfirst($cost['item'] ?? '') }}:
                                                            ${{ number_format($cost['price'] ?? 0) }}
                                                            @if(!empty($cost['period']))
                                                                /{{ $cost['period'] === 'monthly' ? 'mes' : $cost['period'] }}
                                                            @endif
                                                            @if(!empty($cost['note']))
                                                                <span class="text-zinc-400">({{ $cost['note'] }})</span>
                                                            @endif
                                                        </p>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Rental Terms (for rentals) --}}
                            @if($prop['price'] && $prop['price']['type'] === 'rent' && $prop['rentalTerms'])
                                @php $terms = $prop['rentalTerms']; @endphp
                                @if(!empty($terms['deposit_months']) || !empty($terms['advance_months']) || isset($terms['pets_allowed']) || isset($terms['guarantor_required']) || !empty($terms['income_proof_months']) || !empty($terms['restrictions']))
                                    <div class="mt-6">
                                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">Requisitos de Renta</h3>
                                        <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/20">
                                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                                @if(!empty($terms['deposit_months']))
                                                    <div class="text-center">
                                                        <p class="text-xl font-bold text-amber-700 dark:text-amber-400">{{ $terms['deposit_months'] }}</p>
                                                        <p class="text-xs text-amber-600 dark:text-amber-500">Deposito (meses)</p>
                                                    </div>
                                                @endif
                                                @if(!empty($terms['advance_months']))
                                                    <div class="text-center">
                                                        <p class="text-xl font-bold text-amber-700 dark:text-amber-400">{{ $terms['advance_months'] }}</p>
                                                        <p class="text-xs text-amber-600 dark:text-amber-500">Adelanto (meses)</p>
                                                    </div>
                                                @endif
                                                @if(!empty($terms['income_proof_months']))
                                                    <div class="text-center">
                                                        <p class="text-xl font-bold text-amber-700 dark:text-amber-400">{{ $terms['income_proof_months'] }}</p>
                                                        <p class="text-xs text-amber-600 dark:text-amber-500">Comprobante ingresos</p>
                                                    </div>
                                                @endif
                                                @if(isset($terms['pets_allowed']))
                                                    <div class="text-center">
                                                        <p class="text-xl font-bold text-amber-700 dark:text-amber-400">{{ $terms['pets_allowed'] ? '✓ Si' : '✗ No' }}</p>
                                                        <p class="text-xs text-amber-600 dark:text-amber-500">Mascotas</p>
                                                    </div>
                                                @endif
                                                @if(isset($terms['guarantor_required']))
                                                    <div class="text-center">
                                                        <p class="text-xl font-bold text-amber-700 dark:text-amber-400">{{ $terms['guarantor_required'] ? 'Si' : 'No' }}</p>
                                                        <p class="text-xs text-amber-600 dark:text-amber-500">Aval requerido</p>
                                                    </div>
                                                @endif
                                            </div>
                                            @if(!empty($terms['restrictions']))
                                                <div class="mt-4 border-t border-amber-200 pt-3 dark:border-amber-800">
                                                    <p class="text-xs font-medium text-amber-600 dark:text-amber-500">Restricciones:</p>
                                                    <ul class="mt-1 list-disc pl-4 text-sm text-amber-700 dark:text-amber-400">
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

                            {{-- Sale Requirements (for sales) --}}
                            @if($prop['price'] && $prop['price']['type'] === 'sale' && $prop['rentalTerms'])
                                @php $terms = $prop['rentalTerms']; @endphp
                                @if(!empty($terms['legal_policy']) || !empty($terms['restrictions']))
                                    <div class="mt-6">
                                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-300">Informacion Adicional de Venta</h3>
                                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                            @if(!empty($terms['legal_policy']))
                                                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $terms['legal_policy'] }}</p>
                                            @endif
                                            @if(!empty($terms['restrictions']))
                                                <div class="mt-2">
                                                    <p class="text-xs font-medium text-zinc-500">Notas:</p>
                                                    <ul class="mt-1 list-disc pl-4 text-sm text-zinc-600 dark:text-zinc-400">
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
                    </article>
                @endforeach
            </div>
        @endif
    </main>

    {{-- Footer --}}
    <footer class="border-t border-zinc-200 bg-white py-8 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $agent->display_name }}</span>
                    @if($agent->whatsapp)
                        <span class="mx-2">·</span>
                        <span>{{ $agent->whatsapp }}</span>
                    @endif
                    @if($agent->email)
                        <span class="mx-2">·</span>
                        <span>{{ $agent->email }}</span>
                    @endif
                </div>
                <p class="text-xs text-zinc-400">
                    Generado el {{ now()->format('d/m/Y') }}
                </p>
            </div>
        </div>
    </footer>
</div>
