<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
    {{-- Sticky Header with Back Button & Actions --}}
    <div class="sticky top-14 z-40 border-b border-zinc-200/80 bg-white/95 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/95">
        <div class="mx-auto flex h-12 max-w-screen-xl items-center justify-between px-4 sm:px-6">
            <a href="{{ route('agents.properties.index') }}" wire:navigate
               class="flex items-center gap-2 text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                <flux:icon name="arrow-left" class="size-4" />
                <span class="hidden sm:inline">Volver a busqueda</span>
            </a>

            <div class="flex items-center gap-2">
                {{-- Share Button (placeholder) --}}
                <flux:button variant="ghost" size="sm" icon="share">
                    <span class="hidden sm:inline">Compartir</span>
                </flux:button>

                {{-- Add to Collection Button --}}
                <flux:button
                    wire:click="toggleCollection"
                    :variant="$this->isInCollection() ? 'primary' : 'outline'"
                    size="sm"
                    :icon="$this->isInCollection() ? 'check' : 'plus'"
                >
                    {{ $this->isInCollection() ? 'En coleccion' : 'Agregar' }}
                </flux:button>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-screen-xl px-4 py-6 sm:px-6 lg:py-8">
        <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Image Gallery --}}
                <x-image-carousel
                    :images="$this->images"
                    :show-thumbnails="true"
                    :max-thumbnails="8"
                    aspect-ratio="aspect-[16/10]"
                    :link-to-original="true"
                />

                {{-- Mobile Price Card (shown below images on mobile) --}}
                <div class="lg:hidden">
                    @include('livewire.agents.properties.partials.price-card')
                </div>

                {{-- Description --}}
                @if ($this->description)
                    <flux:card class="p-4 sm:p-6">
                        <flux:heading size="lg" class="mb-4">Descripcion</flux:heading>
                        <div x-data="{ expanded: false }" class="relative">
                            <div
                                x-bind:class="{ 'max-h-40 overflow-hidden': !expanded }"
                                class="prose prose-sm dark:prose-invert max-w-none text-zinc-600 dark:text-zinc-400"
                            >
                                {!! $this->description !!}
                            </div>
                            @if (strlen($this->description) > 300)
                                <div
                                    x-show="!expanded"
                                    class="absolute bottom-0 left-0 right-0 h-12 bg-gradient-to-t from-white dark:from-zinc-900 to-transparent pointer-events-none"
                                ></div>
                                <button
                                    x-on:click="expanded = !expanded"
                                    class="mt-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400"
                                >
                                    <span x-text="expanded ? 'Mostrar menos' : 'Mostrar mas'"></span>
                                </button>
                            @endif
                        </div>
                    </flux:card>
                @endif

                {{-- Amenities --}}
                @if (count($this->amenities) > 0)
                    <flux:card class="p-4 sm:p-6">
                        <flux:heading size="lg" class="mb-4">Que ofrece este lugar</flux:heading>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($this->amenities as $amenity)
                                <div class="flex items-center gap-2">
                                    <flux:icon name="check" class="size-4 text-green-500 shrink-0" />
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ str_replace('_', ' ', ucfirst($amenity)) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @endif

                {{-- Location --}}
                <flux:card class="p-4 sm:p-6">
                    <flux:heading size="lg" class="mb-4">Ubicacion</flux:heading>

                    {{-- Map --}}
                    @if ($property->latitude && $property->longitude)
                        <div class="aspect-video rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 mb-4">
                            <iframe
                                width="100%"
                                height="100%"
                                style="border:0"
                                loading="lazy"
                                allowfullscreen
                                referrerpolicy="no-referrer-when-downgrade"
                                src="https://www.google.com/maps/embed/v1/place?key={{ config('services.google.maps_api_key') }}&q={{ $property->latitude }},{{ $property->longitude }}&zoom=15"
                            ></iframe>
                        </div>
                    @endif

                    {{-- Address Info --}}
                    <div class="space-y-2 text-sm">
                        @if ($property->address)
                            <div class="flex items-start gap-2">
                                <flux:icon name="map-pin" class="size-4 text-zinc-400 mt-0.5 shrink-0" />
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $property->address }}</span>
                            </div>
                        @endif
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-zinc-500">
                            @if ($property->colonia)
                                <span>{{ $property->colonia }}</span>
                            @endif
                            @if ($property->city)
                                <span>{{ $property->city }}</span>
                            @endif
                            @if ($property->state)
                                <span>{{ $property->state }}</span>
                            @endif
                            @if ($property->postal_code)
                                <span>CP {{ $property->postal_code }}</span>
                            @endif
                        </div>
                    </div>
                </flux:card>
            </div>

            {{-- Sidebar (Desktop) --}}
            <div class="hidden lg:block space-y-6">
                {{-- Price Card --}}
                @include('livewire.agents.properties.partials.price-card')

                {{-- Contact Card --}}
                @if ($this->publishers->isNotEmpty())
                    <flux:card class="p-4 sm:p-6">
                        <flux:heading size="lg" class="mb-4">Contacto</flux:heading>
                        <div class="space-y-4">
                            @foreach ($this->publishers as $publisher)
                                <div class="{{ !$loop->last ? 'pb-4 border-b border-zinc-100 dark:border-zinc-800' : '' }}">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                                            <flux:icon name="user" class="size-5 text-zinc-400" />
                                        </div>
                                        <div>
                                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $publisher->name }}</p>
                                            <flux:badge size="xs" :color="$publisher->type->color()">
                                                {{ $publisher->type->label() }}
                                            </flux:badge>
                                        </div>
                                    </div>
                                    <div class="space-y-2 ml-12">
                                        @if ($publisher->phone)
                                            <a href="tel:{{ $publisher->phone }}"
                                               class="flex items-center gap-2 text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                                                <flux:icon name="phone" class="size-4" />
                                                {{ $publisher->phone }}
                                            </a>
                                        @endif
                                        @if ($publisher->whatsapp)
                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $publisher->whatsapp) }}"
                                               target="_blank"
                                               class="flex items-center gap-2 text-sm text-green-600 hover:text-green-700">
                                                <flux:icon name="chat-bubble-left" class="size-4" />
                                                WhatsApp
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @endif

                {{-- Source Platforms --}}
                <flux:card class="p-4">
                    <flux:text size="sm" class="text-zinc-500 mb-3">Disponible en</flux:text>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($property->listings as $listing)
                            <a href="{{ $listing->original_url }}"
                               target="_blank"
                               class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700">
                                {{ $listing->platform->name }}
                                <flux:icon name="arrow-top-right-on-square" class="size-3" />
                            </a>
                        @endforeach
                    </div>
                </flux:card>
            </div>
        </div>
    </div>
</div>
