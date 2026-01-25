@props([
    'icon' => null,
    'count',
    'singular' => null,
    'plural' => null,
    'size' => 'sm', // sm, xs
])

@php
    $iconSize = match($size) {
        'xs' => 'size-3.5',
        default => 'size-4',
    };

    $label = match(true) {
        $singular && $plural => $count === 1 ? $singular : $plural,
        default => $singular ?? $plural,
    };
@endphp

<span {{ $attributes->merge(['class' => 'flex items-center gap-1 text-zinc-600 dark:text-zinc-400']) }}>
    @if($icon)
        <flux:icon :name="$icon" :class="$iconSize" />
    @endif
    {{ $count }}{{ $label ? ' ' . $label : '' }}
</span>
