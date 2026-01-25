<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">Datos extraidos</div>
                <p class="mt-1 text-sm text-zinc-500">Revisa y edita los valores. Los campos vacios se pueden completar.</p>
            </div>
            @if ($qualityScore > 0)
                <div class="flex items-center gap-2">
                    <div class="text-sm text-zinc-500">Calidad</div>
                    <div @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-medium',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' => $qualityScore >= 70,
                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $qualityScore >= 40 && $qualityScore < 70,
                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $qualityScore < 40,
                    ])>
                        {{ $qualityScore }}%
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Loading State with Stages --}}
    @if ($this->isExtracting)
        <div wire:poll.500ms="checkExtractionStatus" class="flex flex-col items-center justify-center py-16">
            {{-- Animated Icon --}}
            <div class="relative">
                <div class="absolute inset-0 animate-ping rounded-full bg-zinc-200 opacity-75 dark:bg-zinc-700"></div>
                <div class="relative rounded-full bg-white p-4 shadow-lg dark:bg-zinc-800">
                    @if ($extractionStatus === 'queued')
                        <flux:icon name="clock" class="size-8 text-amber-500" />
                    @elseif ($extractionStatus === 'processing')
                        <flux:icon name="sparkles" class="size-8 animate-pulse text-blue-500" />
                    @else
                        <flux:icon name="arrow-path" class="size-8 animate-spin text-zinc-400" />
                    @endif
                </div>
            </div>

            {{-- Status Text --}}
            <p class="mt-6 text-base font-medium text-zinc-900 dark:text-zinc-100">{{ $extractionStage }}</p>

            {{-- Progress Bar --}}
            <div class="mt-4 h-1.5 w-64 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 transition-all duration-500"
                    style="width: {{ $extractionProgress }}%"
                ></div>
            </div>

            {{-- Stage Indicators --}}
            <div class="mt-6 flex items-center gap-8">
                <div class="flex flex-col items-center">
                    <div @class([
                        'flex size-8 items-center justify-center rounded-full text-xs font-medium',
                        'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' => in_array($extractionStatus, ['queued', 'processing', 'completed']),
                        'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' => !in_array($extractionStatus, ['queued', 'processing', 'completed']),
                    ])>
                        @if (in_array($extractionStatus, ['processing', 'completed']))
                            <flux:icon name="check" variant="micro" class="size-4" />
                        @else
                            1
                        @endif
                    </div>
                    <span class="mt-1.5 text-xs text-zinc-500">En cola</span>
                </div>

                <div class="h-px w-8 bg-zinc-200 dark:bg-zinc-700"></div>

                <div class="flex flex-col items-center">
                    <div @class([
                        'flex size-8 items-center justify-center rounded-full text-xs font-medium',
                        'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' => $extractionStatus === 'processing',
                        'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' => $extractionStatus === 'completed',
                        'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' => !in_array($extractionStatus, ['processing', 'completed']),
                    ])>
                        @if ($extractionStatus === 'completed')
                            <flux:icon name="check" variant="micro" class="size-4" />
                        @elseif ($extractionStatus === 'processing')
                            <flux:icon name="arrow-path" variant="micro" class="size-4 animate-spin" />
                        @else
                            2
                        @endif
                    </div>
                    <span class="mt-1.5 text-xs text-zinc-500">Analizando</span>
                </div>

                <div class="h-px w-8 bg-zinc-200 dark:bg-zinc-700"></div>

                <div class="flex flex-col items-center">
                    <div @class([
                        'flex size-8 items-center justify-center rounded-full text-xs font-medium',
                        'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' => $extractionStatus === 'completed',
                        'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' => $extractionStatus !== 'completed',
                    ])>
                        @if ($extractionStatus === 'completed')
                            <flux:icon name="check" variant="micro" class="size-4" />
                        @else
                            3
                        @endif
                    </div>
                    <span class="mt-1.5 text-xs text-zinc-500">Listo</span>
                </div>
            </div>

            {{-- Fun Facts While Waiting --}}
            <div class="mt-8 max-w-sm text-center">
                <p class="text-xs text-zinc-400 dark:text-zinc-500">
                    💡 Nuestro AI analiza tu descripcion para extraer automaticamente todos los datos de la propiedad.
                </p>
            </div>
        </div>
    @elseif ($extractionStatus === 'failed')
        {{-- Error State --}}
        <div class="flex flex-col items-center justify-center py-16">
            <div class="rounded-full bg-red-100 p-4 dark:bg-red-900/30">
                <flux:icon name="exclamation-triangle" class="size-8 text-red-500" />
            </div>
            <p class="mt-4 text-base font-medium text-zinc-900 dark:text-zinc-100">Error al procesar</p>
            <p class="mt-2 max-w-sm text-center text-sm text-zinc-500">{{ $extractionError ?? 'Ocurrio un error al analizar la descripcion.' }}</p>
            <flux:button wire:click="reextract" variant="primary" icon="arrow-path" class="mt-6">
                Reintentar
            </flux:button>
        </div>
    @else
        {{-- JSON-like Editor --}}
        <div class="space-y-4">
            {{-- Property Section --}}
            <x-upload.data-section title="Propiedad" icon="home">
                <x-upload.data-row label="tipo" required>
                    <select
                        wire:change="updateValue('property.property_type', $event.target.value)"
                        class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                    >
                        <option value="">—</option>
                        @foreach ($propertyTypes as $type)
                            <option value="{{ $type->value }}" @selected(($extractedData['property']['property_type'] ?? '') === $type->value)>
                                {{ $type->labelEs() }}
                            </option>
                        @endforeach
                    </select>
                </x-upload.data-row>

                <x-upload.data-row label="operacion" required>
                    <select
                        wire:change="updateValue('property.operation_type', $event.target.value)"
                        class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                    >
                        <option value="">—</option>
                        @foreach ($operationTypes as $type)
                            <option value="{{ $type->value }}" @selected(($extractedData['property']['operation_type'] ?? '') === $type->value)>
                                {{ $type->labelEs() }}
                            </option>
                        @endforeach
                    </select>
                </x-upload.data-row>

                <x-upload.data-row label="colonia">
                    <x-upload.inline-input
                        :value="$extractedData['property']['colonia'] ?? ''"
                        placeholder="Ej: Providencia"
                        wire:blur="updateValue('property.colonia', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="ciudad">
                    <x-upload.inline-input
                        :value="$extractedData['property']['city'] ?? 'Guadalajara'"
                        wire:blur="updateValue('property.city', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="direccion" note="privada">
                    <x-upload.inline-input
                        :value="$extractedData['property']['address'] ?? ''"
                        placeholder="Calle y numero"
                        wire:blur="updateValue('property.address', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="recamaras">
                    <x-upload.inline-input
                        type="number"
                        :value="$extractedData['property']['bedrooms'] ?? ''"
                        wire:blur="updateValue('property.bedrooms', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="banos">
                    <x-upload.inline-input
                        type="number"
                        :value="$extractedData['property']['bathrooms'] ?? ''"
                        wire:blur="updateValue('property.bathrooms', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="medio_bano">
                    <x-upload.inline-input
                        type="number"
                        :value="$extractedData['property']['half_bathrooms'] ?? ''"
                        wire:blur="updateValue('property.half_bathrooms', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="construccion_m2">
                    <x-upload.inline-input
                        type="number"
                        :value="$extractedData['property']['built_size_m2'] ?? ''"
                        wire:blur="updateValue('property.built_size_m2', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="terreno_m2">
                    <x-upload.inline-input
                        type="number"
                        :value="$extractedData['property']['lot_size_m2'] ?? ''"
                        wire:blur="updateValue('property.lot_size_m2', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="estacionamientos">
                    <x-upload.inline-input
                        type="number"
                        :value="$extractedData['property']['parking_spots'] ?? ''"
                        wire:blur="updateValue('property.parking_spots', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="antiguedad_anos">
                    <x-upload.inline-input
                        type="number"
                        :value="$extractedData['property']['age_years'] ?? ''"
                        wire:blur="updateValue('property.age_years', $event.target.value)"
                    />
                </x-upload.data-row>
            </x-upload.data-section>

            {{-- Pricing Section --}}
            <x-upload.data-section title="Precio" icon="currency-dollar">
                <x-upload.data-row label="precio" required>
                    <x-upload.inline-input
                        type="number"
                        :value="$extractedData['pricing']['price'] ?? ''"
                        wire:blur="updateValue('pricing.price', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="moneda">
                    <select
                        wire:change="updateValue('pricing.price_currency', $event.target.value)"
                        class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                    >
                        <option value="MXN" @selected(($extractedData['pricing']['price_currency'] ?? 'MXN') === 'MXN')>MXN</option>
                        <option value="USD" @selected(($extractedData['pricing']['price_currency'] ?? '') === 'USD')>USD</option>
                    </select>
                </x-upload.data-row>

                <x-upload.data-row label="mantenimiento">
                    <x-upload.inline-input
                        type="number"
                        :value="$extractedData['pricing']['maintenance_fee'] ?? ''"
                        placeholder="mensual"
                        wire:blur="updateValue('pricing.maintenance_fee', $event.target.value)"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="servicios_incluidos">
                    <x-upload.tag-list
                        :items="$extractedData['pricing']['included_services'] ?? []"
                        path="pricing.included_services"
                        placeholder="+ agregar"
                    />
                </x-upload.data-row>
            </x-upload.data-section>

            {{-- Terms Section (for rentals) --}}
            @if (($extractedData['property']['operation_type'] ?? '') === 'rent')
                <x-upload.data-section title="Requisitos de renta" icon="document-text">
                    <x-upload.data-row label="deposito_meses">
                        <x-upload.inline-input
                            type="number"
                            :value="$extractedData['terms']['deposit_months'] ?? ''"
                            wire:blur="updateValue('terms.deposit_months', $event.target.value)"
                        />
                    </x-upload.data-row>

                    <x-upload.data-row label="adelanto_meses">
                        <x-upload.inline-input
                            type="number"
                            :value="$extractedData['terms']['advance_months'] ?? ''"
                            wire:blur="updateValue('terms.advance_months', $event.target.value)"
                        />
                    </x-upload.data-row>

                    <x-upload.data-row label="aval_requerido">
                        <select
                            wire:change="updateValue('terms.guarantor_required', $event.target.value === 'true' ? true : ($event.target.value === 'false' ? false : null))"
                            class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                        >
                            <option value="">—</option>
                            <option value="true" @selected(($extractedData['terms']['guarantor_required'] ?? null) === true)>Si</option>
                            <option value="false" @selected(($extractedData['terms']['guarantor_required'] ?? null) === false)>No</option>
                        </select>
                    </x-upload.data-row>

                    <x-upload.data-row label="mascotas">
                        <select
                            wire:change="updateValue('terms.pets_allowed', $event.target.value === 'true' ? true : ($event.target.value === 'false' ? false : null))"
                            class="w-full rounded border-0 bg-transparent py-0.5 text-sm text-zinc-900 focus:ring-0 dark:text-zinc-100"
                        >
                            <option value="">—</option>
                            <option value="true" @selected(($extractedData['terms']['pets_allowed'] ?? null) === true)>Permitidas</option>
                            <option value="false" @selected(($extractedData['terms']['pets_allowed'] ?? null) === false)>No permitidas</option>
                        </select>
                    </x-upload.data-row>

                    <x-upload.data-row label="max_ocupantes">
                        <x-upload.inline-input
                            type="number"
                            :value="$extractedData['terms']['max_occupants'] ?? ''"
                            wire:blur="updateValue('terms.max_occupants', $event.target.value)"
                        />
                    </x-upload.data-row>

                    <x-upload.data-row label="restricciones">
                        <x-upload.tag-list
                            :items="$extractedData['terms']['restrictions'] ?? []"
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
                        :items="$extractedData['amenities']['unit'] ?? []"
                        path="amenities.unit"
                        placeholder="+ agregar"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="edificio">
                    <x-upload.tag-list
                        :items="$extractedData['amenities']['building'] ?? []"
                        path="amenities.building"
                        placeholder="+ agregar"
                    />
                </x-upload.data-row>

                <x-upload.data-row label="servicios">
                    <x-upload.tag-list
                        :items="$extractedData['amenities']['services'] ?? []"
                        path="amenities.services"
                        placeholder="+ agregar"
                    />
                </x-upload.data-row>
            </x-upload.data-section>

            {{-- Location Section --}}
            <x-upload.data-section title="Ubicacion" icon="map-pin">
                <x-upload.data-row label="nombre_edificio">
                    <x-upload.inline-input
                        :value="$extractedData['location']['building_name'] ?? ''"
                        placeholder="Si aplica"
                        wire:blur="updateValue('location.building_name', $event.target.value)"
                    />
                </x-upload.data-row>
            </x-upload.data-section>

            {{-- Description --}}
            <x-upload.data-section title="Descripcion" icon="document-text">
                <div class="px-4 py-3">
                    <textarea
                        wire:blur="updateValue('description', $event.target.value)"
                        rows="4"
                        class="w-full resize-none rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-900 focus:border-zinc-300 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-100"
                        placeholder="Descripcion de la propiedad..."
                    >{{ $extractedData['description'] ?? '' }}</textarea>
                </div>
            </x-upload.data-section>
        </div>

        {{-- Actions --}}
        <div class="mt-8 flex items-center justify-between">
            <flux:button variant="ghost" wire:click="back" icon="arrow-left">
                Volver
            </flux:button>

            <div class="flex items-center gap-3">
                <flux:button variant="ghost" wire:click="reextract" icon="arrow-path" size="sm">
                    Re-analizar
                </flux:button>
                <flux:button variant="primary" wire:click="continue" icon-trailing="arrow-right">
                    Continuar
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Step Indicator --}}
    <div class="mt-6 flex justify-center gap-2">
        <div class="h-2 w-8 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
        <div class="h-2 w-8 rounded-full bg-zinc-900 dark:bg-white"></div>
        <div class="h-2 w-8 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
        <div class="h-2 w-8 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
    </div>
</div>
