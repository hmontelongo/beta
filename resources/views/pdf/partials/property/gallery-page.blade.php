@php
    use App\Services\CollectionPropertyPresenter;
@endphp

@if(count($prop['galleryImages']) >= 2)
<div class="property-page gallery-page">
    {{-- Property Header Bar --}}
    <div class="details-header">
        <span class="details-position">{{ $prop['position'] }}</span>
        <span class="details-title">Galeria de Fotos</span>
    </div>

    {{-- Photo Gallery Grid (2x3) --}}
    <div class="gallery-grid">
        @foreach($prop['galleryImages'] as $image)
            <div class="gallery-item">
                <img src="{{ $image }}" alt="Foto {{ $loop->iteration }}" class="gallery-image">
            </div>
        @endforeach
    </div>

    {{-- Property Summary Card --}}
    <div class="summary-card">
        <div class="summary-header">Resumen de la Propiedad</div>
        <div class="summary-content">
            <div class="summary-main">
                <span class="summary-type">{{ CollectionPropertyPresenter::getPropertyTypeLabel($prop['propertyType']) }}</span>
                @if($prop['bedrooms'])
                    <span class="summary-sep">·</span>
                    <span>{{ $prop['bedrooms'] }} recamaras</span>
                @endif
                @if($prop['bathrooms'])
                    <span class="summary-sep">·</span>
                    <span>{{ $prop['bathrooms'] }} banos</span>
                @endif
                @if($prop['builtSizeM2'])
                    <span class="summary-sep">·</span>
                    <span>{{ number_format($prop['builtSizeM2']) }} m²</span>
                @endif
            </div>
            <div class="summary-price">
                @if($prop['price'])
                    ${{ number_format($prop['price']['price']) }} {{ $prop['price']['currency'] }}{{ $prop['price']['type'] === 'rent' ? '/mes' : '' }}
                @else
                    Precio a consultar
                @endif
            </div>
            <div class="summary-location">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
                {{ $prop['colonia'] }}{{ $prop['city'] ? ', ' . $prop['city'] : '' }}
            </div>
        </div>
    </div>
</div>
@endif
