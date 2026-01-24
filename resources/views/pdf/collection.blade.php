<x-layouts.pdf :title="$collection->name">
    <x-slot:styles>
        @include('pdf.partials.styles', ['brandColor' => $brandColor])
    </x-slot:styles>

    {{-- Cover Page --}}
    @include('pdf.partials.cover', [
        'collection' => $collection,
        'properties' => $properties,
        'agent' => $agent,
        'brandColor' => $brandColor,
    ])

    {{-- Property Pages (Multi-page magazine layout) --}}
    @foreach($properties as $prop)
        {{-- Page 1: Hero & Overview --}}
        @include('pdf.partials.property.hero-page', [
            'prop' => $prop,
            'brandColor' => $brandColor,
        ])

        {{-- Page 2: Details & Amenities (conditional) --}}
        @include('pdf.partials.property.details-page', [
            'prop' => $prop,
            'brandColor' => $brandColor,
        ])

        {{-- Page 3: Gallery (if 2+ gallery images available) --}}
        @include('pdf.partials.property.gallery-page', [
            'prop' => $prop,
            'brandColor' => $brandColor,
        ])
    @endforeach

    {{-- Contact Footer on last property page --}}
    @include('pdf.partials.contact-footer', [
        'agent' => $agent,
        'collection' => $collection,
        'brandColor' => $brandColor,
        'generatedAt' => $generatedAt,
    ])
</x-layouts.pdf>
