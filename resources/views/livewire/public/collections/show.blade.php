<div class="min-h-screen">
    {{-- Collection Header --}}
    <div class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto max-w-screen-xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="mb-2 text-sm font-medium text-zinc-500">Coleccion de propiedades</p>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 sm:text-3xl">
                    {{ $collection->name }}
                </h1>
                @if ($collection->description)
                    <p class="mx-auto mt-3 max-w-2xl text-zinc-600 dark:text-zinc-400">
                        {{ $collection->description }}
                    </p>
                @endif
                <p class="mt-4 text-sm text-zinc-500">
                    {{ $collection->properties->count() }} {{ $collection->properties->count() === 1 ? 'propiedad' : 'propiedades' }}
                    &middot;
                    Creada por {{ $collection->user->name }}
                </p>
            </div>
        </div>
    </div>

    {{-- Properties Grid --}}
    <div class="mx-auto max-w-screen-xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($collection->properties->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <flux:icon name="folder-open" class="size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="mt-4 text-lg font-medium text-zinc-900 dark:text-zinc-100">Esta coleccion esta vacia</p>
                <p class="mt-1 text-sm text-zinc-500">No hay propiedades en esta coleccion.</p>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($collection->properties as $property)
                    <div class="group overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        {{-- Property Image --}}
                        @php
                            $images = collect($property->listings->first()?->raw_data['images'] ?? [])
                                ->map(fn ($img) => is_array($img) ? $img['url'] : $img)
                                ->filter(fn ($url) => !str_contains($url, '.svg') && !str_contains($url, 'placeholder'))
                                ->take(1);
                            $image = $images->first();

                            $price = null;
                            $priceType = null;
                            foreach ($property->listings as $listing) {
                                $operations = $listing->raw_data['operations'] ?? [];
                                foreach ($operations as $op) {
                                    if (($op['price'] ?? 0) > 0) {
                                        $price = $op['price'];
                                        $priceType = $op['type'] ?? 'unknown';
                                        break 2;
                                    }
                                }
                            }
                        @endphp

                        <div class="relative aspect-[4/3] overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                            @if ($image)
                                <img
                                    src="{{ $image }}"
                                    alt="{{ $property->address }}"
                                    class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    loading="lazy"
                                />
                            @else
                                <div class="flex size-full items-center justify-center">
                                    <flux:icon name="photo" class="size-12 text-zinc-300 dark:text-zinc-600" />
                                </div>
                            @endif

                            {{-- Price Badge --}}
                            @if ($price)
                                <div class="absolute bottom-3 left-3 rounded-lg bg-white/95 px-3 py-1.5 shadow-sm backdrop-blur-sm dark:bg-zinc-900/95">
                                    <span class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                        ${{ number_format($price) }}
                                    </span>
                                    @if ($priceType === 'rent')
                                        <span class="text-sm text-zinc-500">/mes</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Property Details --}}
                        <div class="p-4">
                            {{-- Location --}}
                            <div class="flex items-start gap-2">
                                <flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $property->colonia }}
                                    </p>
                                    @if ($property->city)
                                        <p class="truncate text-sm text-zinc-500">{{ $property->city }}</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Stats --}}
                            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                                @if ($property->bedrooms)
                                    <span>{{ $property->bedrooms }} rec</span>
                                @endif
                                @if ($property->bathrooms)
                                    <span>{{ $property->bathrooms }} banos</span>
                                @endif
                                @if ($property->built_size_m2)
                                    <span>{{ number_format($property->built_size_m2) }} m²</span>
                                @endif
                            </div>

                            {{-- Property Type --}}
                            @if ($property->property_type)
                                <div class="mt-3">
                                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ ucfirst($property->property_type->value) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Footer CTA --}}
    <div class="border-t border-zinc-200 bg-zinc-100 py-12 text-center dark:border-zinc-800 dark:bg-zinc-900">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            ¿Quieres crear tus propias colecciones?
        </p>
        <a href="{{ route('register') }}" class="mt-2 inline-flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
            Registrate gratis
            <flux:icon name="arrow-right" class="size-4" />
        </a>
    </div>
</div>
