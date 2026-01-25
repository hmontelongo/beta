<div class="min-h-screen">
    <div class="mx-auto max-w-screen-xl px-4 py-6 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <flux:heading size="xl">Mis Colecciones</flux:heading>
                <flux:subheading>Administra tus colecciones de propiedades</flux:subheading>
            </div>
            <flux:button :href="route('agents.properties.index')" variant="primary" icon="plus" wire:navigate>
                <span class="hidden sm:inline">Nueva coleccion</span>
                <span class="sm:hidden">Nueva</span>
            </flux:button>
        </div>

        {{-- Filters --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            {{-- Search --}}
            <div class="w-full sm:max-w-xs">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por nombre o cliente..."
                    icon="magnifying-glass"
                />
            </div>

        </div>

        {{-- Collections Grid --}}
        @if($collections->isEmpty())
            <x-empty-state
                icon="folder"
                title="No tienes colecciones"
                subtitle="Crea tu primera coleccion agregando propiedades"
            >
                <flux:button :href="route('agents.properties.index')" variant="primary" icon="plus" wire:navigate>
                    Buscar propiedades
                </flux:button>
            </x-empty-state>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($collections as $collection)
                    @php
                        // Get up to 3 property thumbnail images (works for both native and scraped properties)
                        $thumbnails = $collection->properties->take(3)->map(fn ($property) => $property->cover_image)->filter();
                    @endphp
                    <div
                        wire:key="collection-{{ $collection->id }}"
                        class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition-all hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        {{-- Clickable Card Body --}}
                        <a
                            href="{{ route('agents.collections.show', $collection) }}"
                            wire:navigate
                            class="block p-4"
                        >
                            {{-- Header --}}
                            <div class="mb-3 flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $collection->name }}
                                    </h3>
                                    @if($collection->client_name_display)
                                        <p class="mt-0.5 truncate text-sm text-zinc-500">
                                            <span class="inline-flex items-center gap-1">
                                                <flux:icon.user class="size-3.5" />
                                                {{ $collection->client_name_display }}
                                            </span>
                                        </p>
                                    @endif
                                </div>

                                {{-- Status Badge --}}
                                <flux:badge size="sm" :color="$collection->status_color">
                                    {{ $collection->status_label }}
                                </flux:badge>
                            </div>

                            {{-- Property Thumbnails --}}
                            @if($thumbnails->isNotEmpty())
                                <div class="mb-3 flex -space-x-2">
                                    @foreach($thumbnails as $thumb)
                                        <img
                                            src="{{ $thumb }}"
                                            alt=""
                                            class="size-10 rounded-full border-2 border-white object-cover dark:border-zinc-900"
                                            loading="lazy"
                                        />
                                    @endforeach
                                    @if($collection->properties_count > 3)
                                        <div class="flex size-10 items-center justify-center rounded-full border-2 border-white bg-zinc-100 text-xs font-medium text-zinc-600 dark:border-zinc-900 dark:bg-zinc-800 dark:text-zinc-400">
                                            +{{ $collection->properties_count - 3 }}
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Stats --}}
                            <div class="flex items-center gap-4 text-sm text-zinc-500">
                                <x-stat icon="home" :count="$collection->properties_count" singular="propiedad" plural="propiedades" />
                                <span class="text-xs text-zinc-400">
                                    {{ $collection->updated_at->diffForHumans() }}
                                </span>
                            </div>
                        </a>

                        {{-- Actions (outside the link) --}}
                        <div class="flex items-center gap-1 border-t border-zinc-100 px-3 py-2 dark:border-zinc-800">
                            <flux:tooltip content="Compartir por WhatsApp">
                                <button
                                    wire:click="shareViaWhatsApp({{ $collection->id }})"
                                    class="flex size-8 items-center justify-center rounded-lg text-green-600 transition-colors hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-900/20"
                                >
                                    <x-icons.whatsapp class="size-4" />
                                </button>
                            </flux:tooltip>
                            <flux:tooltip content="Copiar link">
                                <button
                                    x-on:click="navigator.clipboard.writeText('{{ $collection->getShareUrl() }}').then(() => $wire.onLinkCopied({{ $collection->id }}))"
                                    class="flex size-8 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                >
                                    <flux:icon.link class="size-4" />
                                </button>
                            </flux:tooltip>
                            <div class="flex-1"></div>
                            <flux:dropdown position="bottom-end">
                                <flux:button variant="ghost" icon="ellipsis-vertical" size="sm" class="!px-1.5" />
                                <flux:menu>
                                    <flux:menu.item
                                        x-on:click="$flux.modal('delete-collection-{{ $collection->id }}').show()"
                                        variant="danger"
                                        icon="trash"
                                    >
                                        Eliminar
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>

                            {{-- Delete Confirmation Modal --}}
                            <x-confirm-modal
                                name="delete-collection-{{ $collection->id }}"
                                title="¿Eliminar coleccion?"
                                message="Esta accion no se puede deshacer."
                            >
                                <flux:button variant="danger" wire:click="deleteCollection({{ $collection->id }})">
                                    Eliminar
                                </flux:button>
                            </x-confirm-modal>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $collections->links() }}
            </div>
        @endif
    </div>
</div>
