@props([
    'propertyInsights',
    'variant' => 'mobile', // 'mobile' or 'desktop'
])

@php
    $isMobile = $variant === 'mobile';
    $cardClass = $isMobile
        ? 'rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6'
        : 'rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900';
    $gridGap = $isMobile ? 'gap-3' : 'gap-2';
    $emojiSize = $isMobile ? 'text-2xl' : 'text-xl';
    $textSize = $isMobile ? 'text-sm' : 'text-xs';
@endphp

@if ($propertyInsights && (! empty($propertyInsights['target_audience']) || ! empty($propertyInsights['occupancy_type']) || ! empty($propertyInsights['property_condition'])))
    <div class="{{ $cardClass }}">
        <flux:heading size="lg" class="mb-4">Bueno saber</flux:heading>
        <div class="grid grid-cols-3 {{ $gridGap }}">
            @if (! empty($propertyInsights['target_audience']))
                <div class="rounded-xl bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                    <div class="mb-1 {{ $emojiSize }}">👥</div>
                    <div class="text-xs text-zinc-500">Ideal para</div>
                    <div class="{{ $textSize }} font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatTargetAudience($propertyInsights['target_audience']) }}</div>
                </div>
            @endif
            @if (! empty($propertyInsights['occupancy_type']))
                <div class="rounded-xl bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                    <div class="mb-1 {{ $emojiSize }}">🏠</div>
                    <div class="text-xs text-zinc-500">Mejor para</div>
                    <div class="{{ $textSize }} font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatOccupancyType($propertyInsights['occupancy_type']) }}</div>
                </div>
            @endif
            @if (! empty($propertyInsights['property_condition']))
                <div class="rounded-xl bg-zinc-50 p-3 text-center dark:bg-zinc-800">
                    <div class="mb-1 {{ $emojiSize }}">✨</div>
                    <div class="text-xs text-zinc-500">Condicion</div>
                    <div class="{{ $textSize }} font-medium text-zinc-900 dark:text-zinc-100">{{ $this->formatPropertyCondition($propertyInsights['property_condition']) }}</div>
                </div>
            @endif
        </div>
    </div>
@endif
