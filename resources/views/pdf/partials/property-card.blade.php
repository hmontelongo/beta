@php
    use App\Services\CollectionPropertyPresenter;
@endphp

<div class="property-card">
    {{-- Image Section --}}
    <div class="property-header">
        {{-- Position Badge --}}
        <div class="property-number">{{ $prop['position'] }}</div>

        @if(count($prop['images']) > 0)
            {{-- Main Image with Price Overlay --}}
            <div style="position: relative;">
                <img src="{{ $prop['images'][0] }}" alt="{{ $prop['colonia'] }}" class="property-main-image">

                {{-- Gradient overlay --}}
                <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 50%); border-radius: 8px;"></div>

                {{-- Price Badge --}}
                <div class="property-price-overlay">
                    <div class="property-price">
                        @if($prop['price'])
                            <div class="price-amount">
                                ${{ number_format($prop['price']['price']) }}
                                <span class="price-currency">{{ $prop['price']['currency'] }}{{ $prop['price']['type'] === 'rent' ? '/mes' : '' }}</span>
                            </div>
                            @if($prop['pricePerM2'])
                                <div class="price-per-m2">${{ number_format($prop['pricePerM2']) }}/m²</div>
                            @endif
                        @else
                            <div class="price-amount">Consultar</div>
                        @endif
                    </div>
                    @if($prop['price'])
                        <span class="property-type-badge {{ $prop['price']['type'] === 'sale' ? 'badge-sale' : 'badge-rent' }}">
                            {{ $prop['price']['type'] === 'sale' ? 'Venta' : 'Renta' }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Thumbnails --}}
            @if(count($prop['images']) > 1)
                <div class="property-thumbnails">
                    @foreach(array_slice($prop['images'], 1, 4) as $thumb)
                        <img src="{{ $thumb }}" alt="" class="property-thumb">
                    @endforeach
                </div>
            @endif
        @else
            <div style="width: 100%; height: 2.8in; background: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #ccc;">
                Sin imagenes
            </div>
        @endif
    </div>

    {{-- Property Info --}}
    <div class="property-info">
        {{-- Title Row --}}
        <div class="property-title-row">
            <div>
                <span class="property-type">
                    {{ CollectionPropertyPresenter::getPropertyTypeLabel($prop['propertyType']) }}
                </span>
                @if($prop['propertyInsights'] && !empty($prop['propertyInsights']['property_condition']))
                    <span class="property-condition">
                        {{ CollectionPropertyPresenter::getConditionLabel($prop['propertyInsights']['property_condition']) }}
                    </span>
                @endif
            </div>
            @if($prop['latitude'] && $prop['longitude'])
                <span class="map-link">Ver mapa</span>
            @endif
        </div>

        {{-- Address --}}
        <div class="property-address">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="{{ $brandColor }}" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                <circle cx="12" cy="10" r="3"/>
            </svg>
            {{ $prop['fullAddress'] ?: ($prop['colonia'] . ($prop['city'] ? ', ' . $prop['city'] : '') . ($prop['state'] ? ', ' . $prop['state'] : '')) }}
        </div>

        {{-- Specs Grid --}}
        <div class="specs-grid">
            @if($prop['bedrooms'])
                <div class="spec-item">
                    <div class="spec-value">{{ $prop['bedrooms'] }}</div>
                    <div class="spec-label">Recamaras</div>
                </div>
            @endif
            @if($prop['bathrooms'])
                <div class="spec-item">
                    <div class="spec-value">{{ $prop['bathrooms'] }}</div>
                    <div class="spec-label">Banos</div>
                </div>
            @endif
            @if($prop['halfBathrooms'])
                <div class="spec-item">
                    <div class="spec-value">{{ $prop['halfBathrooms'] }}</div>
                    <div class="spec-label">Medio bano</div>
                </div>
            @endif
            @if($prop['parkingSpaces'])
                <div class="spec-item">
                    <div class="spec-value">{{ $prop['parkingSpaces'] }}</div>
                    <div class="spec-label">Estac.</div>
                </div>
            @endif
            @if($prop['builtSizeM2'])
                <div class="spec-item">
                    <div class="spec-value">{{ number_format($prop['builtSizeM2']) }}</div>
                    <div class="spec-label">m² Const.</div>
                </div>
            @endif
            @if($prop['lotSizeM2'])
                <div class="spec-item">
                    <div class="spec-value">{{ number_format($prop['lotSizeM2']) }}</div>
                    <div class="spec-label">m² Terreno</div>
                </div>
            @endif
        </div>

        {{-- Target Audience --}}
        @if($prop['propertyInsights'] && !empty($prop['propertyInsights']['target_audience']))
            <div class="target-audience">
                <div class="target-label">Ideal para</div>
                <div class="audience-tags">
                    @foreach(array_slice($prop['propertyInsights']['target_audience'], 0, 4) as $audience)
                        <span class="audience-tag">
                            {{ CollectionPropertyPresenter::getTargetAudienceLabel($audience) }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Description (truncated) --}}
        @if($prop['description'])
            <div class="section-header">Descripcion</div>
            <div class="property-description">
                {!! Str::limit(strip_tags($prop['description']), 250) !!}
                @if(strlen(strip_tags($prop['description'])) > 250)
                    <div class="description-truncated">Ver descripcion completa en linea</div>
                @endif
            </div>
        @endif

        {{-- Amenities (limited) --}}
        @php
            $allAmenities = collect();
            if ($prop['categorizedAmenities']) {
                $allAmenities = $allAmenities
                    ->merge($prop['categorizedAmenities']['in_unit'] ?? $prop['categorizedAmenities']['unit'] ?? [])
                    ->merge($prop['categorizedAmenities']['building'] ?? [])
                    ->merge($prop['categorizedAmenities']['services'] ?? []);
            } elseif (!empty($prop['flatAmenities'])) {
                $allAmenities = collect($prop['flatAmenities']);
            }
            $displayAmenities = $allAmenities->take(8);
            $remainingCount = max(0, $allAmenities->count() - 8);
        @endphp

        @if($allAmenities->isNotEmpty())
            <div class="section-header">Amenidades</div>
            <div class="amenities-grid">
                @foreach($displayAmenities as $amenity)
                    <span class="amenity-tag">
                        <span class="amenity-check">✓</span>
                        {{ CollectionPropertyPresenter::humanizeAmenity($amenity) }}
                    </span>
                @endforeach
                @if($remainingCount > 0)
                    <span class="amenity-more">+{{ $remainingCount }} mas</span>
                @endif
            </div>
        @endif
    </div>
</div>
