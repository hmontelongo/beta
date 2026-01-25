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
                        <flux:icon.arrow-left class="size-5" />
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
                    {{-- PDF & Copy - Hidden on mobile, shown on sm+ --}}
                    <flux:button wire:click="downloadPdf" variant="ghost" icon="document-arrow-down" size="sm" class="hidden sm:inline-flex">
                        PDF
                    </flux:button>
                    <flux:dropdown position="bottom" align="start" class="hidden sm:block">
                        <flux:button variant="ghost" icon="share" size="sm">
                            Compartir
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item
                                x-on:click="navigator.clipboard.writeText('{{ $collection->getShareUrl() }}').then(() => $wire.onLinkCopied())"
                                icon="link"
                            >
                                Copiar enlace
                            </flux:menu.item>
                            <flux:menu.item wire:click="previewCollection" icon="eye">
                                Vista previa
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                    {{-- WhatsApp - Always visible --}}
                    <flux:button wire:click="shareViaWhatsApp" size="sm" class="!bg-green-600 !text-white hover:!bg-green-700 dark:!bg-green-600 dark:hover:!bg-green-700">
                        <span class="flex items-center gap-1.5">
                            <x-icons.whatsapp class="size-4" />
                            <span class="hidden sm:inline">WhatsApp</span>
                        </span>
                    </flux:button>

                    {{-- Overflow Menu --}}
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" icon="ellipsis-vertical" size="sm" />
                        <flux:menu>
                            {{-- Mobile-only items --}}
                            <flux:menu.item
                                wire:click="downloadPdf"
                                icon="document-arrow-down"
                                class="sm:hidden"
                            >
                                Descargar PDF
                            </flux:menu.item>
                            <flux:menu.item
                                x-on:click="navigator.clipboard.writeText('{{ $collection->getShareUrl() }}').then(() => $wire.onLinkCopied())"
                                icon="link"
                                class="sm:hidden"
                            >
                                Copiar enlace
                            </flux:menu.item>
                            <flux:menu.item
                                wire:click="previewCollection"
                                icon="eye"
                                class="sm:hidden"
                            >
                                Vista previa
                            </flux:menu.item>
                            <flux:menu.separator class="sm:hidden" />
                            {{-- Always visible --}}
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
                                <flux:icon.envelope class="size-4" />
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
            <x-empty-state
                icon="home"
                title="Sin propiedades"
                subtitle="Agrega propiedades desde la busqueda"
            >
                <flux:button wire:click="addProperties" variant="primary" icon="plus">
                    Buscar propiedades
                </flux:button>
            </x-empty-state>
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
                        $heroImage = $property->cover_image;
                        $primaryPrice = $property->primary_price;
                        $price = $primaryPrice['price'] ?? null;
                        $opType = $primaryPrice['type'] ?? $property->operation_type?->value;
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
                            <flux:icon.bars-3 class="size-4" />
                        </div>

                        {{-- Remove Button --}}
                        <button
                            wire:click="removeProperty({{ $property->id }})"
                            class="absolute right-2 top-2 z-10 flex size-8 items-center justify-center rounded-lg bg-white/90 text-zinc-400 shadow-sm backdrop-blur-sm transition-all hover:bg-red-500 hover:text-white dark:bg-zinc-800/90"
                            title="Quitar de coleccion"
                        >
                            <flux:icon.x-mark class="size-4" />
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
                                        <flux:icon.photo class="size-10 text-zinc-300 dark:text-zinc-700" />
                                    </div>
                                @endif

                                {{-- Operation Badge --}}
                                @if($opType)
                                    <div class="absolute bottom-2 left-2">
                                        <flux:badge size="sm" variant="solid" :color="PropertyPresenter::operationTypeBadgeColor($opType)">
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
                <flux:button wire:click="addProperties" variant="ghost" icon="plus">
                    Agregar mas propiedades
                </flux:button>
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
