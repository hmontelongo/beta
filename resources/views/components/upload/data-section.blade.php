@props(['title', 'icon' => null])

<div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
    {{-- Header --}}
    <div class="flex items-center gap-2 border-b border-zinc-200 px-4 py-2.5 dark:border-zinc-700">
        @if ($icon)
            <flux:icon :name="$icon" variant="mini" class="size-4 text-zinc-400" />
        @endif
        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $title }}</span>
    </div>

    {{-- Content --}}
    <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
        {{ $slot }}
    </div>
</div>
