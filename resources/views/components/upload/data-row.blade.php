@props(['label', 'required' => false, 'note' => null])

<div class="flex items-center gap-2 px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
    {{-- Key (label) --}}
    <div class="w-36 shrink-0">
        <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
            {{ $label }}@if($required)<span class="text-red-500">*</span>@endif
        </span>
        @if ($note)
            <span class="ml-1 font-mono text-[10px] text-zinc-400">({{ $note }})</span>
        @endif
    </div>

    {{-- Colon --}}
    <span class="font-mono text-xs text-zinc-400">:</span>

    {{-- Value (editable) --}}
    <div class="min-w-0 flex-1">
        {{ $slot }}
    </div>
</div>
