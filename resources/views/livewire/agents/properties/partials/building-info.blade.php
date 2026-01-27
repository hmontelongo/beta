@use('App\Services\PropertyPresenter')

@props([
    'buildingInfo',
    'buildingAmenities' => [],
    'variant' => 'mobile', // 'mobile' or 'desktop'
])

@php
    $cardClass = 'overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900';

    // Get building highlights (top 3 most notable amenities)
    $highlights = ! empty($buildingAmenities) ? PropertyPresenter::getBuildingHighlights($buildingAmenities, 3) : [];
    $hasContent = ($buildingInfo && (! empty($buildingInfo['building_name']) || ! empty($buildingInfo['nearby']))) || ! empty($highlights);
@endphp

@if ($hasContent)
    <div class="{{ $cardClass }}">
        {{-- Card Header --}}
        <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
            <div class="flex size-8 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                <flux:icon name="building-office-2" variant="mini" class="size-4 text-zinc-600 dark:text-zinc-400" />
            </div>
            <h3 class="font-medium text-zinc-900 dark:text-zinc-100">Sobre este edificio</h3>
        </div>

        <div class="space-y-4 p-4">
            {{-- Building Name & Type --}}
            @if (! empty($buildingInfo['building_name']))
                <div>
                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $buildingInfo['building_name'] }}</p>
                    @if (! empty($buildingInfo['building_type']))
                        <p class="text-sm text-zinc-500">{{ PropertyPresenter::buildingTypeLabel($buildingInfo['building_type']) }}</p>
                    @endif
                </div>
            @endif

            {{-- Nearby Landmarks --}}
            @if (! empty($buildingInfo['nearby']))
                <div>
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Cerca de aqui</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($buildingInfo['nearby'] as $landmark)
                            @php
                                $landmarkName = is_array($landmark) ? ($landmark['name'] ?? '') : $landmark;
                                $landmarkType = is_array($landmark) ? ($landmark['type'] ?? 'default') : 'default';
                                $landmarkDistance = is_array($landmark) ? ($landmark['distance'] ?? null) : null;
                            @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                                <flux:icon :name="PropertyPresenter::getLandmarkHeroicon($landmarkType)" variant="mini" class="size-4 text-zinc-500" />
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $landmarkName }}</span>
                                @if (! empty($landmarkDistance))
                                    <span class="text-zinc-400">{{ $landmarkDistance }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Building Highlights --}}
            @if (! empty($highlights))
                <div>
                    <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">Destacados</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($highlights as $amenity)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                <flux:icon :name="PropertyPresenter::getAmenityHeroicon($amenity)" variant="mini" class="size-4 text-zinc-500 dark:text-zinc-400" />
                                {{ PropertyPresenter::humanizeAmenity($amenity) }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
