<div class="min-h-screen">
    {{-- Agent Contact Bar (Sticky) --}}
    <div class="sticky top-0 z-50 border-b border-zinc-200 bg-white/95 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/95">
        <div class="mx-auto max-w-screen-xl px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <flux:avatar :name="$collection->user->name" size="sm" />
                    <div>
                        <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $collection->user->name }}</p>
                        <p class="text-xs text-zinc-500">Agente inmobiliario</p>
                    </div>
                </div>
                {{-- Contact button: WhatsApp > Email > Fallback text --}}
                @if($collection->user->whatsapp)
                    @php
                        $phone = preg_replace('/[^0-9]/', '', $collection->user->whatsapp);
                        $message = "Hola! Vi la coleccion '{$collection->name}' y me gustaria mas informacion.";
                        $whatsappUrl = "https://wa.me/{$phone}?text=" . urlencode($message);
                    @endphp
                    <a
                        href="{{ $whatsappUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-700"
                    >
                        <x-icons.whatsapp class="size-4" />
                        Contactar
                    </a>
                @elseif($collection->user->email)
                    <a
                        href="mailto:{{ $collection->user->email }}?subject={{ urlencode('Consulta: ' . $collection->name) }}&body={{ urlencode("Hola! Vi la coleccion '{$collection->name}' y me gustaria mas informacion.") }}"
                        class="inline-flex items-center gap-2 rounded-lg bg-zinc-800 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-zinc-900 dark:bg-zinc-700 dark:hover:bg-zinc-600"
                    >
                        <flux:icon name="envelope" class="size-4" />
                        Enviar email
                    </a>
                @else
                    <span class="text-sm text-zinc-500">
                        Busca a {{ $collection->user->name }}
                    </span>
                @endif
            </div>
        </div>
    </div>

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
                    Seleccionadas para ti
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

                            {{-- Property Type + Interest Button --}}
                            <div class="mt-3 flex items-center justify-between">
                                @if ($property->property_type)
                                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ $property->property_type->labelEs() }}
                                    </span>
                                @else
                                    <span></span>
                                @endif

                                {{-- "Me interesa" button --}}
                                @if($collection->user->whatsapp)
                                    @php
                                        $propertyPhone = preg_replace('/[^0-9]/', '', $collection->user->whatsapp);
                                        $propertyMessage = "Hola! Me interesa la propiedad en {$property->colonia}" .
                                            ($price ? " de $" . number_format($price) . ($priceType === 'rent' ? '/mes' : '') : '') .
                                            " de la coleccion '{$collection->name}'.";
                                        $propertyWhatsappUrl = "https://wa.me/{$propertyPhone}?text=" . urlencode($propertyMessage);
                                    @endphp
                                    <a
                                        href="{{ $propertyWhatsappUrl }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-green-700"
                                    >
                                        <svg class="size-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                        </svg>
                                        Me interesa
                                    </a>
                                @elseif($collection->user->email)
                                    @php
                                        $propertySubject = "Me interesa: {$property->colonia}";
                                        $propertyBody = "Hola! Me interesa la propiedad en {$property->colonia}" .
                                            ($price ? " de $" . number_format($price) . ($priceType === 'rent' ? '/mes' : '') : '') .
                                            " de la coleccion '{$collection->name}'.";
                                    @endphp
                                    <a
                                        href="mailto:{{ $collection->user->email }}?subject={{ urlencode($propertySubject) }}&body={{ urlencode($propertyBody) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-700 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-zinc-800 dark:bg-zinc-600 dark:hover:bg-zinc-500"
                                    >
                                        <flux:icon name="envelope" class="size-3.5" />
                                        Me interesa
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Footer CTA --}}
    <div class="border-t border-zinc-200 bg-zinc-50 py-12 text-center dark:border-zinc-800 dark:bg-zinc-900">
        <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
            ¿Te interesa alguna propiedad?
        </p>
        <p class="mt-1 text-sm text-zinc-500">
            Contacta a {{ $collection->user->name }} para mas informacion
        </p>
        {{-- Contact button: WhatsApp > Email > Fallback --}}
        @if($collection->user->whatsapp)
            @php
                $phone = preg_replace('/[^0-9]/', '', $collection->user->whatsapp);
                $message = "Hola! Vi la coleccion '{$collection->name}' y me gustaria mas informacion.";
                $whatsappUrl = "https://wa.me/{$phone}?text=" . urlencode($message);
            @endphp
            <a
                href="{{ $whatsappUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 inline-flex items-center gap-2 rounded-lg bg-green-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-green-700"
            >
                <svg class="size-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                Enviar WhatsApp
            </a>
        @elseif($collection->user->email)
            <a
                href="mailto:{{ $collection->user->email }}?subject={{ urlencode('Consulta: ' . $collection->name) }}&body={{ urlencode("Hola! Vi la coleccion '{$collection->name}' y me gustaria mas informacion.") }}"
                class="mt-4 inline-flex items-center gap-2 rounded-lg bg-zinc-800 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-zinc-900 dark:bg-zinc-700 dark:hover:bg-zinc-600"
            >
                <flux:icon name="envelope" class="size-5" />
                Enviar email
            </a>
        @else
            <p class="mt-4 text-sm text-zinc-400">
                Busca a {{ $collection->user->name }} en redes sociales
            </p>
        @endif
    </div>
</div>
