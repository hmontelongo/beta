<div
    class="mx-auto max-w-3xl px-4 py-8"
    x-data="{
        dragging: null,
        dragOver: null,
        handleDragStart(e, index) {
            this.dragging = index;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', index);
        },
        handleDragOver(e, index) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.dragOver = index;
        },
        handleDragLeave(e) {
            this.dragOver = null;
        },
        handleDrop(e, toIndex) {
            e.preventDefault();
            const fromIndex = parseInt(e.dataTransfer.getData('text/plain'));
            if (fromIndex !== toIndex) {
                $wire.reorderPhoto(fromIndex, toIndex);
            }
            this.dragging = null;
            this.dragOver = null;
        },
        handleDragEnd() {
            this.dragging = null;
            this.dragOver = null;
        }
    }"
>
    {{-- Header --}}
    <div class="mb-8">
        <flux:heading size="xl" class="mb-2">Agrega fotos de la propiedad</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400">
            Las fotos ayudan a vender. Puedes agregar hasta 20 fotos.
        </flux:text>
    </div>

    <flux:card class="space-y-6">
        {{-- File Upload Dropzone --}}
        <flux:file-upload wire:model="photos" multiple accept="image/*">
            <flux:file-upload.dropzone
                heading="Arrastra fotos aqui o haz clic para subir"
                text="JPG, PNG, WEBP hasta 10MB cada una"
                with-progress
            />
        </flux:file-upload>

        {{-- Photo Preview Grid --}}
        @if(count($savedPhotoPaths) > 0)
            <div>
                <flux:heading size="sm" class="mb-4">
                    {{ count($savedPhotoPaths) }} {{ count($savedPhotoPaths) === 1 ? 'foto' : 'fotos' }}
                </flux:heading>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                    @foreach($savedPhotoPaths as $index => $path)
                        <div
                            wire:key="photo-{{ $index }}"
                            draggable="true"
                            x-on:dragstart="handleDragStart($event, {{ $index }})"
                            x-on:dragover="handleDragOver($event, {{ $index }})"
                            x-on:dragleave="handleDragLeave($event)"
                            x-on:drop="handleDrop($event, {{ $index }})"
                            x-on:dragend="handleDragEnd()"
                            class="group relative aspect-square overflow-hidden rounded-lg border-2 transition-all cursor-grab active:cursor-grabbing
                                {{ $index === $coverIndex ? 'border-blue-500 ring-2 ring-blue-500/30' : 'border-zinc-200 dark:border-zinc-700' }}"
                            :class="{
                                'opacity-50 scale-95': dragging === {{ $index }},
                                'ring-2 ring-blue-400 border-blue-400': dragOver === {{ $index }} && dragging !== {{ $index }}
                            }"
                        >
                            {{-- Image --}}
                            <img
                                src="{{ $this->getSavedPhotoUrl($path) }}"
                                alt="Foto {{ $index + 1 }}"
                                class="h-full w-full object-cover pointer-events-none"
                            >

                            {{-- Cover Badge --}}
                            @if($index === $coverIndex)
                                <div class="absolute left-2 top-2 rounded bg-black/70 px-2 py-1 text-xs font-medium text-white shadow-lg">
                                    <span class="flex items-center gap-1">
                                        <flux:icon name="star" variant="solid" class="size-3 text-amber-400" />
                                        Portada
                                    </span>
                                </div>
                            @endif

                            {{-- Photo number --}}
                            <div class="absolute bottom-2 left-2 flex size-6 items-center justify-center rounded-full bg-black/60 text-xs font-medium text-white">
                                {{ $index + 1 }}
                            </div>

                            {{-- Hover Actions --}}
                            <div class="absolute inset-0 flex items-center justify-center gap-3 bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                @if($index !== $coverIndex)
                                    <button
                                        type="button"
                                        wire:click="setCover({{ $index }})"
                                        class="flex size-10 items-center justify-center rounded-full bg-white text-zinc-700 shadow-lg transition hover:bg-amber-100 hover:text-amber-600"
                                        title="Establecer como portada"
                                    >
                                        <flux:icon name="star" class="size-5" />
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    wire:click="removePhoto({{ $index }})"
                                    class="flex size-10 items-center justify-center rounded-full bg-white text-zinc-700 shadow-lg transition hover:bg-red-100 hover:text-red-600"
                                    title="Eliminar foto"
                                >
                                    <flux:icon name="trash" class="size-5" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <flux:text size="sm" class="mt-3 text-zinc-500 dark:text-zinc-400">
                    Arrastra las fotos para reordenarlas. Haz clic en la estrella para establecer la foto de portada.
                </flux:text>
            </div>
        @endif

        {{-- Tips --}}
        <flux:callout icon="camera" class="bg-blue-50 dark:bg-blue-950/30">
            <flux:callout.heading>Consejos para buenas fotos</flux:callout.heading>
            <flux:callout.text>
                <ul class="mt-1 list-inside list-disc space-y-1 text-sm">
                    <li>Toma fotos con buena iluminacion natural</li>
                    <li>Incluye fotos de cada habitacion</li>
                    <li>Muestra los espacios desde diferentes angulos</li>
                    <li>Destaca las amenidades especiales</li>
                </ul>
            </flux:callout.text>
        </flux:callout>

        {{-- Form Actions --}}
        <div class="flex items-center justify-between pt-4">
            <flux:button
                type="button"
                wire:click="back"
                variant="ghost"
                icon="arrow-left"
            >
                Volver
            </flux:button>

            <div class="flex gap-3">
                <flux:button
                    type="button"
                    wire:click="skip"
                    variant="ghost"
                >
                    Saltar por ahora
                </flux:button>

                <flux:button
                    type="button"
                    wire:click="continue"
                    variant="primary"
                    icon:trailing="arrow-right"
                    :disabled="count($savedPhotoPaths) === 0"
                >
                    Continuar
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Step Indicator --}}
    <div class="mt-6 flex justify-center gap-2">
        <div class="h-2 w-8 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
        <div class="h-2 w-8 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
        <div class="h-2 w-8 rounded-full bg-zinc-900 dark:bg-white"></div>
        <div class="h-2 w-8 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
    </div>
</div>
