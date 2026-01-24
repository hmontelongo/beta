@php
    use App\Services\CollectionPropertyPresenter;
    $brandColor = $agent->brand_color ?? '#3b82f6';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $collection->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Print-specific styles */
        @page {
            size: letter;
            margin: 0.5in;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        /* Page break control */
        .property-card {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .page-break-before {
            page-break-before: always;
            break-before: page;
        }

        /* Ensure backgrounds print */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Custom brand color */
        .brand-color { color: {{ $brandColor }}; }
        .brand-bg { background-color: {{ $brandColor }}; }
        .brand-border { border-color: {{ $brandColor }}; }
        .brand-bg-light { background-color: {{ $brandColor }}20; }
    </style>
</head>
<body class="bg-zinc-50 text-zinc-600">
    {{-- Header --}}
    <header class="bg-white border-b-4 brand-border pb-6 mb-8">
        <div class="max-w-4xl mx-auto">
            {{-- Agent Info --}}
            <div class="flex items-center gap-4">
                @if($avatarBase64)
                    <img
                        src="{{ $avatarBase64 }}"
                        alt="{{ $agent->display_name }}"
                        class="w-16 h-16 rounded-full object-cover ring-2 ring-zinc-200"
                    />
                @else
                    <div
                        class="w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold text-white brand-bg"
                    >
                        {{ substr($agent->display_name, 0, 1) }}
                    </div>
                @endif

                <div>
                    <h2 class="text-xl font-bold text-zinc-900">
                        {{ $agent->display_name }}
                    </h2>
                    @if($agent->tagline)
                        <p class="text-sm text-zinc-500">
                            {{ $agent->tagline }}
                        </p>
                    @endif
                    <div class="mt-1 flex flex-wrap gap-x-3 text-sm text-zinc-600">
                        @if($agent->whatsapp)
                            <span>{{ $agent->whatsapp }}</span>
                        @endif
                        @if($agent->email)
                            <span>{{ $agent->email }}</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Collection Info --}}
            <div class="mt-6">
                <h1 class="text-3xl font-bold text-zinc-900">
                    {{ $collection->name }}
                </h1>
                <p class="mt-2 text-zinc-600">
                    {{ count($properties) }} {{ count($properties) === 1 ? 'propiedad seleccionada' : 'propiedades seleccionadas' }}
                    @if($collection->client)
                        · Preparado para {{ $collection->client->name }}
                    @endif
                </p>
            </div>
        </div>
    </header>

    {{-- Properties --}}
    <main class="max-w-4xl mx-auto space-y-8">
        @foreach ($properties as $index => $prop)
            <article class="property-card bg-white rounded-2xl overflow-hidden shadow-sm {{ $index > 0 ? 'page-break-before' : '' }}">
                {{-- Property Header --}}
                <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4">
                    <div>
                        <h2 class="text-xl font-bold brand-color">
                            Propiedad #{{ $prop['position'] }}
                        </h2>
                        <p class="text-xs text-zinc-400">ID: {{ $prop['id'] }}</p>
                    </div>
                    @if($prop['price'])
                        <span
                            class="inline-flex items-center rounded-full px-4 py-1.5 text-sm font-semibold uppercase text-white {{ $prop['price']['type'] === 'sale' ? 'brand-bg' : 'bg-emerald-500' }}"
                        >
                            {{ $prop['price']['type'] === 'sale' ? 'En Venta' : 'En Renta' }}
                        </span>
                    @endif
                </div>

                {{-- Image Gallery --}}
                @if(count($prop['images']) > 0)
                    <div class="aspect-[16/9] w-full overflow-hidden bg-zinc-100">
                        <img
                            src="{{ $prop['images'][0] }}"
                            alt="{{ $prop['colonia'] }}"
                            class="w-full h-full object-cover"
                        />
                    </div>
                    @if(count($prop['images']) > 1)
                        <div class="grid grid-cols-3 gap-2 p-2">
                            @foreach(array_slice($prop['images'], 1, 3) as $thumb)
                                <div class="aspect-[4/3] overflow-hidden rounded bg-zinc-100">
                                    <img
                                        src="{{ $thumb }}"
                                        alt=""
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                {{-- Property Content --}}
                <div class="p-6">
                    {{-- Price Section --}}
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-3xl font-bold text-zinc-900">
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
                                <p class="text-lg font-semibold text-zinc-900">${{ number_format($prop['pricePerM2']) }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Property Type --}}
                    <div class="mt-3">
                        <p class="text-lg font-medium text-zinc-700">
                            {{ CollectionPropertyPresenter::getPropertyTypeLabel($prop['propertyType']) }}
                            @if($prop['ageYears'])
                                · {{ $prop['ageYears'] }} {{ $prop['ageYears'] === 1 ? 'año' : 'años' }}
                            @endif
                            @if($prop['propertyInsights'] && !empty($prop['propertyInsights']['property_condition']))
                                · {{ CollectionPropertyPresenter::getConditionLabel($prop['propertyInsights']['property_condition']) }}
                            @endif
                        </p>
                    </div>

                    {{-- Location --}}
                    <div class="mt-4 rounded-lg bg-zinc-50 p-4">
                        <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-700">
                            📍 Ubicación
                        </h3>
                        <p class="text-zinc-600">
                            {{ $prop['fullAddress'] ?: ($prop['colonia'] . ($prop['city'] ? ', ' . $prop['city'] : '') . ($prop['state'] ? ', ' . $prop['state'] : '')) }}
                        </p>
                    </div>

                    {{-- Specs Grid --}}
                    @php
                        $specs = [];
                        if($prop['bedrooms']) $specs[] = ['value' => $prop['bedrooms'], 'label' => 'Recámaras'];
                        if($prop['bathrooms']) $specs[] = ['value' => $prop['bathrooms'], 'label' => 'Baños'];
                        if($prop['halfBathrooms']) $specs[] = ['value' => $prop['halfBathrooms'], 'label' => 'Medio baño'];
                        if($prop['parkingSpaces']) $specs[] = ['value' => $prop['parkingSpaces'], 'label' => 'Estacionamientos'];
                        if($prop['builtSizeM2']) $specs[] = ['value' => number_format($prop['builtSizeM2']), 'label' => 'm² Construidos'];
                        if($prop['lotSizeM2']) $specs[] = ['value' => number_format($prop['lotSizeM2']), 'label' => 'm² Terreno'];
                    @endphp
                    @if(count($specs) > 0)
                        <div class="mt-6 grid grid-cols-{{ min(count($specs), 6) }} gap-4 border-y border-zinc-200 py-6">
                            @foreach($specs as $spec)
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-zinc-900">{{ $spec['value'] }}</p>
                                    <p class="text-xs text-zinc-500">{{ $spec['label'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Ideal For --}}
                    @if($prop['propertyInsights'] && !empty($prop['propertyInsights']['target_audience']))
                        <div class="mt-6">
                            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-700">
                                Ideal para
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($prop['propertyInsights']['target_audience'] as $audience)
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium brand-bg-light brand-color">
                                        {{ CollectionPropertyPresenter::getTargetAudienceLabel($audience) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Description --}}
                    @if($prop['description'])
                        <div class="mt-6">
                            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-700">
                                Descripción
                            </h3>
                            <div class="text-sm text-zinc-600 [&>h3]:mt-4 [&>h3]:mb-2 [&>h3]:text-sm [&>h3]:font-semibold [&>h3]:text-zinc-700 [&>h3:first-child]:mt-0 [&>p]:mb-3 [&>p]:leading-relaxed [&>ul]:my-2 [&>ul]:list-disc [&>ul]:pl-5 [&>ol]:my-2 [&>ol]:list-decimal [&>ol]:pl-5 [&>li]:mb-1">
                                {!! $prop['description'] !!}
                            </div>
                        </div>
                    @endif

                    {{-- Building & Nearby --}}
                    @if($prop['buildingInfo'] && (!empty($prop['buildingInfo']['building_name']) || !empty($prop['buildingInfo']['nearby'])))
                        <div class="mt-6">
                            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-700">
                                Edificio y Alrededores
                            </h3>
                            <div class="rounded-lg bg-zinc-50 p-4">
                                @if(!empty($prop['buildingInfo']['building_name']))
                                    <p class="font-semibold text-zinc-900">
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
                                        <div class="grid gap-2 grid-cols-2">
                                            @foreach($prop['buildingInfo']['nearby'] as $landmark)
                                                <div class="flex items-center gap-2 text-sm text-zinc-600">
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

                    {{-- Amenities --}}
                    @if($prop['categorizedAmenities'] || count($prop['flatAmenities']) > 0)
                        <div class="mt-6">
                            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-700">Amenidades</h3>
                            @if($prop['categorizedAmenities'])
                                <div class="grid gap-6 grid-cols-2">
                                    @if(!empty($prop['categorizedAmenities']['in_unit'] ?? $prop['categorizedAmenities']['unit'] ?? []))
                                        <div>
                                            <p class="mb-2 text-xs font-medium text-zinc-500">En la unidad</p>
                                            <div class="space-y-1">
                                                @foreach($prop['categorizedAmenities']['in_unit'] ?? $prop['categorizedAmenities']['unit'] ?? [] as $amenity)
                                                    <p class="flex items-center gap-2 text-sm text-zinc-600">
                                                        <span class="brand-color">✓</span>
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
                                                    <p class="flex items-center gap-2 text-sm text-zinc-600">
                                                        <span class="brand-color">✓</span>
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
                                                    <p class="flex items-center gap-2 text-sm text-zinc-600">
                                                        <span class="brand-color">✓</span>
                                                        {{ CollectionPropertyPresenter::humanizeAmenity($service) }}
                                                    </p>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach($prop['flatAmenities'] as $amenity)
                                        <p class="flex items-center gap-2 text-sm text-zinc-600">
                                            <span class="brand-color">✓</span>
                                            {{ CollectionPropertyPresenter::humanizeAmenity($amenity) }}
                                        </p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Pricing Details --}}
                    @if($prop['pricingDetails'] && (!empty($prop['pricingDetails']['included_services']) || !empty($prop['pricingDetails']['extra_costs'])))
                        <div class="mt-6">
                            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-700">Detalles de Precio</h3>
                            <div class="rounded-lg border border-zinc-200 p-4">
                                @if(!empty($prop['pricingDetails']['included_services']))
                                    <div class="mb-3">
                                        <p class="mb-2 text-xs font-medium text-green-600">Incluido en el precio:</p>
                                        <div class="space-y-1">
                                            @foreach($prop['pricingDetails']['included_services'] as $service)
                                                <p class="text-sm text-zinc-600">
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
                                                <p class="text-sm text-zinc-600">
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

                    {{-- Rental Terms --}}
                    @if($prop['price'] && $prop['price']['type'] === 'rent' && $prop['rentalTerms'])
                        @php $terms = $prop['rentalTerms']; @endphp
                        @if(!empty($terms['deposit_months']) || !empty($terms['advance_months']) || isset($terms['pets_allowed']) || isset($terms['guarantor_required']) || !empty($terms['income_proof_months']) || !empty($terms['restrictions']))
                            <div class="mt-6">
                                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-amber-700">Requisitos de Renta</h3>
                                <div class="rounded-lg bg-amber-50 p-4">
                                    <div class="grid grid-cols-4 gap-4">
                                        @if(!empty($terms['deposit_months']))
                                            <div class="text-center">
                                                <p class="text-xl font-bold text-amber-700">{{ $terms['deposit_months'] }}</p>
                                                <p class="text-xs text-amber-600">Depósito (meses)</p>
                                            </div>
                                        @endif
                                        @if(!empty($terms['advance_months']))
                                            <div class="text-center">
                                                <p class="text-xl font-bold text-amber-700">{{ $terms['advance_months'] }}</p>
                                                <p class="text-xs text-amber-600">Adelanto (meses)</p>
                                            </div>
                                        @endif
                                        @if(!empty($terms['income_proof_months']))
                                            <div class="text-center">
                                                <p class="text-xl font-bold text-amber-700">{{ $terms['income_proof_months'] }}</p>
                                                <p class="text-xs text-amber-600">Comprobante ingresos</p>
                                            </div>
                                        @endif
                                        @if(isset($terms['pets_allowed']))
                                            <div class="text-center">
                                                <p class="text-xl font-bold text-amber-700">{{ $terms['pets_allowed'] ? '✓ Sí' : '✗ No' }}</p>
                                                <p class="text-xs text-amber-600">Mascotas</p>
                                            </div>
                                        @endif
                                        @if(isset($terms['guarantor_required']))
                                            <div class="text-center">
                                                <p class="text-xl font-bold text-amber-700">{{ $terms['guarantor_required'] ? 'Sí' : 'No' }}</p>
                                                <p class="text-xs text-amber-600">Aval requerido</p>
                                            </div>
                                        @endif
                                    </div>
                                    @if(!empty($terms['restrictions']))
                                        <div class="mt-4 border-t border-amber-200 pt-3">
                                            <p class="text-xs font-medium text-amber-600">Restricciones:</p>
                                            <ul class="mt-1 list-disc pl-4 text-sm text-amber-700">
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

                    {{-- Sale Info --}}
                    @if($prop['price'] && $prop['price']['type'] === 'sale' && $prop['rentalTerms'])
                        @php $terms = $prop['rentalTerms']; @endphp
                        @if(!empty($terms['legal_policy']) || !empty($terms['restrictions']))
                            <div class="mt-6">
                                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-700">Información de Venta</h3>
                                <div class="rounded-lg border border-zinc-200 p-4">
                                    @if(!empty($terms['legal_policy']))
                                        <p class="text-sm text-zinc-600">{{ $terms['legal_policy'] }}</p>
                                    @endif
                                    @if(!empty($terms['restrictions']))
                                        <div class="mt-2">
                                            <p class="text-xs font-medium text-zinc-500">Notas:</p>
                                            <ul class="mt-1 list-disc pl-4 text-sm text-zinc-600">
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
    </main>

    {{-- Footer --}}
    <footer class="mt-8 border-t border-zinc-200 bg-white py-6">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between">
                <div class="text-sm text-zinc-600">
                    <span class="font-medium text-zinc-900">{{ $agent->display_name }}</span>
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
</body>
</html>
