<div class="min-h-screen">
    <div class="mx-auto max-w-screen-xl px-4 py-6 sm:px-6 lg:px-8">
        {{-- Back Link --}}
        <div class="mb-6">
            <flux:link href="{{ route('agents.clients.index') }}" wire:navigate icon="arrow-left" class="text-sm">
                Clientes
            </flux:link>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left Column: Client Info --}}
            <div class="space-y-6 lg:col-span-1">
                {{-- Contact Card --}}
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="p-5">
                        @if($editing)
                            {{-- Edit Form --}}
                            <form wire:submit="saveClient" class="space-y-4">
                                <flux:input
                                    wire:model="editName"
                                    label="Nombre"
                                    placeholder="Nombre del cliente"
                                    required
                                />

                                <flux:input
                                    wire:model="editWhatsapp"
                                    label="WhatsApp"
                                    placeholder="+52 55 1234 5678"
                                />

                                <flux:input
                                    wire:model="editEmail"
                                    label="Email"
                                    type="email"
                                    placeholder="cliente@email.com"
                                />

                                <flux:textarea
                                    wire:model="editNotes"
                                    label="Notas"
                                    placeholder="Notas adicionales..."
                                    rows="4"
                                />

                                <div class="flex justify-end gap-2 pt-2">
                                    <flux:button variant="ghost" wire:click="cancelEditing" type="button">
                                        Cancelar
                                    </flux:button>
                                    <flux:button type="submit" variant="primary">
                                        Guardar
                                    </flux:button>
                                </div>
                            </form>
                        @else
                            {{-- View Mode --}}
                            @php
                                $nameParts = preg_split('/\s+/', trim($client->name));
                                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                            @endphp
                            <div class="flex items-start gap-4">
                                {{-- Avatar --}}
                                <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xl font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ $initials }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ $client->name }}
                                    </h1>

                                    @if($client->whatsapp)
                                        <p class="mt-1 flex items-center gap-1.5 text-sm text-zinc-500">
                                            <flux:icon.device-phone-mobile class="size-4" />
                                            {{ $client->whatsapp }}
                                        </p>
                                    @endif

                                    @if($client->email)
                                        <p class="mt-1 flex items-center gap-1.5 text-sm text-zinc-500">
                                            <flux:icon.envelope class="size-4" />
                                            {{ $client->email }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if($client->whatsapp)
                                    <flux:button wire:click="openWhatsApp" size="sm" class="!bg-green-600 !text-white hover:!bg-green-700">
                                        <x-icons.whatsapp class="size-4" />
                                        WhatsApp
                                    </flux:button>
                                @endif
                                <flux:button wire:click="startEditing" variant="ghost" size="sm" icon="pencil">
                                    Editar
                                </flux:button>
                            </div>

                            {{-- Notes --}}
                            @if($client->notes)
                                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                    <h3 class="text-xs font-medium uppercase tracking-wide text-zinc-400">Notas</h3>
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-zinc-600 dark:text-zinc-400">{{ $client->notes }}</p>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Activity Stats Card --}}
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Actividad</h2>
                    </div>
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <div class="flex items-center justify-between px-5 py-3">
                            <span class="text-sm text-zinc-500">Colecciones</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $collections->count() }}</span>
                        </div>
                        <div class="flex items-center justify-between px-5 py-3">
                            <span class="text-sm text-zinc-500">Vistas totales</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $client->total_views }}</span>
                        </div>
                        <div class="flex items-center justify-between px-5 py-3">
                            <span class="text-sm text-zinc-500">Creado</span>
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $client->created_at->format('d M Y') }}</span>
                        </div>
                        @if($client->last_activity)
                            <div class="flex items-center justify-between px-5 py-3">
                                <span class="text-sm text-zinc-500">Ultima actividad</span>
                                <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $client->last_activity->diffForHumans() }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Danger Zone --}}
                <div class="overflow-hidden rounded-xl border border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-900/10">
                    <div class="px-5 py-4">
                        <h3 class="font-medium text-red-800 dark:text-red-400">Zona de peligro</h3>
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400/80">
                            Eliminar este cliente no eliminara sus colecciones.
                        </p>
                        <div class="mt-3">
                            <flux:button
                                x-on:click="$flux.modal('delete-client').show()"
                                variant="danger"
                                size="sm"
                                icon="trash"
                            >
                                Eliminar cliente
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Collections --}}
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Colecciones enviadas</h2>
                    </div>

                    @if($collections->isEmpty())
                        <div class="flex flex-col items-center justify-center py-12">
                            <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon.folder class="size-6 text-zinc-400" />
                            </div>
                            <p class="mt-3 text-sm text-zinc-500">Aun no hay colecciones para este cliente</p>
                            <flux:link
                                href="{{ route('agents.properties.index') }}"
                                wire:navigate
                                class="mt-3 text-sm"
                            >
                                Buscar propiedades
                            </flux:link>
                        </div>
                    @else
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($collections as $collection)
                                <a
                                    wire:key="collection-{{ $collection->id }}"
                                    href="{{ route('agents.collections.show', $collection) }}"
                                    wire:navigate
                                    class="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                >
                                    {{-- Collection Icon --}}
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                        <flux:icon.folder class="size-5 text-zinc-500" />
                                    </div>

                                    {{-- Collection Info --}}
                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $collection->name }}
                                        </h3>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500">
                                            <span>{{ $collection->properties_count }} {{ $collection->properties_count === 1 ? 'propiedad' : 'propiedades' }}</span>
                                            <span class="flex items-center gap-1">
                                                <flux:icon.eye class="size-3.5" />
                                                {{ $collection->view_count ?? 0 }} vistas
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Date & Status --}}
                                    <div class="hidden shrink-0 text-right sm:block">
                                        <flux:badge size="sm" :color="$collection->status_color">
                                            {{ $collection->status_label }}
                                        </flux:badge>
                                        <p class="mt-1 text-xs text-zinc-400">
                                            {{ $collection->updated_at->diffForHumans() }}
                                        </p>
                                    </div>

                                    {{-- Chevron --}}
                                    <flux:icon.chevron-right class="size-5 shrink-0 text-zinc-400" />
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <x-confirm-modal
        name="delete-client"
        title="Eliminar cliente?"
        message="Las colecciones de este cliente no seran eliminadas, pero perderan su vinculacion."
    >
        <flux:button variant="danger" wire:click="deleteClient">
            Eliminar
        </flux:button>
    </x-confirm-modal>
</div>
