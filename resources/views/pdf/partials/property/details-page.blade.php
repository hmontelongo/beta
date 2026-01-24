@php
    use App\Services\PropertyPresenter;
    use Illuminate\Support\Str;

    // Collect all amenities
    $unitAmenities = collect();
    $buildingAmenities = collect();
    $serviceAmenities = collect();

    if ($prop['categorizedAmenities']) {
        $unitAmenities = collect($prop['categorizedAmenities']['in_unit'] ?? $prop['categorizedAmenities']['unit'] ?? []);
        $buildingAmenities = collect($prop['categorizedAmenities']['building'] ?? []);
        $serviceAmenities = collect($prop['categorizedAmenities']['services'] ?? []);
    } elseif (!empty($prop['flatAmenities'])) {
        $unitAmenities = collect($prop['flatAmenities']);
    }

    $hasAmenities = $unitAmenities->isNotEmpty() || $buildingAmenities->isNotEmpty() || $serviceAmenities->isNotEmpty();
    $hasBuilding = $prop['buildingInfo'] && (!empty($prop['buildingInfo']['building_name']) || !empty($prop['buildingInfo']['nearby_landmarks']));

    // Only show rental terms if we have actual content
    $hasRentalTerms = $prop['price']
        && $prop['price']['type'] === 'rent'
        && $prop['rentalTerms']
        && (
            !empty($prop['rentalTerms']['deposit'])
            || !empty($prop['rentalTerms']['advance'])
            || isset($prop['rentalTerms']['pets_allowed'])
            || !empty($prop['rentalTerms']['guarantor'])
            || !empty($prop['rentalTerms']['minimum_lease'])
            || !empty($prop['rentalTerms']['restrictions'])
        );

    // Only show pricing if we have actual extra costs (not just included_services)
    $hasPricing = !empty($prop['pricingDetails']['extra_costs']);

    $hasMaintenanceFee = $prop['maintenanceFee'];

    // Only render this page if there's content to show
    $hasContent = $hasAmenities || $hasBuilding || $hasRentalTerms || $hasPricing || $hasMaintenanceFee;
@endphp

