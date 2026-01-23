<div class="min-h-screen">
    <div class="mx-auto max-w-screen-xl px-4 py-6 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">Mis Colecciones</h1>
                <p class="mt-1 text-sm text-zinc-500">Administra tus colecciones de propiedades</p>
            </div>
            <a
                href="{{ route('agents.properties.index') }}"
                wire:navigate
                class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-blue-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-blue-600"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span class="hidden sm:inline">Nueva coleccion</span>
                <span class="sm:hidden">Nueva</span>
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
                    @php
                        // Get up to 3 property thumbnail images
                        $thumbnails = $collection->properties->take(3)->map(function ($property) {
                            $images = collect($property->listings->first()?->raw_data['images'] ?? [])
                                ->map(fn ($img) => is_array($img) ? $img['url'] : $img)
                                ->filter(fn ($url) => !str_contains($url, '.svg') && !str_contains($url, 'placeholder'));
                            return $images->first();
                        })->filter();
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
                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                {{ $collection->client_name_display }}
                                            </span>
                                        </p>
                                    @endif
                                </div>

                                {{-- Status Badge with Tooltip --}}
                                <flux:tooltip content="{{ $collection->status_tooltip }}">
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $collection->status_color }}">
                                        {{ $collection->status_label }}
                                    </span>
                                </flux:tooltip>
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
                                    wire:click="copyShareLink({{ $collection->id }})"
                                    class="flex size-8 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                                >
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                    </svg>
                                </button>
                            </flux:tooltip>
                            <div class="flex-1"></div>
                            <flux:dropdown position="bottom-end">
                                <flux:button variant="ghost" icon="ellipsis-vertical" size="sm" class="!px-1.5" />
                                <flux:menu>
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
</div>
