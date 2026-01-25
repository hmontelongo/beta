@props(['title', 'icon' => null, 'collapsed' => false])

<div
    x-data="{ open: {{ $collapsed ? 'false' : 'true' }} }"
    class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800"
>
    {{-- Header (clickable) --}}
    <button
        type="button"
        @click="open = !open"
        class="flex w-full items-center gap-2 px-4 py-2.5 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/30"
        :class="{ 'border-b border-zinc-200 dark:border-zinc-700': open }"
    >
        @if ($icon)
            <flux:icon :name="$icon" variant="mini" class="size-4 text-zinc-400" />
        @endif
        <span class="flex-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $title }}</span>
        <flux:icon
            name="chevron-down"
            variant="mini"
            class="size-4 text-zinc-400 transition-transform duration-200"
            ::class="{ 'rotate-180': !open }"
        />
    </button>

    {{-- Content (collapsible) --}}
    <div
        x-show="open"
        x-collapse
        class="divide-y divide-zinc-100 dark:divide-zinc-700/50"
    >
        {{ $slot }}
    </div>
</div>
