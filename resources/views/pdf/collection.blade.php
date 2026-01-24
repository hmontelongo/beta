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

    {{-- Property Pages --}}
    @foreach($properties as $prop)
        @include('pdf.partials.property-card', [
            'prop' => $prop,
            'brandColor' => $brandColor,
            'isLast' => $loop->last,
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
