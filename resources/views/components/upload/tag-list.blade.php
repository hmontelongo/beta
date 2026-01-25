@props(['items' => [], 'path', 'placeholder' => '+ agregar'])

<div class="flex flex-wrap items-center gap-1.5" x-data="{ newItem: '', isAdding: false }">
    {{-- Existing items --}}
    @foreach ($items as $index => $item)
        <span class="inline-flex items-center gap-1 rounded bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
            {{ $item }}
            <button
                type="button"
                wire:click="removeFromArray('{{ $path }}', {{ $index }})"
                class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
            >
                <flux:icon name="x-mark" variant="micro" class="size-3" />
            </button>
        </span>
    @endforeach

    {{-- Add new item --}}
    <template x-if="!isAdding">
        <button
            type="button"
            @click="isAdding = true; $nextTick(() => $refs.input.focus())"
            class="text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
        >
            {{ $placeholder }}
        </button>
    </template>

    <template x-if="isAdding">
        <input
            type="text"
            x-ref="input"
            x-model="newItem"
            @keydown.enter.prevent="if(newItem.trim()) { $wire.addToArray('{{ $path }}', newItem.trim()); newItem = ''; }"
            @keydown.escape="isAdding = false; newItem = ''"
            @blur="if(newItem.trim()) { $wire.addToArray('{{ $path }}', newItem.trim()); } newItem = ''; isAdding = false"
            placeholder="Escribir..."
            class="w-24 rounded border border-zinc-300 bg-white px-2 py-0.5 text-xs focus:border-zinc-400 focus:outline-none focus:ring-0 dark:border-zinc-600 dark:bg-zinc-700"
        />
    </template>
</div>
