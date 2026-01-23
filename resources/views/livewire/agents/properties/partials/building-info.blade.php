@props([
    'buildingInfo',
    'variant' => 'mobile', // 'mobile' or 'desktop'
])

@php
    $isMobile = $variant === 'mobile';
    $cardClass = $isMobile
        ? 'rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6'
        : 'rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900';
    $iconSize = $isMobile ? 'size-10' : 'size-9';
    $iconInner = $isMobile ? 'size-5' : 'size-4';
@endphp

@if ($buildingInfo && (! empty($buildingInfo['building_name']) || ! empty($buildingInfo['nearby'])))
    <div class="{{ $cardClass }}">
        <flux:heading size="lg" class="mb-4">Sobre este edificio</flux:heading>

        @if (! empty($buildingInfo['building_name']))
            <div class="mb-4 flex items-center gap-3">
                <div class="flex {{ $iconSize }} items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="building-office-2" class="{{ $iconInner }} text-zinc-600 dark:text-zinc-400" />
                </div>
                <div>
                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $buildingInfo['building_name'] }}</p>
                    @if (! empty($buildingInfo['building_type']))
                        <p class="text-sm text-zinc-500">{{ $buildingInfo['building_type'] }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if (! empty($buildingInfo['nearby']))
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500">Cerca de aqui</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($buildingInfo['nearby'] as $landmark)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-3 py-1.5 text-sm dark:bg-zinc-800">
                        <span>{{ $this->getLandmarkIcon($landmark['type'] ?? 'default') }}</span>
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $landmark['name'] }}</span>
                        @if (! empty($landmark['distance']))
                            <span class="text-zinc-400">{{ $landmark['distance'] }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        @endif
    </div>
@endif
