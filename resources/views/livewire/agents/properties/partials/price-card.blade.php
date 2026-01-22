<flux:card class="p-4 sm:p-6">
    {{-- Price --}}
    @if ($this->primaryPrice)
        <div class="mb-4">
            <div class="flex items-center gap-2 mb-1">
                <flux:badge size="sm" :color="$this->primaryPrice['type'] === 'rent' ? 'blue' : 'green'">
                    {{ $this->primaryPrice['type'] === 'rent' ? 'Renta' : 'Venta' }}
                </flux:badge>
            </div>
            <div class="flex items-baseline gap-1">
                <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    ${{ number_format($this->primaryPrice['price']) }}
                </span>
                <span class="text-sm text-zinc-500">{{ $this->primaryPrice['currency'] }}</span>
                @if ($this->primaryPrice['type'] === 'rent')
                    <span class="text-sm text-zinc-400">/mes</span>
                @endif
            </div>
            @if ($this->primaryPrice['maintenance_fee'])
                <p class="text-sm text-zinc-500 mt-1">
                    + ${{ number_format($this->primaryPrice['maintenance_fee']) }} mant.
                </p>
            @endif
        </div>
    @endif

    {{-- Key Stats --}}
    <div class="grid grid-cols-2 gap-4 py-4 border-y border-zinc-100 dark:border-zinc-800">
        @if ($property->bedrooms)
            <div>
                <p class="text-xs text-zinc-500 uppercase tracking-wide">Recamaras</p>
                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $property->bedrooms }}</p>
            </div>
        @endif
        @if ($property->bathrooms)
            <div>
                <p class="text-xs text-zinc-500 uppercase tracking-wide">Banos</p>
                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $property->bathrooms }}</p>
            </div>
        @endif
        @if ($property->parking_spots)
            <div>
                <p class="text-xs text-zinc-500 uppercase tracking-wide">Estacionamiento</p>
                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $property->parking_spots }}</p>
            </div>
        @endif
        @if ($property->built_size_m2)
            <div>
                <p class="text-xs text-zinc-500 uppercase tracking-wide">Construccion</p>
                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($property->built_size_m2) }} m<sup>2</sup></p>
            </div>
        @endif
        @if ($property->lot_size_m2)
            <div>
                <p class="text-xs text-zinc-500 uppercase tracking-wide">Terreno</p>
                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($property->lot_size_m2) }} m<sup>2</sup></p>
            </div>
        @endif
        @if ($property->age_years)
            <div>
                <p class="text-xs text-zinc-500 uppercase tracking-wide">Antiguedad</p>
                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $property->age_years }} {{ Str::plural('ano', $property->age_years) }}</p>
            </div>
        @endif
    </div>

    {{-- Property Type --}}
    @if ($property->property_type)
        <div class="mt-4">
            <p class="text-xs text-zinc-500 uppercase tracking-wide">Tipo de propiedad</p>
            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ ucfirst($property->property_type->value) }}</p>
        </div>
    @endif

    {{-- Location Summary --}}
    <div class="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
        <div class="flex items-start gap-2">
            <flux:icon name="map-pin" class="size-4 text-zinc-400 mt-0.5 shrink-0" />
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                @if ($property->colonia)
                    <span class="font-medium">{{ $property->colonia }}</span>
                @endif
                @if ($property->city)
                    <span>, {{ $property->city }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Add to Collection Button (Mobile) --}}
    <div class="mt-4 lg:hidden">
        <flux:button
            wire:click="toggleCollection"
            :variant="$this->isInCollection() ? 'primary' : 'outline'"
            class="w-full"
            :icon="$this->isInCollection() ? 'check' : 'plus'"
        >
            {{ $this->isInCollection() ? 'En tu coleccion' : 'Agregar a coleccion' }}
        </flux:button>
    </div>
</flux:card>
