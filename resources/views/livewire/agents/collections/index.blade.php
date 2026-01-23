<div class="min-h-screen">
    <div class="mx-auto max-w-screen-xl px-4 py-6 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Mis Colecciones</h1>
                <p class="mt-1 text-sm text-zinc-500">Administra tus colecciones de propiedades</p>
            </div>
            <a
                href="{{ route('agents.properties.index') }}"
                wire:navigate
                class="inline-flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-blue-600"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Nueva coleccion
            </a>
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

            {{-- Filter Pills --}}
            <div class="flex gap-2">
                <button
                    wire:click="$set('filter', 'all')"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition-all {{ $filter === 'all' ? 'bg-blue-500 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                >
                    Todas
                </button>
                <button
                    wire:click="$set('filter', 'public')"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition-all {{ $filter === 'public' ? 'bg-blue-500 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                >
                    Publicas
                </button>
                <button
                    wire:click="$set('filter', 'private')"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition-all {{ $filter === 'private' ? 'bg-blue-500 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}"
                >
                    Privadas
                </button>
            </div>
        </div>

        {{-- Collections Grid --}}
        @if($collections->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700">
                <div class="flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <svg class="size-8 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">No tienes colecciones</h3>
                <p class="mt-1 text-sm text-zinc-500">Crea tu primera coleccion agregando propiedades</p>
                <a
                    href="{{ route('agents.properties.index') }}"
                    wire:navigate
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600"
                >
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Buscar propiedades
                </a>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($collections as $collection)
                    <div
                        wire:key="collection-{{ $collection->id }}"
                        class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-all hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        {{-- Header --}}
                        <div class="mb-3 flex items-start justify-between">
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $collection->name }}
                                </h3>
                                @if($collection->client_name)
                                    <p class="mt-0.5 truncate text-sm text-zinc-500">
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                            </svg>
                                            {{ $collection->client_name }}
                                        </span>
                                    </p>
                                @endif
                            </div>

                            {{-- Status Badge --}}
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $collection->is_public ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' }}">
                                {{ $collection->is_public ? 'Publica' : 'Privada' }}
                            </span>
                        </div>

                        {{-- Stats --}}
                        <div class="mb-4 flex items-center gap-4 text-sm text-zinc-500">
                            <span class="flex items-center gap-1">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                                {{ $collection->properties_count }} {{ $collection->properties_count === 1 ? 'propiedad' : 'propiedades' }}
                            </span>
                            <span class="text-xs text-zinc-400">
                                {{ $collection->updated_at->diffForHumans() }}
                            </span>
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-2">
                            <button
                                wire:click="editCollection({{ $collection->id }})"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-700 transition-all hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                                Editar
                            </button>
                            <button
                                wire:click="shareViaWhatsApp({{ $collection->id }})"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white transition-all hover:bg-green-700"
                            >
                                <svg class="size-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                Compartir
                            </button>
                            <flux:dropdown>
                                <flux:button variant="ghost" icon="ellipsis-vertical" class="!px-2" />

                                <flux:menu>
                                    <flux:menu.item wire:click="copyShareLink({{ $collection->id }})" icon="link">
                                        Copiar link
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        wire:click="deleteCollection({{ $collection->id }})"
                                        wire:confirm="¿Eliminar esta coleccion? Esta accion no se puede deshacer."
                                        variant="danger"
                                        icon="trash"
                                    >
                                        Eliminar
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
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

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">Editar coleccion</flux:heading>

            <flux:input
                wire:model="editName"
                label="Nombre de la coleccion"
                placeholder="Ej: Casa para familia Garcia"
            />

            <div class="space-y-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/50">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    Cliente (opcional)
                </p>
                <flux:input
                    wire:model="editClientName"
                    placeholder="Nombre del cliente"
                />
                <flux:input
                    wire:model="editClientWhatsapp"
                    placeholder="WhatsApp: +52 33 1234 5678"
                    type="tel"
                />
            </div>

            <div class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/50">
                <div>
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Coleccion publica</p>
                    <p class="text-xs text-zinc-500">Compartible via link</p>
                </div>
                <flux:switch wire:model.live="editIsPublic" />
            </div>

            <div class="flex gap-2 pt-2">
                <flux:button wire:click="$set('showEditModal', false)" variant="ghost" class="flex-1">
                    Cancelar
                </flux:button>
                <flux:button wire:click="updateCollection" variant="primary" class="flex-1">
                    Guardar cambios
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
