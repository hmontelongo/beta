@use('App\Services\PropertyPresenter')

<div class="min-h-screen">
    {{-- Sticky Header with Share Actions --}}
    <div class="sticky top-0 z-40 border-b border-zinc-200 bg-white/95 backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/95">
        <div class="mx-auto max-w-screen-xl px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between gap-4">
                {{-- Left: Back + Name --}}
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <a
                        href="{{ route('agents.collections.index') }}"
                        wire:navigate
                        class="shrink-0 rounded-lg p-1.5 text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                    >
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                    </a>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                wire:model.blur="name"
                                class="w-full min-w-0 border-0 bg-transparent p-0 text-lg font-bold text-zinc-900 placeholder:text-zinc-400 focus:ring-0 dark:text-zinc-100"
                                placeholder="Nombre de la coleccion"
                            />
                            <flux:badge size="sm" :color="$collection->status_color">
                                {{ $collection->status_label }}
                            </flux:badge>
                        </div>
                        @error('name')
                            <p class="text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Right: Share Actions --}}
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button wire:click="downloadPdf" variant="ghost" icon="document-arrow-down" size="sm">
                        PDF
                    </flux:button>
                    <flux:button wire:click="copyShareLink" variant="ghost" icon="link" size="sm">
                        Copiar
                    </flux:button>
                    <flux:button wire:click="shareViaWhatsApp" size="sm" class="!bg-green-600 !text-white hover:!bg-green-700 dark:!bg-green-600 dark:hover:!bg-green-700">
                        <span class="flex items-center gap-1.5">
                            <x-icons.whatsapp class="size-4" />
                            WhatsApp
                        </span>
                    </flux:button>

                    {{-- Overflow Menu --}}
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" icon="ellipsis-vertical" size="sm" />
                        <flux:menu>
                            <flux:menu.item
                                x-on:click="$flux.modal('delete-collection').show()"
                                icon="trash"
                                variant="danger"
                            >
                                Eliminar coleccion
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        </div>
    </div>

    {{-- Settings Bar (always visible) --}}
    <div class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/50">
        <div class="mx-auto max-w-screen-xl px-4 py-2.5 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                {{-- Client Selection --}}
                <div class="flex items-center gap-2">
                    <span class="text-sm text-zinc-500">Cliente:</span>
                    <flux:select
                        variant="listbox"
                        wire:model.live="clientId"
                        wire:key="client-select-{{ $clientId }}"
                        placeholder="Sin cliente"
                        size="sm"
                        class="w-44"
                    >
                        <flux:select.option :value="null">Sin cliente</flux:select.option>
                        @foreach($this->clients as $client)
                            <flux:select.option :value="$client->id">
                                {{ $client->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button wire:click="openNewClientModal" variant="ghost" size="sm" icon="plus" />
                </div>

                {{-- Client Contact (if available) --}}
                @if($collection->client && ($collection->client->whatsapp || $collection->client->email))
                    <div class="flex items-center gap-3 text-sm">
                        @if($collection->client->whatsapp)
                            <a
                                href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $collection->client->whatsapp) }}"
                                target="_blank"
                                class="flex items-center gap-1 text-green-600 hover:text-green-700"
                            >
                                <x-icons.whatsapp class="size-4" />
                                {{ $collection->client->whatsapp }}
                            </a>
                        @endif
                        @if($collection->client->email)
                            <a
                                href="mailto:{{ $collection->client->email }}"
                                class="flex items-center gap-1 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                                {{ $collection->client->email }}
                            </a>
                        @endif
                    </div>
                @endif

                {{-- Spacer --}}
                <div class="flex-1"></div>

                {{-- View Stats (only when shared) --}}
                @if($collection->shared_at)
                    <div class="flex items-center gap-1.5 text-sm text-zinc-500">
                        <flux:icon name="eye" class="size-4" />
                        <span>{{ $collection->view_count }}</span>
                        @if($collection->last_viewed_at)
                            <span class="text-zinc-400">·</span>
                            <span>{{ $collection->last_viewed_at->diffForHumans() }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-screen-xl px-4 py-6 sm:px-6 lg:px-8">
        {{-- Properties Section --}}
        @if($collection->properties->isEmpty())
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700">
                <div class="flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <svg class="size-8 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Sin propiedades</h3>
                <p class="mt-1 text-sm text-zinc-500">Agrega propiedades desde la busqueda</p>
                <button
                    wire:click="addProperties"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Buscar propiedades
                </button>
            </div>
        @else
            {{-- Property Count --}}
            <p class="mb-4 text-sm text-zinc-500">
                {{ $collection->properties->count() }} {{ $collection->properties->count() === 1 ? 'propiedad' : 'propiedades' }}
                <span class="hidden sm:inline">
                    <span class="mx-1">&middot;</span>
                    Arrastra para reordenar
                </span>
            </p>

            {{-- Property Grid (Sortable) --}}
            <div
                wire:sort="reorderProperty"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                @foreach($collection->properties as $property)
                    @php
                        $listing = $property->listings->first();
                        $images = $listing?->raw_data['images'] ?? [];
                        $heroImage = $images[0] ?? null;
                        $operations = $listing?->operations ?? [];
                        $price = $operations[0]['price'] ?? null;
                        $opType = $operations[0]['type'] ?? null;
                    @endphp
                    <div
                        wire:key="property-{{ $property->id }}"
                        wire:sort:item="{{ $property->id }}"
                        class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition-all hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        {{-- Drag Handle - Always visible --}}
                        <div
                            wire:sort:handle
                            class="absolute left-2 top-2 z-10 flex size-8 cursor-grab items-center justify-center rounded-lg bg-white/90 text-zinc-400 shadow-sm backdrop-blur-sm transition-colors hover:text-zinc-600 dark:bg-zinc-800/90"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </div>

                        {{-- Remove Button --}}
                        <button
                            wire:click="removeProperty({{ $property->id }})"
                            class="absolute right-2 top-2 z-10 flex size-8 items-center justify-center rounded-lg bg-white/90 text-zinc-400 shadow-sm backdrop-blur-sm transition-all hover:bg-red-500 hover:text-white dark:bg-zinc-800/90"
                            title="Quitar de coleccion"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <a href="{{ route('agents.properties.show', $property) }}" wire:navigate class="block">
                            {{-- Image --}}
                            <div class="relative aspect-[4/3] overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                                @if($heroImage)
                                    <img
                                        src="{{ $heroImage }}"
                                        alt="{{ $property->colonia }}"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                @else
                                    <div class="flex h-full w-full items-center justify-center">
                                        <svg class="size-10 text-zinc-300 dark:text-zinc-700" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                        </svg>
                                    </div>
                                @endif

                                {{-- Operation Badge --}}
                                @if($opType)
                                    <div class="absolute bottom-2 left-2">
                                        <flux:badge size="sm" :color="PropertyPresenter::operationTypeBadgeColor($opType)">
                                            {{ PropertyPresenter::operationTypeLabel($opType) }}
                                        </flux:badge>
                                    </div>
                                @endif
                            </div>

                            {{-- Content --}}
                            <div class="p-3">
                                {{-- Price --}}
                                <div class="mb-1">
                                    @if($price)
                                        <p class="text-lg font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                                            {{ PropertyPresenter::formatPrice(['type' => $opType, 'price' => $price]) }}
                                        </p>
                                    @else
                                        <p class="text-lg font-bold text-zinc-400 dark:text-zinc-600">
                                            Consultar precio
                                        </p>
                                    @endif
                                </div>

                                {{-- Location --}}
                                <p class="mb-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $property->colonia }}{{ $property->city ? ', ' . $property->city : '' }}
                                </p>

                                {{-- Stats --}}
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                                    @if($property->bedrooms)
                                        <span class="flex items-center gap-1">
                                            {!! PropertyPresenter::bedroomIcon('size-3.5') !!}
                                            {{ PropertyPresenter::formatBedrooms($property->bedrooms, abbrev: true) }}
                                        </span>
                                    @endif
                                    @if($property->bathrooms)
                                        <span class="flex items-center gap-1">
                                            {!! PropertyPresenter::bathroomIcon('size-3.5') !!}
                                            {{ PropertyPresenter::formatBathrooms($property->bathrooms, abbrev: true) }}
                                        </span>
                                    @endif
                                    @if($property->built_size_m2)
                                        <span class="flex items-center gap-1">
                                            {!! PropertyPresenter::sizeIcon('size-3.5') !!}
                                            {{ PropertyPresenter::formatBuiltSize($property->built_size_m2) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Add More Properties Link (only when collection has properties) --}}
        @if($collection->properties->isNotEmpty())
            <div class="mt-6">
                <button
                    wire:click="addProperties"
                    class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Agregar mas propiedades
                </button>
            </div>
        @endif
    </div>

    {{-- Delete Collection Confirmation Modal --}}
    <x-confirm-modal
        name="delete-collection"
        title="¿Eliminar coleccion?"
        message="Esta accion no se puede deshacer."
    >
        <flux:button variant="danger" wire:click="deleteCollection">
            Eliminar
        </flux:button>
    </x-confirm-modal>

    {{-- New Client Modal --}}
    <flux:modal wire:model="showNewClientModal" class="w-full max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">Nuevo cliente</flux:heading>

            <flux:input
                wire:model="newClientName"
                label="Nombre *"
                placeholder="Ej: Juan Martinez"
            />
            @error('newClientName')
                <p class="-mt-2 text-xs text-red-500">{{ $message }}</p>
            @enderror

            <flux:input
                wire:model="newClientWhatsapp"
                label="WhatsApp"
                placeholder="+52 33 1234 5678"
                type="tel"
            />

            <flux:input
                wire:model="newClientEmail"
                label="Email"
                placeholder="juan@email.com"
                type="email"
            />

            <flux:textarea
                wire:model="newClientNotes"
                label="Notas (solo tu las ves)"
                placeholder="Busca para su familia, presupuesto flexible..."
                rows="2"
            />

            <div class="flex gap-2 pt-2">
                <flux:button wire:click="$set('showNewClientModal', false)" variant="ghost" class="flex-1">
                    Cancelar
                </flux:button>
                <flux:button wire:click="createClient" variant="primary" class="flex-1">
                    Crear cliente
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
