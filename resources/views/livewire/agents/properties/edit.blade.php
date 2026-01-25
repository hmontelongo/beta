<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center gap-3">
            <flux:button :href="route('agents.properties.show', $property)" variant="ghost" icon="arrow-left" wire:navigate />
            <div>
                <flux:heading size="xl">Editar propiedad</flux:heading>
                <flux:subheading>{{ $property->colonia }}, {{ $property->city }}</flux:subheading>
            </div>
        </div>
    </div>

    {{-- Warning if property is in other agents' collections --}}
    @if ($this->collectionsUsingCount > 0)
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
            <flux:callout.heading>Esta propiedad está en {{ $this->collectionsUsingCount }} {{ $this->collectionsUsingCount === 1 ? 'colección' : 'colecciones' }} de otros agentes</flux:callout.heading>
            <flux:callout.text>Los cambios que realices se reflejarán en todas las colecciones donde esta propiedad aparece.</flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-4">
        {{-- Photos Section --}}
        <x-upload.data-section title="Fotos" icon="photo">
            <div class="p-4 space-y-4">
                {{-- Upload Dropzone --}}
                <flux:file-upload wire:model="photos" multiple accept="image/*">
                    <flux:file-upload.dropzone
                        heading="Arrastra fotos aquí o haz clic para subir"
                        text="JPG, PNG, WEBP hasta 10MB cada una"
                        with-progress
                    />
                </flux:file-upload>

                {{-- Existing Photos Grid --}}
                @if($this->existingImages->count() > 0)
                    <div>
                        <p class="mb-3 text-sm text-zinc-500">
                            {{ $this->existingImages->count() }} {{ $this->existingImages->count() === 1 ? 'foto' : 'fotos' }}
                            <span class="hidden sm:inline"> - Arrastra para reordenar</span>
                        </p>

                        <div
                            wire:sortable="reorderPhoto"
                            class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                        >
                            @foreach($this->existingImages as $image)
                                <div
                                    wire:key="image-{{ $image->id }}"
                                    wire:sortable.item="{{ $image->id }}"
                                    class="group relative aspect-square overflow-hidden rounded-lg border-2 transition-all cursor-grab active:cursor-grabbing {{ $image->is_cover ? 'border-blue-500 ring-2 ring-blue-500/30' : 'border-zinc-200 dark:border-zinc-700' }}"
                                >
                                    {{-- Image --}}
                                    <img
                                        src="{{ $image->url }}"
                                        alt="Foto"
                                        class="h-full w-full object-cover pointer-events-none"
                                    />

                                    {{-- Drag Handle --}}
                                    <div
                                        wire:sortable.handle
                                        class="absolute left-2 top-2 flex size-6 cursor-grab items-center justify-center rounded bg-black/60 text-white"
                                    >
                                        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                        </svg>
                                    </div>

                                    {{-- Cover Badge --}}
                                    @if($image->is_cover)
                                        <div class="absolute right-2 top-2 rounded bg-black/70 px-2 py-1 text-xs font-medium text-white shadow-lg">
                                            <span class="flex items-center gap-1">
                                                <flux:icon name="star" variant="solid" class="size-3 text-amber-400" />
                                                Portada
                                            </span>
                                        </div>
                                    @endif

                                    {{-- Hover Actions --}}
                                    <div class="absolute inset-0 flex items-center justify-center gap-3 bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                        @if(!$image->is_cover)
                                            <button
                                                type="button"
                                                wire:click="setCover({{ $image->id }})"
                                                class="flex size-10 items-center justify-center rounded-full bg-white text-zinc-700 shadow-lg transition hover:bg-amber-100 hover:text-amber-600"
                                                title="Establecer como portada"
                                            >
                                                <flux:icon name="star" class="size-5" />
                                            </button>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="removePhoto({{ $image->id }})"
                                            wire:confirm="¿Eliminar esta foto?"
                                            class="flex size-10 items-center justify-center rounded-full bg-white text-zinc-700 shadow-lg transition hover:bg-red-100 hover:text-red-600"
                                            title="Eliminar foto"
                                        >
                                            <flux:icon name="trash" class="size-5" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-upload.data-section>

        {{-- Property Section --}}
        <x-upload.data-section title="Propiedad" icon="home">
            <x-upload.data-row label="tipo" required>
                <select
                    wire:change="updateValue('property.property_type', $event.target.value)"
                    class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                >
                    <option value="">—</option>
                    @foreach ($propertyTypes as $type)
                        <option value="{{ $type->value }}" @selected(($data['property']['property_type'] ?? '') === $type->value)>
                            {{ $type->labelEs() }}
                        </option>
                    @endforeach
                </select>
            </x-upload.data-row>

            <x-upload.data-row label="operación" required>
                <select
                    wire:change="updateValue('property.operation_type', $event.target.value)"
                    class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                >
                    <option value="">—</option>
                    @foreach ($operationTypes as $type)
                        <option value="{{ $type->value }}" @selected(($data['property']['operation_type'] ?? '') === $type->value)>
                            {{ $type->labelEs() }}
                        </option>
                    @endforeach
                </select>
            </x-upload.data-row>

            <x-upload.data-row label="colonia" required>
                <x-upload.inline-input
                    :value="$data['property']['colonia'] ?? ''"
                    placeholder="Ej: Providencia"
                    wire:blur="updateValue('property.colonia', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="ciudad">
                <x-upload.inline-input
                    :value="$data['property']['city'] ?? 'Guadalajara'"
                    wire:blur="updateValue('property.city', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="estado">
                <x-upload.inline-input
                    :value="$data['property']['state'] ?? 'Jalisco'"
                    wire:blur="updateValue('property.state', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="dirección" note="privada">
                <x-upload.inline-input
                    :value="$data['property']['address'] ?? ''"
                    placeholder="Calle y número"
                    wire:blur="updateValue('property.address', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="interior">
                <x-upload.inline-input
                    :value="$data['property']['interior_number'] ?? ''"
                    placeholder="Depto / Interior"
                    wire:blur="updateValue('property.interior_number', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="código_postal">
                <x-upload.inline-input
                    :value="$data['property']['postal_code'] ?? ''"
                    placeholder="Ej: 44630"
                    wire:blur="updateValue('property.postal_code', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="recámaras">
                <x-upload.inline-input
                    type="number"
                    :value="$data['property']['bedrooms'] ?? ''"
                    wire:blur="updateValue('property.bedrooms', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="baños">
                <x-upload.inline-input
                    type="number"
                    :value="$data['property']['bathrooms'] ?? ''"
                    wire:blur="updateValue('property.bathrooms', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="medio_baño">
                <x-upload.inline-input
                    type="number"
                    :value="$data['property']['half_bathrooms'] ?? ''"
                    wire:blur="updateValue('property.half_bathrooms', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="construcción_m2">
                <x-upload.inline-input
                    type="number"
                    :value="$data['property']['built_size_m2'] ?? ''"
                    wire:blur="updateValue('property.built_size_m2', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="terreno_m2">
                <x-upload.inline-input
                    type="number"
                    :value="$data['property']['lot_size_m2'] ?? ''"
                    wire:blur="updateValue('property.lot_size_m2', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="estacionamientos">
                <x-upload.inline-input
                    type="number"
                    :value="$data['property']['parking_spots'] ?? ''"
                    wire:blur="updateValue('property.parking_spots', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="antigüedad_años">
                <x-upload.inline-input
                    type="number"
                    :value="$data['property']['age_years'] ?? ''"
                    wire:blur="updateValue('property.age_years', $event.target.value)"
                />
            </x-upload.data-row>
        </x-upload.data-section>

        {{-- Pricing Section --}}
        <x-upload.data-section title="Precio" icon="currency-dollar">
            <x-upload.data-row label="precio" required>
                <x-upload.inline-input
                    type="number"
                    :value="$data['pricing']['price'] ?? ''"
                    wire:blur="updateValue('pricing.price', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="moneda">
                <select
                    wire:change="updateValue('pricing.price_currency', $event.target.value)"
                    class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                >
                    <option value="MXN" @selected(($data['pricing']['price_currency'] ?? 'MXN') === 'MXN')>MXN</option>
                    <option value="USD" @selected(($data['pricing']['price_currency'] ?? '') === 'USD')>USD</option>
                </select>
            </x-upload.data-row>

            <x-upload.data-row label="mantenimiento">
                <x-upload.inline-input
                    type="number"
                    :value="$data['pricing']['maintenance_fee'] ?? ''"
                    placeholder="mensual"
                    wire:blur="updateValue('pricing.maintenance_fee', $event.target.value)"
                />
            </x-upload.data-row>

            <x-upload.data-row label="servicios_incluidos">
                <x-upload.tag-list
                    :items="$data['pricing']['included_services'] ?? []"
                    path="pricing.included_services"
                    placeholder="+ agregar"
                />
            </x-upload.data-row>
        </x-upload.data-section>

        {{-- Terms Section (for rentals) --}}
        @if (($data['property']['operation_type'] ?? '') === 'rent')
            <x-upload.data-section title="Requisitos de renta" icon="document-text">
                <x-upload.data-row label="depósito_meses">
                    <x-upload.inline-input
                        type="number"
                        :value="$data['terms']['deposit_months'] ?? ''"
                        wire:blur="updateValue('terms.deposit_months', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="adelanto_meses">
                    <x-upload.inline-input
                        type="number"
                        :value="$data['terms']['advance_months'] ?? ''"
                        wire:blur="updateValue('terms.advance_months', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="aval_requerido">
                    <select
                        wire:change="updateValue('terms.guarantor_required', $event.target.value === 'true' ? true : ($event.target.value === 'false' ? false : null))"
                        class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                    >
                        <option value="">—</option>
                        <option value="true" @selected(($data['terms']['guarantor_required'] ?? null) === true)>Sí</option>
                        <option value="false" @selected(($data['terms']['guarantor_required'] ?? null) === false)>No</option>
                    </select>
                </x-upload.data-row>

                <x-upload.data-row label="mascotas">
                    <select
                        wire:change="updateValue('terms.pets_allowed', $event.target.value === 'true' ? true : ($event.target.value === 'false' ? false : null))"
                        class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                    >
                        <option value="">—</option>
                        <option value="true" @selected(($data['terms']['pets_allowed'] ?? null) === true)>Permitidas</option>
                        <option value="false" @selected(($data['terms']['pets_allowed'] ?? null) === false)>No permitidas</option>
                    </select>
                </x-upload.data-row>

                <x-upload.data-row label="máx_ocupantes">
                    <x-upload.inline-input
                        type="number"
                        :value="$data['terms']['max_occupants'] ?? ''"
                        wire:blur="updateValue('terms.max_occupants', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="restricciones">
                    <x-upload.tag-list
                        :items="$data['terms']['restrictions'] ?? []"
                        path="terms.restrictions"
                        placeholder="+ agregar"
                    />
                </x-upload.data-row>
            </x-upload.data-section>
        @endif

        {{-- Amenities Section --}}
        <x-upload.data-section title="Amenidades" icon="sparkles">
            <x-upload.data-row label="unidad">
                <x-upload.tag-list
                    :items="$data['amenities']['unit'] ?? []"
                    path="amenities.unit"
                    placeholder="+ agregar"
                />
            </x-upload.data-row>

            <x-upload.data-row label="edificio">
                <x-upload.tag-list
                    :items="$data['amenities']['building'] ?? []"
                    path="amenities.building"
                    placeholder="+ agregar"
                />
            </x-upload.data-row>

            <x-upload.data-row label="servicios">
                <x-upload.tag-list
                    :items="$data['amenities']['services'] ?? []"
                    path="amenities.services"
                    placeholder="+ agregar"
                />
            </x-upload.data-row>
        </x-upload.data-section>

        {{-- Location Section --}}
        <x-upload.data-section title="Ubicación" icon="map-pin">
            <x-upload.data-row label="nombre_edificio">
                <x-upload.inline-input
                    :value="$data['location']['building_name'] ?? ''"
                    placeholder="Si aplica"
                    wire:blur="updateValue('location.building_name', $event.target.value)"
                />
            </x-upload.data-row>
        </x-upload.data-section>

        {{-- Description Section --}}
        <x-upload.data-section title="Descripción" icon="document-text">
            <div class="px-4 py-3">
                <textarea
                    wire:blur="updateValue('description', $event.target.value)"
                    rows="4"
                    class="w-full resize-none rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-900 focus:border-zinc-300 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                    placeholder="Descripción de la propiedad..."
                >{{ $data['description'] ?? '' }}</textarea>
            </div>
        </x-upload.data-section>

        {{-- Collaboration Section --}}
        <x-upload.data-section title="Colaboración" icon="user-group">
            <x-upload.data-row label="colaborativa">
                <select
                    wire:change="updateValue('collaboration.is_collaborative', $event.target.value === 'true')"
                    class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                >
                    <option value="false" @selected(!($data['collaboration']['is_collaborative'] ?? false))>No</option>
                    <option value="true" @selected($data['collaboration']['is_collaborative'] ?? false)>Sí - Abierta a colaboración</option>
                </select>
            </x-upload.data-row>

            @if ($data['collaboration']['is_collaborative'] ?? false)
                <x-upload.data-row label="comisión_compartida">
                    <select
                        wire:change="updateValue('collaboration.commission_split', $event.target.value)"
                        class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                    >
                        <option value="">Sin especificar</option>
                        <option value="30" @selected(($data['collaboration']['commission_split'] ?? '') == '30')>30%</option>
                        <option value="40" @selected(($data['collaboration']['commission_split'] ?? '') == '40')>40%</option>
                        <option value="50" @selected(($data['collaboration']['commission_split'] ?? '') == '50')>50%</option>
                    </select>
                </x-upload.data-row>
            @endif
        </x-upload.data-section>

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-900/20">
                <div class="flex items-start gap-3">
                    <flux:icon name="exclamation-circle" class="size-5 text-red-500 mt-0.5" />
                    <div>
                        <p class="font-medium text-red-800 dark:text-red-200">Corrige los siguientes errores:</p>
                        <ul class="mt-2 list-inside list-disc text-sm text-red-700 dark:text-red-300">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-4">
            <flux:button
                type="button"
                variant="danger"
                icon="trash"
                x-on:click="$flux.modal('delete-property').show()"
            >
                Eliminar
            </flux:button>

            <div class="flex gap-3">
                <flux:button wire:click="cancel" variant="ghost">
                    Cancelar
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Guardar cambios
                </flux:button>
            </div>
        </div>
    </form>

    {{-- Delete Confirmation Modal --}}
    <x-confirm-modal
        name="delete-property"
        title="¿Eliminar propiedad?"
        message="Esta acción eliminará la propiedad de todas las colecciones donde aparece. Esta acción no se puede deshacer."
    >
        <flux:button variant="danger" wire:click="delete">
            Eliminar propiedad
        </flux:button>
    </x-confirm-modal>
</div>
