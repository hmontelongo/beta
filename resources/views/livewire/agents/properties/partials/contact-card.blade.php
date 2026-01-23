@props([
    'publishers',
    'variant' => 'mobile', // 'mobile' or 'desktop'
])

@php
    $isMobile = $variant === 'mobile';
    $cardClass = $isMobile
        ? 'rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900'
        : 'rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900';
@endphp

@if ($publishers->isNotEmpty())
    <div class="{{ $cardClass }}">
        <flux:heading size="lg" class="mb-4">Contacto</flux:heading>
        <div class="space-y-4">
            @foreach ($publishers as $publisher)
                @php
                    $contactNumber = $publisher->whatsapp ?: $publisher->phone;
                    $cleanNumber = $contactNumber ? preg_replace('/[^0-9]/', '', $contactNumber) : null;
                @endphp
                <div class="{{ ! $loop->last ? 'border-b border-zinc-100 pb-4 dark:border-zinc-800' : '' }}">
                    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $publisher->name }}</p>
                    <p class="text-xs text-zinc-400">{{ $publisher->type->label() }}</p>
                    @if ($cleanNumber)
                        <a href="https://wa.me/{{ $cleanNumber }}"
                           target="_blank"
                           class="mt-2 inline-flex items-center gap-1.5 text-sm text-green-600 hover:text-green-700 dark:text-green-500">
                            <x-icons.whatsapp class="size-4" />
                            {{ $contactNumber }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
