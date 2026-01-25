@props(['value' => '', 'type' => 'text', 'placeholder' => '—'])

<input
    type="{{ $type }}"
    value="{{ $value }}"
    placeholder="{{ $placeholder }}"
    {{ $attributes->merge([
        'class' => 'w-full rounded border-0 bg-transparent px-1 py-0.5 text-sm text-zinc-900 placeholder:text-zinc-400 focus:bg-zinc-100 focus:ring-0 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:bg-zinc-700'
    ]) }}
/>
