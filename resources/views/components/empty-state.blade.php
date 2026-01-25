@props([
    'icon' => 'folder',
    'title',
    'subtitle' => null,
    'layout' => 'default', // default, compact, grid
])

@php
    $wrapperClasses = match($layout) {
        'compact' => 'py-12 text-center',
        'grid' => 'col-span-full py-12 text-center sm:py-16',
        default => 'flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700',
    };

    $iconWrapperClasses = match($layout) {
        'grid' => 'mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 sm:size-16',
        default => 'flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800',
    };

    $iconClasses = match($layout) {
        'grid' => 'size-7 text-zinc-400 sm:size-8',
        default => 'size-8 text-zinc-400',
    };
@endphp

<div {{ $attributes->merge(['class' => $wrapperClasses]) }}>
    <div class="{{ $iconWrapperClasses }}">
        <flux:icon :name="$icon" :class="$iconClasses" />
    </div>
    <flux:heading size="lg" @class(['mt-4' => $layout !== 'grid'])>{{ $title }}</flux:heading>
    @if($subtitle)
        <flux:subheading @class(['mt-1' => $layout === 'grid'])>{{ $subtitle }}</flux:subheading>
    @endif
    @if($slot->isNotEmpty())
        <div class="mt-4">
            {{ $slot }}
        </div>
    @endif
</div>
