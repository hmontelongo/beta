<div class="min-h-screen">
    <div class="mx-auto max-w-screen-xl px-4 py-6 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <flux:heading size="xl">Mis Clientes</flux:heading>
                <flux:subheading>Administra tus clientes y sus colecciones</flux:subheading>
            </div>
            <flux:button wire:click="openCreateModal" variant="primary" icon="plus">
                <span class="hidden sm:inline">Nuevo cliente</span>
                <span class="sm:hidden">Nuevo</span>
            </flux:button>
        </div>

        {{-- Search --}}
        <div class="mb-6">
            <div class="w-full sm:max-w-xs">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por nombre, email o telefono..."
                    icon="magnifying-glass"
                />
            </div>
        </div>

        {{-- Clients Grid --}}
        @if($clients->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-700">
                <div class="flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon.users class="size-8 text-zinc-400" />
                </div>
                <flux:heading size="lg" class="mt-4">No tienes clientes</flux:heading>
                <flux:subheading>Agrega tu primer cliente para comenzar</flux:subheading>
                <flux:button wire:click="openCreateModal" variant="primary" icon="plus" class="mt-4">
                    Agregar cliente
                </flux:button>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($clients as $client)
                    <div
                        wire:key="client-{{ $client->id }}"
                        class="group relative overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition-all hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        {{-- Clickable Card Body --}}
                        @php
                            $nameParts = preg_split('/\s+/', trim($client->name));
                            $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                        @endphp
                        <a
                            href="{{ route('agents.clients.show', $client) }}"
                            wire:navigate
                            class="block p-4"
                        >
                            {{-- Header --}}
                            <div class="mb-3 flex items-start gap-3">
                                {{-- Avatar --}}
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ $initials }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $client->name }}
                                    </h3>
                                    @if($client->whatsapp)
                                        <p class="mt-0.5 truncate text-sm text-zinc-500">
                                            <span class="inline-flex items-center gap-1">
                                                <flux:icon.device-phone-mobile class="size-3.5" />
                                                {{ $client->whatsapp }}
                                            </span>
                                        </p>
                                    @elseif($client->email)
                                        <p class="mt-0.5 truncate text-sm text-zinc-500">
                                            <span class="inline-flex items-center gap-1">
                                                <flux:icon.envelope class="size-3.5" />
                                                {{ $client->email }}
                                            </span>
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Stats Row --}}
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500">
                                <span class="flex items-center gap-1">
                                    <flux:icon.folder class="size-4" />
                                    {{ $client->collections_count }} {{ $client->collections_count === 1 ? 'coleccion' : 'colecciones' }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <flux:icon.eye class="size-4" />
                                    {{ $client->total_views }} {{ $client->total_views === 1 ? 'vista' : 'vistas' }}
                                </span>
                            </div>

                            {{-- Last Activity --}}
                            @if($client->last_activity)
                                <p class="mt-2 text-xs text-zinc-400">
                                    Ultimo: {{ $client->last_activity->diffForHumans() }}
                                </p>
                            @endif
                        </a>

                        {{-- Actions (outside the link) --}}
                        <div class="flex items-center gap-1 border-t border-zinc-100 px-3 py-2 dark:border-zinc-800">
                            @if($client->whatsapp)
                                <flux:tooltip content="Abrir WhatsApp">
                                    <button
                                        wire:click="openWhatsApp({{ $client->id }})"
                                        class="flex size-8 items-center justify-center rounded-lg text-green-600 transition-colors hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-900/20"
                                    >
                                        <x-icons.whatsapp class="size-4" />
                                    </button>
                                </flux:tooltip>
                            @endif
                            <div class="flex-1"></div>
                            <flux:dropdown position="bottom-end">
                                <flux:button variant="ghost" icon="ellipsis-vertical" size="sm" class="!px-1.5" />
                                <flux:menu>
                                    <flux:menu.item
                                        :href="route('agents.clients.show', $client)"
                                        icon="eye"
                                        wire:navigate
                                    >
                                        Ver detalles
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        x-on:click="$flux.modal('delete-client-{{ $client->id }}').show()"
                                        variant="danger"
                                        icon="trash"
                                    >
                                        Eliminar
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>

                            {{-- Delete Confirmation Modal --}}
                            <x-confirm-modal
                                name="delete-client-{{ $client->id }}"
                                title="Eliminar cliente?"
                                message="Las colecciones de este cliente no seran eliminadas, pero perderan su vinculacion."
                            >
                                <flux:button variant="danger" wire:click="deleteClient({{ $client->id }})">
                                    Eliminar
                                </flux:button>
                            </x-confirm-modal>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $clients->links() }}
            </div>
        @endif
    </div>

    {{-- Create Client Modal --}}
    <flux:modal wire:model="showCreateModal" class="w-full max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">Nuevo Cliente</flux:heading>

            <form wire:submit="createClient" class="space-y-4">
                <flux:input
                    wire:model="newClientName"
                    label="Nombre"
                    placeholder="Nombre del cliente"
                    required
                />

                <flux:input
                    wire:model="newClientWhatsapp"
                    label="WhatsApp"
                    placeholder="+52 55 1234 5678"
                />

                <flux:input
                    wire:model="newClientEmail"
                    label="Email"
                    type="email"
                    placeholder="cliente@email.com"
                />

                <flux:textarea
                    wire:model="newClientNotes"
                    label="Notas"
                    placeholder="Notas adicionales sobre el cliente..."
                    rows="3"
                />

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Guardar
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
