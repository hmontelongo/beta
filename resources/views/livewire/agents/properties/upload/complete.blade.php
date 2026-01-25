<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4">
    <div class="w-full max-w-md text-center">
        {{-- Success Icon --}}
        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
            <flux:icon.check class="h-10 w-10 text-green-600 dark:text-green-400" />
        </div>

        {{-- Heading --}}
        <flux:heading size="xl" class="mb-2">Tu propiedad esta lista</flux:heading>
        <flux:text class="mb-8 text-zinc-500 dark:text-zinc-400">
            Ya puedes compartirla con tus clientes.
        </flux:text>

        {{-- Property Preview Card --}}
        @if($this->property)
            <flux:card class="mb-8 p-0 overflow-hidden text-left">
                {{-- Cover Image --}}
                @if($this->property->cover_image)
                    <div class="aspect-video w-full overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                        <img
                            src="{{ $this->property->cover_image }}"
                            alt="{{ $this->property->location_display }}"
                            class="h-full w-full object-cover"
                        >
                    </div>
                @else
                    <div class="flex aspect-video w-full items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon.home class="h-12 w-12 text-zinc-400" />
                    </div>
                @endif

                {{-- Property Info --}}
                <div class="p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="sm">
                                {{ $this->property->property_type?->labelEs() ?? 'Propiedad' }}
                                en {{ $this->property->location_display }}
                            </flux:heading>
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                @if($this->property->bedrooms)
                                    <span>{{ $this->property->bedrooms }} rec</span>
                                    <span class="text-zinc-300 dark:text-zinc-600">&middot;</span>
                                @endif
                                @if($this->property->bathrooms)
                                    <span>{{ $this->property->bathrooms }} banos</span>
                                    <span class="text-zinc-300 dark:text-zinc-600">&middot;</span>
                                @endif
                                @if($this->property->built_size_m2)
                                    <span>{{ number_format($this->property->built_size_m2) }} m²</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            @if($this->property->primary_price)
                                <flux:heading size="sm" class="text-green-600 dark:text-green-400">
                                    ${{ number_format($this->property->primary_price['price']) }}
                                </flux:heading>
                                <flux:text size="sm" class="text-zinc-500">
                                    {{ ucfirst($this->property->operation_type?->labelEs() ?? '') }}
                                </flux:text>
                            @endif
                        </div>
                    </div>

                    {{-- Collaboration Badge --}}
                    @if($this->property->is_collaborative)
                        <div class="mt-3">
                            <flux:badge color="blue" size="sm" icon="user-group">
                                Colaborativa &middot; {{ (int) $this->property->commission_split }}% comision
                            </flux:badge>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endif

        {{-- Actions --}}
        <div class="space-y-3">
            <flux:button
                wire:click="shareViaWhatsApp"
                variant="primary"
                icon="share"
                class="w-full"
            >
                Compartir por WhatsApp
            </flux:button>

            <div class="grid grid-cols-2 gap-3">
                <flux:button
                    wire:click="viewProperty"
                    variant="ghost"
                    icon="eye"
                >
                    Ver propiedad
                </flux:button>

                <flux:button
                    wire:click="addAnother"
                    variant="ghost"
                    icon="plus"
                >
                    Agregar otra
                </flux:button>
            </div>

            <flux:button
                wire:click="myProperties"
                variant="subtle"
                class="w-full"
            >
                Ir a mis propiedades
            </flux:button>
        </div>
    </div>
</div>
