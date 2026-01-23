@props([
    'property',
    'variant' => 'mobile', // 'mobile' or 'desktop'
])

@php
    $isMobile = $variant === 'mobile';
    $cardClass = $isMobile
        ? 'rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6'
        : 'rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900';
    $aspectClass = $isMobile ? 'aspect-video' : 'aspect-[4/3]';
    $hasLocationData = $property->latitude || $property->longitude || $property->address || $property->colonia || $property->city;
@endphp

@if ($hasLocationData)
<div class="{{ $cardClass }}">
    <flux:heading size="lg" class="mb-4">Ubicacion</flux:heading>

    @if ($property->latitude && $property->longitude)
        <div class="mb-4 {{ $aspectClass }} overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
            <iframe
                width="100%"
                height="100%"
                style="border:0"
                loading="lazy"
                allowfullscreen
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps/embed/v1/place?key={{ config('services.google.maps_api_key') }}&q={{ $property->latitude }},{{ $property->longitude }}&zoom=15"
            ></iframe>
        </div>
    @endif

    <div class="space-y-2 text-sm">
        @if ($property->address)
            <div class="flex items-start gap-2">
                <flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                <span class="text-zinc-600 dark:text-zinc-400">{{ $property->address }}</span>
            </div>
        @endif
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
            @if ($property->colonia)
                <span>{{ $property->colonia }}</span>
            @endif
            @if ($property->city)
                <span>{{ $property->city }}</span>
            @endif
            @if ($property->state)
                <span>{{ $property->state }}</span>
            @endif
            @if ($property->postal_code)
                <span>CP {{ $property->postal_code }}</span>
            @endif
        </div>
    </div>
</div>
@endif