@if($hasContent)
<div class="property-page details-page">
    {{-- Property Header Bar --}}
    <div class="details-header">
        <span class="details-position">{{ $prop['position'] }}</span>
        <span class="details-section-title">Características y Amenidades</span>
    </div>

    {{-- Building & Location Info --}}
    @if($hasBuilding)
        <div class="info-section">
            <div class="section-header">Edificio y Ubicacion</div>
            <div class="building-info">
                @if(!empty($prop['buildingInfo']['building_name']))
                    <div class="building-name">
                        <span class="building-icon">🏢</span>
                        {{ $prop['buildingInfo']['building_name'] }}
                        @if(!empty($prop['buildingInfo']['building_type']))
                            <span class="building-type">
                                {{ PropertyPresenter::buildingTypeLabel($prop['buildingInfo']['building_type']) }}
                            </span>
                        @endif
                    </div>
                @endif

                @if(!empty($prop['buildingInfo']['nearby_landmarks']))
                    <div class="nearby-landmarks">
                        <div class="landmarks-label">Cerca de:</div>
                        <div class="landmarks-list">
                            @foreach(array_slice($prop['buildingInfo']['nearby_landmarks'], 0, 6) as $landmark)
                                <div class="landmark-item">
                                    <span class="landmark-icon">{{ PropertyPresenter::getLandmarkIcon($landmark['type'] ?? 'default') }}</span>
                                    <span class="landmark-name">{{ $landmark['name'] }}</span>
                                    @if(!empty($landmark['distance']))
                                        <span class="landmark-distance">({{ $landmark['distance'] }})</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Unit Amenities --}}
    @if($unitAmenities->isNotEmpty())
        <div class="info-section">
            <div class="section-header">Amenidades de la Unidad</div>
            <div class="amenities-grid full">
                @foreach($unitAmenities as $amenity)
                    <span class="amenity-tag">
                        <span class="amenity-icon">{{ PropertyPresenter::getAmenityIcon($amenity) }}</span>
                        {{ PropertyPresenter::humanizeAmenity($amenity) }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Building Amenities --}}
    @if($buildingAmenities->isNotEmpty())
        <div class="info-section">
            <div class="section-header">Amenidades del Edificio</div>
            <div class="amenities-grid full">
                @foreach($buildingAmenities as $amenity)
                    <span class="amenity-tag">
                        <span class="amenity-icon">{{ PropertyPresenter::getAmenityIcon($amenity) }}</span>
                        {{ PropertyPresenter::humanizeAmenity($amenity) }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Services --}}
    @if($serviceAmenities->isNotEmpty())
        <div class="info-section">
            <div class="section-header">Servicios Incluidos</div>
            <div class="amenities-grid full">
                @foreach($serviceAmenities as $amenity)
                    <span class="amenity-tag">
                        <span class="amenity-icon">{{ PropertyPresenter::getAmenityIcon($amenity) }}</span>
                        {{ PropertyPresenter::humanizeAmenity($amenity) }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Rental Terms --}}
    @if($hasRentalTerms)
        <div class="info-section">
            <div class="section-header">Requisitos de Renta</div>
            <div class="rental-terms">
                @if(!empty($prop['rentalTerms']['deposit']))
                    <div class="term-item">
                        <span class="term-label">Deposito:</span>
                        <span class="term-value">{{ $prop['rentalTerms']['deposit'] }}</span>
                    </div>
                @endif
                @if(!empty($prop['rentalTerms']['advance']))
                    <div class="term-item">
                        <span class="term-label">Adelanto:</span>
                        <span class="term-value">{{ $prop['rentalTerms']['advance'] }}</span>
                    </div>
                @endif
                @if(isset($prop['rentalTerms']['pets_allowed']))
                    <div class="term-item">
                        <span class="term-label">Mascotas:</span>
                        <span class="term-value">{{ $prop['rentalTerms']['pets_allowed'] ? '✓ Permitidas' : '✗ No permitidas' }}</span>
                    </div>
                @endif
                @if(!empty($prop['rentalTerms']['guarantor']))
                    <div class="term-item">
                        <span class="term-label">Aval:</span>
                        <span class="term-value">{{ $prop['rentalTerms']['guarantor'] }}</span>
                    </div>
                @endif
                @if(!empty($prop['rentalTerms']['minimum_lease']))
                    <div class="term-item">
                        <span class="term-label">Contrato minimo:</span>
                        <span class="term-value">{{ $prop['rentalTerms']['minimum_lease'] }}</span>
                    </div>
                @endif
                @if(!empty($prop['rentalTerms']['restrictions']))
                    <div class="term-restrictions">
                        <span class="term-label">Restricciones:</span>
                        @foreach($prop['rentalTerms']['restrictions'] as $restriction)
                            <span class="restriction-tag">{{ is_array($restriction) ? ($restriction['name'] ?? json_encode($restriction)) : $restriction }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Additional Costs --}}
    @if($hasPricing || $hasMaintenanceFee)
        <div class="info-section">
            <div class="section-header">Costos Adicionales</div>
            <div class="pricing-details">
                @if($hasMaintenanceFee)
                    <div class="cost-item highlight">
                        <span class="cost-label">Mantenimiento:</span>
                        <span class="cost-value">{{ PropertyPresenter::formatMaintenanceFee(['amount' => $prop['maintenanceFee'], 'currency' => 'MXN', 'period' => 'monthly']) }}</span>
                    </div>
                @endif
                @if(!empty($prop['pricingDetails']['extra_costs']))
                    @foreach($prop['pricingDetails']['extra_costs'] as $cost)
                        <div class="cost-item">
                            <span class="cost-label">{{ $cost['name'] ?? 'Costo adicional' }}:</span>
                            <span class="cost-value">
                                @if(!empty($cost['amount']))
                                    ${{ number_format($cost['amount']) }}
                                    @if(!empty($cost['frequency']))
                                        /{{ $cost['frequency'] }}
                                    @endif
                                @else
                                    {{ $cost['description'] ?? 'Consultar' }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @endif

    {{-- Included Services (from pricing) --}}
    @if(!empty($prop['pricingDetails']['included_services']))
        <div class="info-section">
            <div class="section-header">Servicios Incluidos en el Precio</div>
            <div class="included-services">
                @foreach($prop['pricingDetails']['included_services'] as $service)
                    @php
                        $serviceName = $service;
                        if (is_array($service)) {
                            $serviceName = $service['name'] ?? $service['service'] ?? $service['description'] ?? null;
                            if (!$serviceName && !empty($service)) {
                                $serviceName = reset($service);
                            }
                        }
                    @endphp
                    @if($serviceName)
                        <span class="service-tag">
                            <span class="service-check">✓</span>
                            {{ Str::ucfirst($serviceName) }}
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
@endif
