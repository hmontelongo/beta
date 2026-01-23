@props([
    'rentalTerms',
    'variant' => 'mobile', // 'mobile' or 'desktop'
])

@php
    $isMobile = $variant === 'mobile';
    $cardClass = $isMobile
        ? 'rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6'
        : 'rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900';
    $gridGap = $isMobile ? 'gap-4' : 'gap-3';
    $cellPadding = $isMobile ? 'p-3' : 'p-2.5';
    $numberSize = $isMobile ? 'text-lg' : 'text-lg';
    $iconSize = $isMobile ? 'size-5' : 'size-4';
    $bottomGap = $isMobile ? 'gap-4' : 'gap-3';
@endphp

<div class="{{ $cardClass }}">
    <flux:heading size="lg" class="mb-4">Condiciones de renta</flux:heading>
    <div class="grid grid-cols-3 {{ $gridGap }}">
        @if (! empty($rentalTerms['deposit_months']))
            <div class="rounded-lg bg-zinc-50 {{ $cellPadding }} text-center dark:bg-zinc-800">
                <p class="text-xs text-zinc-500">Deposito</p>
                <p class="{{ $numberSize }} font-bold text-zinc-900 dark:text-zinc-100">{{ $rentalTerms['deposit_months'] }}</p>
                <p class="text-xs text-zinc-500">{{ $rentalTerms['deposit_months'] === 1 ? 'mes' : 'meses' }}</p>
            </div>
        @endif
        @if (! empty($rentalTerms['advance_months']))
            <div class="rounded-lg bg-zinc-50 {{ $cellPadding }} text-center dark:bg-zinc-800">
                <p class="text-xs text-zinc-500">Adelanto</p>
                <p class="{{ $numberSize }} font-bold text-zinc-900 dark:text-zinc-100">{{ $rentalTerms['advance_months'] }}</p>
                <p class="text-xs text-zinc-500">{{ $rentalTerms['advance_months'] === 1 ? 'mes' : 'meses' }}</p>
            </div>
        @endif
        @if (! empty($rentalTerms['income_proof_months']))
            <div class="rounded-lg bg-zinc-50 {{ $cellPadding }} text-center dark:bg-zinc-800">
                <p class="text-xs text-zinc-500">Comprobante</p>
                <p class="{{ $numberSize }} font-bold text-zinc-900 dark:text-zinc-100">{{ $rentalTerms['income_proof_months'] }}x</p>
                <p class="text-xs text-zinc-500">ingresos</p>
            </div>
        @endif
    </div>
    <div class="mt-4 flex flex-wrap {{ $bottomGap }}">
        @if (isset($rentalTerms['pets_allowed']))
            <div class="flex items-center {{ $isMobile ? 'gap-2' : 'gap-1.5' }} text-sm">
                @if ($rentalTerms['pets_allowed'])
                    <flux:icon name="check-circle" class="{{ $iconSize }} text-green-500" />
                    <span class="text-zinc-700 dark:text-zinc-300">Mascotas permitidas</span>
                @else
                    <flux:icon name="x-circle" class="{{ $iconSize }} text-red-500" />
                    <span class="text-zinc-700 dark:text-zinc-300">No se permiten mascotas</span>
                @endif
            </div>
        @endif
        @if (isset($rentalTerms['guarantor_required']))
            <div class="flex items-center {{ $isMobile ? 'gap-2' : 'gap-1.5' }} text-sm">
                @if ($rentalTerms['guarantor_required'])
                    <flux:icon name="user-circle" class="{{ $iconSize }} text-amber-500" />
                    <span class="text-zinc-700 dark:text-zinc-300">Requiere aval</span>
                @else
                    <flux:icon name="check-circle" class="{{ $iconSize }} text-green-500" />
                    <span class="text-zinc-700 dark:text-zinc-300">Sin aval requerido</span>
                @endif
            </div>
        @endif
        @if (! empty($rentalTerms['max_occupants']))
            <div class="flex items-center {{ $isMobile ? 'gap-2' : 'gap-1.5' }} text-sm">
                <flux:icon name="users" class="{{ $iconSize }} text-zinc-400" />
                <span class="text-zinc-700 dark:text-zinc-300">Max {{ $rentalTerms['max_occupants'] }}{{ $isMobile ? ' personas' : '' }}</span>
            </div>
        @endif
    </div>
</div>
